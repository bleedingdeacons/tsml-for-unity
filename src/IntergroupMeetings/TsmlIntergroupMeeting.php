<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;

/**
 * TSML Intergroup Meeting Class
 */
class TsmlIntergroupMeeting implements IntergroupMeeting
{
    private int $id;

    private string $title;

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
     * @param string $title Meeting title
     * @param array<int> $groupAttendees Array of member IDs
     * @param array<int> $officersAttending Array of officer IDs
     * @param string $date Meeting date (Y-m-d format)
     */
    public function __construct(
        int $id,
        string $title = '',
        array $groupAttendees = [],
        array $officersAttending = [],
        string $date = ''
    ) {
        $this->id = $id;
        $this->title = $title;
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
     * Get the title of the intergroup meeting
     *
     * @return string The meeting title or empty string if not set
     */
    public function getTitle(): string
    {
        return $this->title;
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

    /**
     * Add a member ID to the group attendees list
     *
     * @param int $memberId
     * @return bool True if the member was added, false if already present
     */
    public function addGroupAttendee(int $memberId): bool
    {
        if (in_array($memberId, $this->groupAttendees, true)) {
            return false;
        }

        $this->groupAttendees[] = $memberId;
        return true;
    }

    /**
     * Remove a member ID from the group attendees list
     *
     * @param int $memberId
     * @return bool True if the member was removed, false if not present
     */
    public function removeGroupAttendee(int $memberId): bool
    {
        $key = array_search($memberId, $this->groupAttendees, true);

        if ($key === false) {
            return false;
        }

        unset($this->groupAttendees[$key]);
        $this->groupAttendees = array_values($this->groupAttendees);
        return true;
    }

    /**
     * Check if a member ID is in the group attendees list
     *
     * @param int $memberId
     * @return bool
     */
    public function hasGroupAttendee(int $memberId): bool
    {
        return in_array($memberId, $this->groupAttendees, true);
    }

    /**
     * Add an officer ID to the officers attending list
     *
     * @param int $officerId
     * @return bool True if the officer was added, false if already present
     */
    public function addOfficerAttendee(int $officerId): bool
    {
        if (in_array($officerId, $this->officersAttending, true)) {
            return false;
        }

        $this->officersAttending[] = $officerId;
        return true;
    }

    /**
     * Remove an officer ID from the officers attending list
     *
     * @param int $officerId
     * @return bool True if the officer was removed, false if not present
     */
    public function removeOfficerAttendee(int $officerId): bool
    {
        $key = array_search($officerId, $this->officersAttending, true);

        if ($key === false) {
            return false;
        }

        unset($this->officersAttending[$key]);
        $this->officersAttending = array_values($this->officersAttending);
        return true;
    }

    /**
     * Check if an officer ID is in the officers attending list
     *
     * @param int $officerId
     * @return bool
     */
    public function hasOfficerAttendee(int $officerId): bool
    {
        return in_array($officerId, $this->officersAttending, true);
    }
}