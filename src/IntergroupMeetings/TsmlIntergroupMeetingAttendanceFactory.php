<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingAttendanceFactory;

/**
 * TSML Intergroup Meeting Attendance Factory
 *
 * Creates IntergroupMeetingAttendance objects from a custom database table row ID.
 */
class TsmlIntergroupMeetingAttendanceFactory implements IntergroupMeetingAttendanceFactory
{
    /**
     * Create an IntergroupMeetingAttendance from a database row ID
     *
     * @param int $id Row ID in the attendance table
     * @return IntergroupMeetingAttendance
     */
    public function createFromSource(int $id): IntergroupMeetingAttendance
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return new TsmlIntergroupMeetingAttendance(id: $id);
        }

        return $this->hydrateFromRow($row);
    }

    /**
     * Hydrate an attendance object from a database row array
     *
     * @param array $row Associative array of column values
     * @return TsmlIntergroupMeetingAttendance
     */
    public function hydrateFromRow(array $row): TsmlIntergroupMeetingAttendance
    {
        return new TsmlIntergroupMeetingAttendance(
            id: (int) ($row['id'] ?? 0),
            intergroupMeetingId: (int) ($row['intergroup_meeting_id'] ?? 0),
            memberId: (int) ($row['member_id'] ?? 0),
            meetingGroup: (string) ($row['meeting_group'] ?? ''),
            gsrName: (string) ($row['gsr_name'] ?? ''),
            gsrProxy: (bool) ($row['gsr_proxy'] ?? false),
            gsrProxyName: (string) ($row['gsr_proxy_name'] ?? '')
        );
    }

    /**
     * Create a new IntergroupMeetingAttendance instance (not yet persisted)
     *
     * @param int    $intergroupMeetingId Parent intergroup meeting ID
     * @param int    $memberId           Member ID
     * @param string $meetingGroup        Meeting or group name (plain text)
     * @param string $gsrName            GSR name (plain text)
     * @param bool   $gsrProxy           Whether a proxy attended for the GSR
     * @param string $gsrProxyName       Proxy name (plain text)
     * @return IntergroupMeetingAttendance
     */
    public function createNew(
        int $intergroupMeetingId,
        int $memberId,
        string $meetingGroup,
        string $gsrName,
        bool $gsrProxy = false,
        string $gsrProxyName = ''
    ): IntergroupMeetingAttendance {
        return new TsmlIntergroupMeetingAttendance(
            id: 0,
            intergroupMeetingId: $intergroupMeetingId,
            memberId: $memberId,
            meetingGroup: $meetingGroup,
            gsrName: $gsrName,
            gsrProxy: $gsrProxy,
            gsrProxyName: $gsrProxyName
        );
    }
}