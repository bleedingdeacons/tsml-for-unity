<?php

declare(strict_types=1);

namespace TsmlForUnity;

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
    private array $attendees;

    private string $date;

    /**
     * TsmlIntergroupMeeting constructor
     *
     * @param int $id Post ID
     * @param array<int> $attendees Array of member IDs
     * @param string $date Meeting date (Y-m-d format)
     */
    public function __construct(
        int $id,
        array $attendees = [],
        string $date = ''
    ) {
        $this->id = $id;
        $this->attendees = $attendees;
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
    public function getAttendees(): array
    {
        return $this->attendees;
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