<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceRepository;

/**
 * TSML Intergroup Meeting Officer Attendance Repository
 *
 * Implements persistence against a custom WordPress database table.
 */
class TsmlIntergroupMeetingOfficerAttendanceRepository implements IntergroupMeetingOfficerAttendanceRepository
{
    private TsmlIntergroupMeetingOfficerAttendanceFactory $factory;

    /**
     * TsmlIntergroupMeetingOfficerAttendanceRepository constructor
     *
     * @param TsmlIntergroupMeetingOfficerAttendanceFactory $factory
     */
    public function __construct(TsmlIntergroupMeetingOfficerAttendanceFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Find an officer attendance record by ID
     *
     * @param int $id
     * @return IntergroupMeetingOfficerAttendance|null
     */
    public function find(int $id): ?IntergroupMeetingOfficerAttendance
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

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
     * Find all officer attendance records matching the given arguments
     *
     * Supported $args keys:
     *  - intergroup_meeting_id (int)    Filter by parent meeting
     *  - officer_id            (int)    Filter by officer
     *  - position_name         (string) Filter by position name
     *  - officer_name          (string) Filter by officer name
     *  - number                (int)    Limit (default -1 = all)
     *  - offset                (int)    Offset for pagination
     *  - orderby               (string) Column to order by (default 'id')
     *  - order                 (string) ASC or DESC (default 'ASC')
     *
     * @param array $args Query arguments
     * @return array<IntergroupMeetingOfficerAttendance>
     */
    public function findAll(array $args = []): array
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $where = [];
        $values = [];

        if (isset($args['intergroup_meeting_id'])) {
            $where[] = 'intergroup_meeting_id = %d';
            $values[] = (int) $args['intergroup_meeting_id'];
        }

        if (isset($args['officer_id'])) {
            $where[] = 'officer_id = %d';
            $values[] = (int) $args['officer_id'];
        }

        if (isset($args['position_name'])) {
            $where[] = 'position_name = %s';
            $values[] = $args['position_name'];
        }

        if (isset($args['officer_name'])) {
            $where[] = 'officer_name = %s';
            $values[] = $args['officer_name'];
        }

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        $allowedOrderBy = ['id', 'intergroup_meeting_id', 'position_name', 'officer_name'];
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
     * Find all officer attendance records for a specific intergroup meeting
     *
     * @param int $intergroupMeetingId
     * @return array<IntergroupMeetingOfficerAttendance>
     */
    public function findByIntergroupMeeting(int $intergroupMeetingId): array
    {
        return $this->findAll(['intergroup_meeting_id' => $intergroupMeetingId]);
    }

    /**
     * Get total count of officer attendance records matching criteria
     *
     * Accepts the same filter keys as findAll() (excluding pagination).
     *
     * @param array $args Query arguments
     * @return int
     */
    public function count(array $args = []): int
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $where = [];
        $values = [];

        if (isset($args['intergroup_meeting_id'])) {
            $where[] = 'intergroup_meeting_id = %d';
            $values[] = (int) $args['intergroup_meeting_id'];
        }

        if (isset($args['officer_id'])) {
            $where[] = 'officer_id = %d';
            $values[] = (int) $args['officer_id'];
        }

        if (isset($args['position_name'])) {
            $where[] = 'position_name = %s';
            $values[] = $args['position_name'];
        }

        if (isset($args['officer_name'])) {
            $where[] = 'officer_name = %s';
            $values[] = $args['officer_name'];
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
     * Save an officer attendance record (insert or update)
     *
     * @param IntergroupMeetingOfficerAttendance $attendance
     * @return bool
     */
    public function save(IntergroupMeetingOfficerAttendance $attendance): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $data = [
            'intergroup_meeting_id' => $attendance->getIntergroupMeetingId(),
            'officer_id'            => $attendance->getOfficerId(),
            'position_name'         => $attendance->getPositionName(),
            'officer_name'          => $attendance->getOfficerName(),
        ];

        $formats = ['%d', '%d', '%s', '%s'];

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
     * Delete an officer attendance record
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        return $result !== false;
    }

    /**
     * Delete the officer attendance record for a specific officer at a specific intergroup meeting
     *
     * @param int $intergroupMeetingId
     * @param int $officerId
     * @return bool
     */
    public function deleteByIntergroupMeetingAndOfficer(int $intergroupMeetingId, int $officerId): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $result = $wpdb->delete(
            $table,
            [
                'intergroup_meeting_id' => $intergroupMeetingId,
                'officer_id'            => $officerId,
            ],
            ['%d', '%d']
        );

        return $result !== false;
    }

    /**
     * Update the position name and officer name on the attendance record
     * for a given officer at a specific intergroup meeting.
     *
     * @param int    $intergroupMeetingId The intergroup meeting ID
     * @param int    $officerId           The officer (member) ID
     * @param string $positionName        New position name (plain text)
     * @param string $officerName         New officer name (plain text)
     * @return int Number of rows updated
     */
    public function updateByMeetingAndOfficer(int $intergroupMeetingId, int $officerId, string $positionName, string $officerName): int
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        $result = $wpdb->update(
            $table,
            [
                'position_name' => $positionName,
                'officer_name'  => $officerName,
            ],
            [
                'intergroup_meeting_id' => $intergroupMeetingId,
                'officer_id'            => $officerId,
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );

        return $result !== false ? (int) $result : 0;
    }

    /**
     * @inheritDoc
     */
    public function existsForMeetingAndOfficer(int $intergroupMeetingId, int $officerId): bool
    {
        global $wpdb;

        $table = TsmlIntergroupMeetingOfficerAttendanceTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constant; esc_sql as defence-in-depth
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `" . esc_sql($table) . "` WHERE intergroup_meeting_id = %d AND officer_id = %d",
            $intergroupMeetingId,
            $officerId
        ));

        return $count > 0;
    }
}