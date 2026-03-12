<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceRepository;

/**
 * TSML Intergroup Meeting Attendance Repository
 *
 * Implements persistence against a custom WordPress database table.
 */
class TsmlIntergroupMeetingGroupAttendanceRepository implements IntergroupMeetingGroupAttendanceRepository
{
    private TsmlIntergroupMeetingGroupAttendanceFactory $factory;

    /**
     * TsmlIntergroupMeetingGroupAttendanceRepository constructor
     *
     * @param TsmlIntergroupMeetingGroupAttendanceFactory $factory
     */
    public function __construct(TsmlIntergroupMeetingGroupAttendanceFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Find an attendance record by ID
     *
     * @param int $id
     * @return IntergroupMeetingGroupAttendance|null
     */
    public function find(int $id): ?IntergroupMeetingGroupAttendance
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->factory->hydrateFromRow($row);
    }

    /**
     * Find all attendance records matching the given arguments
     *
     * Supported $args keys:
     *  - intergroup_meeting_id (int)  Filter by parent meeting
     *  - group_id              (int)    Filter by group CPT post ID
     *  - member_id             (int)    Filter by member
     *  - meeting_group         (string) Filter by meeting/group name
     *  - gsr_name              (string) Filter by GSR name
     *  - number                (int)    Limit (default -1 = all)
     *  - offset                (int)    Offset for pagination
     *  - orderby               (string) Column to order by (default 'id')
     *  - order                 (string) ASC or DESC (default 'ASC')
     *
     * @param array $args Query arguments
     * @return array<IntergroupMeetingGroupAttendance>
     */
    public function findAll(array $args = []): array
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $where = [];
        $values = [];

        if (isset($args['intergroup_meeting_id'])) {
            $where[] = 'intergroup_meeting_id = %d';
            $values[] = (int) $args['intergroup_meeting_id'];
        }

        if (isset($args['group_id'])) {
            $where[] = 'group_id = %d';
            $values[] = (int) $args['group_id'];
        }

        if (isset($args['member_id'])) {
            $where[] = 'member_id = %d';
            $values[] = (int) $args['member_id'];
        }

        if (isset($args['meeting_group'])) {
            $where[] = 'meeting_group = %s';
            $values[] = $args['meeting_group'];
        }

        if (isset($args['gsr_name'])) {
            $where[] = 'gsr_name = %s';
            $values[] = $args['gsr_name'];
        }

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = ['id', 'intergroup_meeting_id', 'group_id', 'meeting_group', 'gsr_name'];
        $orderBy = isset($args['orderby']) && in_array($args['orderby'], $allowedOrderBy, true)
            ? $args['orderby']
            : 'id';

        $order = isset($args['order']) && strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $limit = '';
        $number = isset($args['number']) ? (int) $args['number'] : -1;
        if ($number > 0) {
            $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
            $limit = $wpdb->prepare('LIMIT %d OFFSET %d', $number, $offset);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$table} {$whereClause} ORDER BY {$orderBy} {$order} {$limit}";

        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($sql, ...$values);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $records = [];
        foreach ($rows as $row) {
            $records[] = $this->factory->hydrateFromRow($row);
        }

        return $records;
    }

    /**
     * Find all attendance records for a specific intergroup meeting
     *
     * @param int $intergroupMeetingId
     * @return array<IntergroupMeetingGroupAttendance>
     */
    public function findByIntergroupMeeting(int $intergroupMeetingId): array
    {
        return $this->findAll(['intergroup_meeting_id' => $intergroupMeetingId]);
    }

    /**
     * Get total count of attendance records matching criteria
     *
     * Accepts the same filter keys as findAll() (excluding pagination).
     *
     * @param array $args Query arguments
     * @return int
     */
    public function count(array $args = []): int
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $where = [];
        $values = [];

        if (isset($args['intergroup_meeting_id'])) {
            $where[] = 'intergroup_meeting_id = %d';
            $values[] = (int) $args['intergroup_meeting_id'];
        }

        if (isset($args['group_id'])) {
            $where[] = 'group_id = %d';
            $values[] = (int) $args['group_id'];
        }

        if (isset($args['member_id'])) {
            $where[] = 'member_id = %d';
            $values[] = (int) $args['member_id'];
        }

        if (isset($args['meeting_group'])) {
            $where[] = 'meeting_group = %s';
            $values[] = $args['meeting_group'];
        }

        if (isset($args['gsr_name'])) {
            $where[] = 'gsr_name = %s';
            $values[] = $args['gsr_name'];
        }

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$table} {$whereClause}";

        if (!empty($values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql = $wpdb->prepare($sql, ...$values);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Save an attendance record (insert or update)
     *
     * @param IntergroupMeetingGroupAttendance $attendance
     * @return bool
     */
    public function save(IntergroupMeetingGroupAttendance $attendance): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $data = [
            'intergroup_meeting_id' => $attendance->getIntergroupMeetingId(),
            'group_id'              => $attendance->getGroupId(),
            'member_id'             => $attendance->getMemberId(),
            'meeting_group'         => $attendance->getMeetingGroup(),
            'gsr_name'              => $attendance->getGsrName(),
            'gsr_proxy'             => $attendance->isGsrProxy() ? 1 : 0,
            'gsr_proxy_name'        => $attendance->getGsrProxyName(),
        ];

        $formats = ['%d', '%d', '%d', '%s', '%s', '%d', '%s'];

        // Update existing record
        if ($attendance->getId() > 0) {
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $attendance->getId()],
                $formats,
                ['%d']
            );

            return $result !== false;
        }

        // Insert new record
        $result = $wpdb->insert($table, $data, $formats);

        return $result !== false;
    }

    /**
     * Delete an attendance record
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    /**
     * Delete the attendance record for a specific member at a specific intergroup meeting
     *
     * @param int $intergroupMeetingId
     * @param int $memberId
     * @return bool
     */
    public function deleteByIntergroupMeetingAndMember(int $intergroupMeetingId, int $memberId): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $result = $wpdb->delete(
            $table,
            [
                'intergroup_meeting_id' => $intergroupMeetingId,
                'member_id'             => $memberId,
            ],
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Delete the attendance record for a specific group at a specific intergroup meeting
     *
     * @param int $intergroupMeetingId
     * @param int $groupId
     * @return bool
     */
    public function deleteByIntergroupMeetingAndGroup(int $intergroupMeetingId, int $groupId): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        $result = $wpdb->delete(
            $table,
            [
                'intergroup_meeting_id' => $intergroupMeetingId,
                'group_id'              => $groupId,
            ],
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function existsForMeetingAndGroup(int $intergroupMeetingId, int $groupId): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingGroupAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant; esc_sql as defence-in-depth
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql($table) . "` WHERE intergroup_meeting_id = %d AND group_id = %d",
            $intergroupMeetingId,
            $groupId
        ));

        return $count > 0;
    }
}