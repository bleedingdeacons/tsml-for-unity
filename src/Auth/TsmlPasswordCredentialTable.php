<?php

declare(strict_types=1);

namespace TsmlForUnity\Auth;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages the custom database table holding member password credentials.
 *
 * <p>The store behind {@see \Unity\Auth\Interfaces\PasswordCredentialRepository}.
 * Unity declares that contract and binds nothing to it, as it does for
 * every repository; this plugin supplies the table and the SQL, alongside
 * the other tables it already owns.</p>
 *
 * <p><b>It replaces two tables, and absorbs them.</b> Reach kept
 * `wp_reach_credentials` and Fellowship kept `wp_fellowship_credentials`,
 * with byte-identical schemas — so a member who set a password in one
 * could not sign into the other with it, and a reset in one left the
 * other stale with nothing anywhere to say so. A member has one
 * password.</p>
 *
 * Handles creation on activation and schema upgrades via dbDelta, the
 * same way the attendance tables do.
 */
class TsmlPasswordCredentialTable
{
    /**
     * Database table version for schema upgrades
     */
    public const DB_VERSION = '1.0';

    /**
     * Option key storing the current installed table version
     */
    public const DB_VERSION_OPTION = 'unity_credentials_db_version';

    /**
     * Table name suffix (appended to $wpdb->prefix)
     */
    public const TABLE_NAME = 'unity_credentials';

    /**
     * The two plugin-private tables this one replaces, absorbed on
     * creation and then left alone.
     */
    private const LEGACY_TABLES = ['reach_credentials', 'fellowship_credentials'];

    /**
     * Get the full table name including the WordPress prefix
     *
     * @return string
     */
    public static function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create or upgrade the credentials table
     *
     * Uses WordPress dbDelta() so it is safe to call on every activation
     * and during upgrade checks. Absorbs the two legacy tables afterwards,
     * which is idempotent for the reason {@see absorb()} explains.
     *
     * @return void
     */
    public static function createTable(): void
    {
        global $wpdb;

        $table = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        // Only hashed secrets: a bcrypt password hash and the SHA-256 hex
        // of a mailed reset token. A database dump alone yields neither a
        // usable password nor a usable reset link.
        $sql = "CREATE TABLE {$table} (
            email VARCHAR(254) NOT NULL,
            password_hash VARCHAR(255) NOT NULL DEFAULT '',
            reset_token_hash CHAR(64) NOT NULL DEFAULT '',
            reset_expires_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            locked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (email),
            KEY reset_token_hash (reset_token_hash)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        foreach (self::LEGACY_TABLES as $legacy) {
            self::absorb($wpdb->prefix . $legacy);
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Copy any rows from a plugin's old private credentials table into
     * this one, then leave the old table alone.
     *
     * <p><b>The newer row wins, and neither is overwritten blind.</b> A
     * member who set a password in Reach and later set one in Fellowship
     * holds two different hashes, and only one of them is the password
     * they think they have. `updated_at` is what says which, so the
     * INSERT carries an ON DUPLICATE KEY UPDATE guarded on it: a row
     * already here from the other table is replaced only by a strictly
     * newer one. Running this twice, or in either order, therefore
     * reaches the same answer.</p>
     *
     * <p><b>The old tables are not dropped.</b> This is the only copy of
     * some members' passwords, the upgrade runs unattended on a page
     * load, and a bad one that has also destroyed its source is not
     * recoverable. They are left in place, unread, for somebody to remove
     * deliberately once this has been seen to work.</p>
     *
     * <p>Absent tables are the normal case — a site with Reach but not
     * Fellowship has only one — so a missing table is silence, not a
     * failure.</p>
     *
     * @param string $legacyTable Fully prefixed name of the old table.
     * @return void
     */
    private static function absorb(string $legacyTable): void
    {
        global $wpdb;

        $table = self::getTableName();

        // SHOW TABLES LIKE rather than information_schema: it needs no
        // extra grant, and a shared host may not give one.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacyTable));

        if ($exists !== $legacyTable) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be parameterised with prepare(); both are built from $wpdb->prefix and a class constant, and the source is proved to exist above
        $wpdb->query(
            "INSERT INTO {$table}
                 (email, password_hash, reset_token_hash, reset_expires_at,
                  failed_attempts, locked_until, updated_at)
             SELECT email, password_hash, reset_token_hash, reset_expires_at,
                    failed_attempts, locked_until, updated_at
               FROM {$legacyTable}
             ON DUPLICATE KEY UPDATE
                 password_hash = IF(VALUES(updated_at) > {$table}.updated_at, VALUES(password_hash), {$table}.password_hash),
                 reset_token_hash = IF(VALUES(updated_at) > {$table}.updated_at, VALUES(reset_token_hash), {$table}.reset_token_hash),
                 reset_expires_at = IF(VALUES(updated_at) > {$table}.updated_at, VALUES(reset_expires_at), {$table}.reset_expires_at),
                 failed_attempts = IF(VALUES(updated_at) > {$table}.updated_at, VALUES(failed_attempts), {$table}.failed_attempts),
                 locked_until = IF(VALUES(updated_at) > {$table}.updated_at, VALUES(locked_until), {$table}.locked_until),
                 updated_at = GREATEST({$table}.updated_at, VALUES(updated_at))"
        );
    }

    /**
     * Drop the credentials table
     *
     * Called on plugin uninstall when a clean removal is desired. Note
     * that this destroys every member's password: the two apps fall back
     * to OAuth, which is their default sign-in path, but anybody who used
     * a password will need a new one.
     *
     * @return void
     */
    public static function dropTable(): void
    {
        global $wpdb;

        $table = self::getTableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be parameterised with prepare(); esc_sql used as defence-in-depth
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table) . "`");

        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Check whether the table needs to be created or upgraded
     *
     * Compare the stored version against DB_VERSION and run createTable()
     * when they differ.
     *
     * @return void
     */
    public static function maybeUpgrade(): void
    {
        $installed = get_option(self::DB_VERSION_OPTION);

        if ($installed !== self::DB_VERSION) {
            self::createTable();
        }
    }
}
