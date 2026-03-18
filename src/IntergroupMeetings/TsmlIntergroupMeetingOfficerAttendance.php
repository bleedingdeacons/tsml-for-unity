<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;

/**
 * TSML Intergroup Meeting Officer Attendance
 *
 * Records individual officer attendance at an intergroup meeting.
 * Backed by a custom database table rather than a custom post type.
 */
class TsmlIntergroupMeetingOfficerAttendance implements IntergroupMeetingOfficerAttendance
{
    private int $id;
    private int $intergroupMeetingId;
    private int $officerId;
    private string $positionName;
    private string $officerName;

    /**
     * TsmlIntergroupMeetingOfficerAttendance constructor
     *
     * @param int    $id                   Row ID (0 for new unsaved records)
     * @param int    $intergroupMeetingId   Parent intergroup meeting post ID
     * @param int    $officerId            Officer member ID
     * @param string $positionName         Position name (plain text)
     * @param string $officerName          Officer name (plain text)
     */
    public function __construct(
        int $id = 0,
        int $intergroupMeetingId = 0,
        int $officerId = 0,
        string $positionName = '',
        string $officerName = ''
    ) {
        $this->id = $id;
        $this->intergroupMeetingId = $intergroupMeetingId;
        $this->officerId = $officerId;
        $this->positionName = $positionName;
        $this->officerName = $officerName;
    }

    /**
     * Get the attendance record ID
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the intergroup meeting ID this attendance belongs to
     *
     * @return int
     */
    public function getIntergroupMeetingId(): int
    {
        return $this->intergroupMeetingId;
    }

    /**
     * Get the member ID this attendance record belongs to
     *
     * @return int
     */
    public function getOfficerId(): int
    {
        return $this->officerId;
    }

    /**
     * Get the officer position name (plain text, no relationship)
     *
     * @return string
     */
    public function getPositionName(): string
    {
        return $this->positionName;
    }

    /**
     * Get the officer name (plain text, no relationship)
     *
     * @return string
     */
    public function getOfficerName(): string
    {
        return $this->officerName;
    }
}