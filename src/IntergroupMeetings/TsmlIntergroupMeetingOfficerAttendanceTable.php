<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

/**
 * Manages the custom database table for intergroup meeting officer attendance.
 *
 * Handles table creation on activation and schema upgrades via dbDelta.
 */
class TsmlIntergroupMeetingOfficerAttendanceTable
{
    /**
     * Database table version for schema upgrades
     */
    public const DB_VERSION = '1.0';

    /**
     * Option key storing the current installed table version
     */
    public const DB_VERSION_OPTION = 'unity_ig_officer_attendance_db_version';

    /**
     * Table name suffix (appended to $wpdb->prefix)
     */
    public const TABLE_NAME = 'unity_ig_officer_attendance_register';

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
     * Create or upgrade the officer attendance table
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
            officer_id bigint(20) unsigned NOT NULL,
            position_name varchar(255) NOT NULL DEFAULT '',
            officer_name varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY intergroup_meeting_id (intergroup_meeting_id),
            KEY officer_id (officer_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Drop the officer attendance table
     *
     * Called on plugin uninstall when a clean removal is desired.
     *
     * @return void
     */
    public static function dropTable(): void
    {
        global $wpdb;

        $table = self::getTableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table}");

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