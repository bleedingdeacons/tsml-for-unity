<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceFactory;

/**
 * TSML Intergroup Meeting Attendance Factory
 *
 * Creates IntergroupMeetingGroupAttendance objects from a custom database table row ID.
 */
class TsmlIntergroupMeetingGroupAttendanceFactory implements IntergroupMeetingGroupAttendanceFactory
{
    /**
     * Create an IntergroupMeetingGroupAttendance from a database row ID
     *
     * @param int $id Row ID in the attendance table
     * @return IntergroupMeetingGroupAttendance
     */
    public function createFromSource(int $id): IntergroupMeetingGroupAttendance
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return new TsmlIntergroupMeetingGroupAttendance(id: $id);
        }

        return $this->hydrateFromRow($row);
    }

    /**
     * Hydrate an attendance object from a database row array
     *
     * @param array $row Associative array of column values
     * @return TsmlIntergroupMeetingGroupAttendance
     */
    public function hydrateFromRow(array $row): TsmlIntergroupMeetingGroupAttendance
    {
        return new TsmlIntergroupMeetingGroupAttendance(
            id: (int) ($row['id'] ?? 0),
            intergroupMeetingId: (int) ($row['intergroup_meeting_id'] ?? 0),
            meetingLabel: (string) ($row['meeting_label'] ?? ''),
            groupId: (int) ($row['group_id'] ?? 0),
            memberId: (int) ($row['member_id'] ?? 0),
            meetingGroup: (string) ($row['meeting_group'] ?? ''),
            gsrName: (string) ($row['gsr_name'] ?? ''),
            gsrProxy: (bool) ($row['gsr_proxy'] ?? false),
            gsrProxyName: (string) ($row['gsr_proxy_name'] ?? '')
        );
    }

    /**
     * Create a new IntergroupMeetingGroupAttendance instance (not yet persisted)
     *
     * @param int    $intergroupMeetingId Parent intergroup meeting ID
     * @param string $meetingLabel        Display label for the intergroup meeting (denormalised)
     * @param int    $groupId            Group CPT post ID
     * @param int    $memberId           Member ID
     * @param string $meetingGroup        Meeting or group name (looked up from group CPT)
     * @param string $gsrName            GSR name (plain text)
     * @param bool   $gsrProxy           Whether a proxy attended for the GSR
     * @param string $gsrProxyName       Proxy name (plain text)
     * @return IntergroupMeetingGroupAttendance
     */
    public function createNew(
        int $intergroupMeetingId,
        string $meetingLabel,
        int $groupId,
        int $memberId,
        string $meetingGroup,
        string $gsrName,
        bool $gsrProxy = false,
        string $gsrProxyName = ''
    ): IntergroupMeetingGroupAttendance {
        return new TsmlIntergroupMeetingGroupAttendance(
            id: 0,
            intergroupMeetingId: $intergroupMeetingId,
            meetingLabel: $meetingLabel,
            groupId: $groupId,
            memberId: $memberId,
            meetingGroup: $meetingGroup,
            gsrName: $gsrName,
            gsrProxy: $gsrProxy,
            gsrProxyName: $gsrProxyName
        );
    }
}