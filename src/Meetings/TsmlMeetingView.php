<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingView;
use Unity\Members\Interfaces\Member;
use Unity\Positions\Interfaces\Position;

/**
 * Position View Class
 *
 * Combines position and member data
 */
class TsmlMeetingView implements MeetingView
{
    private Meeting $meeting;
    /**
     * @var Member|Member[]|null
     */
    private Member|array|null $members;

    /**
     * Constructor
     *
     * @param Meeting $meeting The meeting
     * @param Member[] $members The members associated
     */
    public function __construct(
        Meeting $meeting,
        array   $members,
    )
    {
        $this->meeting = $meeting;
        $this->members = $members;
    }

    public function getMeeting(): Meeting
    {
        return $this->meeting;
    }

    public function getMembers(): array
    {
        return $this->members;
    }


    public function getGsrNames(): array
    {
        $names = [];

        foreach ($this->members as $member) {
            $names[] = $member->getGsrName();
        }

        return $names;

    }
}