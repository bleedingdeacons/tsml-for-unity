<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

/**
 * Manages the custom database table for intergroup meeting attendance.
 *
 * Handles table creation on activation and schema upgrades via dbDelta.
 */
class TsmlIntergroupMeetingGroupAttendanceTable
{
    /**
     * Database table version for schema upgrades
     */
    public const DB_VERSION = '1.1';

    /**
     * Option key storing the current installed table version
     */
    public const DB_VERSION_OPTION = 'unity_ig_group_attendance_db_version';

    /**
     * Table name suffix (appended to $wpdb->prefix)
     */
    public const TABLE_NAME = 'unity_ig_group_attendance_register';

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
     * Create or upgrade the attendance table
     *
     * Uses WordPress dbDelta() so it is safe to call on every activation
     * and during upgrade checks.
     *
     * @return void
     */
    public static function createTable(): void
    {
        global $wpdb;

        $table = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            intergroup_meeting_id bigint(20) unsigned NOT NULL,
            group_id bigint(20) unsigned NOT NULL DEFAULT 0,
            member_id bigint(20) unsigned NOT NULL,
            meeting_group varchar(255) NOT NULL DEFAULT '',
            gsr_name varchar(255) NOT NULL DEFAULT '',
            gsr_proxy tinyint(1) NOT NULL DEFAULT 0,
            gsr_proxy_name varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY intergroup_meeting_id (intergroup_meeting_id),
            KEY group_id (group_id),
            KEY member_id (member_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Drop the attendance table
     *
     * Called on plugin uninstall when a clean removal is desired.
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