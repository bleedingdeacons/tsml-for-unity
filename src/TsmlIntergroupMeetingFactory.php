<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactoryInterface;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingInterface;
use function get_field;

/**
 * TSML Intergroup Meeting Factory Implementation
 *
 * Factory class for creating IntergroupMeeting objects from TSML data.
 * This implementation uses TsmlIntergroupMeetingFields constants for field names.
 */
class TsmlIntergroupMeetingFactory implements IntergroupMeetingFactoryInterface
{
    /**
     * Create a new IntergroupMeeting instance from a WordPress post ID
     *
     * @param int $id WordPress post ID
     * @return IntergroupMeetingInterface
     */
    public function createFromSource(int $id): IntergroupMeetingInterface
    {
        $attendeesField = get_field(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, $id);
        $attendees = $this->parseAttendees($attendeesField);

        $dateField = get_field(TsmlIntergroupMeetingFields::FIELD_DATE, $id);
        $date = is_string($dateField) ? $dateField : '';

        return new TsmlIntergroupMeeting(
            $id,
            $attendees,
            $date
        );
    }

    /**
     * Parse the attendees field into an array of member IDs
     *
     * @param mixed $attendeesField The raw attendees field value from ACF
     * @return array<int> Array of member IDs
     */
    private function parseAttendees($attendeesField): array
    {
        $attendees = [];

        if (is_array($attendeesField)) {
            foreach ($attendeesField as $item) {
                if ($item instanceof \WP_Post) {
                    $attendees[] = $item->ID;
                } elseif (is_numeric($item)) {
                    $attendees[] = (int) $item;
                }
            }
        }

        return $attendees;
    }
}