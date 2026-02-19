<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceFactory;

/**
 * TSML Intergroup Meeting Officer Attendance Factory
 *
 * Creates IntergroupMeetingOfficerAttendance objects from a custom database table row ID.
 */
class TsmlIntergroupMeetingOfficerAttendanceFactory implements IntergroupMeetingOfficerAttendanceFactory
{
    /**
     * Create an IntergroupMeetingOfficerAttendance from a database row ID
     *
     * @param int $id Row ID in the officer attendance table
     * @return IntergroupMeetingOfficerAttendance
     */
    public function createFromSource(int $id): IntergroupMeetingOfficerAttendance
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return new TsmlIntergroupMeetingOfficerAttendance(id: $id);
        }

        return $this->hydrateFromRow($row);
    }

    /**
     * Hydrate an officer attendance object from a database row array
     *
     * @param array $row Associative array of column values
     * @return TsmlIntergroupMeetingOfficerAttendance
     */
    public function hydrateFromRow(array $row): TsmlIntergroupMeetingOfficerAttendance
    {
        return new TsmlIntergroupMeetingOfficerAttendance(
            id: (int) ($row['id'] ?? 0),
            intergroupMeetingId: (int) ($row['intergroup_meeting_id'] ?? 0),
            officerId: (int) ($row['officer_id'] ?? 0),
            positionName: (string) ($row['position_name'] ?? ''),
            officerName: (string) ($row['officer_name'] ?? '')
        );
    }

    /**
     * Create a new IntergroupMeetingOfficerAttendance instance (not yet persisted)
     *
     * @param int    $intergroupMeetingId Parent intergroup meeting ID
     * @param int    $officerId           Officer member ID
     * @param string $positionName        Position name (plain text)
     * @param string $officerName         Officer name (plain text)
     * @return IntergroupMeetingOfficerAttendance
     */
    public function createNew(
        int $intergroupMeetingId,
        int $officerId,
        string $positionName,
        string $officerName
    ): IntergroupMeetingOfficerAttendance {
        return new TsmlIntergroupMeetingOfficerAttendance(
            id: 0,
            intergroupMeetingId: $intergroupMeetingId,
            officerId: $officerId,
            positionName: $positionName,
            officerName: $officerName
        );
    }
}