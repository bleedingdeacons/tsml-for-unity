<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingInterface;

/**
 * TSML Intergroup Meeting Class
 */
class TsmlIntergroupMeeting implements IntergroupMeetingInterface
{
    private int $id;

    /**
     * @var array<int>
     */
    private array $groupAttendees;

    /**
     * @var array<int>
     */
    private array $officersAttending;

    private string $date;

    /**
     * TsmlIntergroupMeeting constructor
     *
     * @param int $id Post ID
     * @param array<int> $groupAttendees Array of member IDs
     * @param array<int> $officersAttending Array of officer IDs
     * @param string $date Meeting date (Y-m-d format)
     */
    public function __construct(
        int $id,
        array $groupAttendees = [],
        array $officersAttending = [],
        string $date = ''
    ) {
        $this->id = $id;
        $this->groupAttendees = $groupAttendees;
        $this->officersAttending = $officersAttending;
        $this->date = $date;
    }

    /**
     * Get the intergroup meeting ID
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the array of member IDs attending the meeting
     *
     * @return array<int>
     */
    public function getGroupAttendees(): array
    {
        return $this->groupAttendees;
    }

    /**
     * Get the array of officer IDs attending the meeting
     *
     * @return array<int>
     */
    public function getOfficersAttending(): array
    {
        return $this->officersAttending;
    }

    /**
     * Get the date of the meeting
     *
     * @return string Date in format Y-m-d or empty string if not set
     */
    public function getDate(): string
    {
        return $this->date;
    }
}