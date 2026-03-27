<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;

/**
 * TSML Intergroup Meeting Attendance
 *
 * Records individual attendance at an intergroup meeting.
 * Backed by a custom database table rather than a custom post type.
 */
class TsmlIntergroupMeetingGroupAttendance implements IntergroupMeetingGroupAttendance
{
    private int $id;
    private int $intergroupMeetingId;
    private string $meetingLabel;
    private int $groupId;
    private int $memberId;
    private string $meetingGroup;
    private string $gsrName;
    private bool $gsrProxy;
    private string $gsrProxyName;

    /**
     * TsmlIntergroupMeetingGroupAttendance constructor
     *
     * @param int    $id                   Row ID (0 for new unsaved records)
     * @param int    $intergroupMeetingId   Parent intergroup meeting post ID
     * @param string $meetingLabel          Display label for the intergroup meeting (denormalised)
     * @param int    $groupId              Group CPT post ID
     * @param int    $memberId             Member ID
     * @param string $meetingGroup          Meeting or group name (looked up from group CPT)
     * @param string $gsrName              GSR name (plain text)
     * @param bool   $gsrProxy             Whether a proxy attended for the GSR
     * @param string $gsrProxyName         Proxy name (plain text)
     */
    public function __construct(
        int $id = 0,
        int $intergroupMeetingId = 0,
        string $meetingLabel = '',
        int $groupId = 0,
        int $memberId = 0,
        string $meetingGroup = '',
        string $gsrName = '',
        bool $gsrProxy = false,
        string $gsrProxyName = ''
    ) {
        $this->id = $id;
        $this->intergroupMeetingId = $intergroupMeetingId;
        $this->meetingLabel = $meetingLabel;
        $this->groupId = $groupId;
        $this->memberId = $memberId;
        $this->meetingGroup = $meetingGroup;
        $this->gsrName = $gsrName;
        $this->gsrProxy = $gsrProxy;
        $this->gsrProxyName = $gsrProxyName;
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
     * Get the display label for the intergroup meeting
     *
     * @return string
     */
    public function getMeetingLabel(): string
    {
        return $this->meetingLabel;
    }

    /**
     * Get the group ID (group CPT post ID) this attendance belongs to
     *
     * @return int
     */
    public function getGroupId(): int
    {
        return $this->groupId;
    }

    /**
     * Get the member ID this attendance record belongs to
     *
     * @return int
     */
    public function getMemberId(): int
    {
        return $this->memberId;
    }

    /**
     * Get the meeting or group name
     *
     * @return string
     */
    public function getMeetingGroup(): string
    {
        return $this->meetingGroup;
    }

    /**
     * Get the GSR name (plain text)
     *
     * @return string
     */
    public function getGsrName(): string
    {
        return $this->gsrName;
    }

    /**
     * Check if a proxy attended in place of the GSR
     *
     * @return bool
     */
    public function isGsrProxy(): bool
    {
        return $this->gsrProxy;
    }

    /**
     * Get the proxy name when a proxy attended for the GSR
     *
     * @return string Proxy name or empty string if no proxy
     */
    public function getGsrProxyName(): string
    {
        return $this->gsrProxyName;
    }
}