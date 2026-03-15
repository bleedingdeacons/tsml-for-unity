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
     * @var array<int> Array of group IDs (group CPT post IDs) attending
     */
    private array $groupAttendees;

    /**
     * @var array<int>
     */
    private array $officersAttending;

    private string $date;

    private string $updated;

    /**
     * TsmlIntergroupMeeting constructor
     *
     * @param int $id Post ID
     * @param string $title Meeting title
     * @param array<int> $groupAttendees Array of group IDs (group CPT post IDs) attending
     * @param array<int> $officersAttending Array of officer member IDs attending
     * @param string $date Meeting date (Y-m-d format, normalised from ACF d/m/Y at factory level)
     * @param string $updated Last updated datetime string
     */
    public function __construct(
        int $id,
        string $title = '',
        array $groupAttendees = [],
        array $officersAttending = [],
        string $date = '',
        string $updated = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->groupAttendees = $groupAttendees;
        $this->officersAttending = $officersAttending;
        $this->date = $date;
        $this->updated = $updated;
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
     * Get the array of group IDs attending the meeting
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
     * Add a group ID to the group attendees list
     *
     * @param int $groupId
     * @return bool True if the group was added, false if already present
     */
    public function addGroupAttendee(int $groupId): bool
    {
        if (in_array($groupId, $this->groupAttendees, true)) {
            return false;
        }

        $this->groupAttendees[] = $groupId;
        return true;
    }

    /**
     * Remove a group ID from the group attendees list
     *
     * @param int $groupId
     * @return bool True if the group was removed, false if not present
     */
    public function removeGroupAttendee(int $groupId): bool
    {
        $key = array_search($groupId, $this->groupAttendees, true);

        if ($key === false) {
            return false;
        }

        unset($this->groupAttendees[$key]);
        $this->groupAttendees = array_values($this->groupAttendees);
        return true;
    }

    /**
     * Check if a group ID is in the group attendees list
     *
     * @param int $groupId
     * @return bool
     */
    public function hasGroupAttendee(int $groupId): bool
    {
        return in_array($groupId, $this->groupAttendees, true);
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

    /**
     * {@inheritdoc}
     */
    public function getUpdated(): string
    {
        return $this->updated;
    }
}