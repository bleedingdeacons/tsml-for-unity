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
        $attendees = $this->parsePostIds($attendeesField);

        $officersField = get_field(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, $id);
        $officers = $this->parsePostIds($officersField);

        $dateField = get_field(TsmlIntergroupMeetingFields::FIELD_DATE, $id);
        $date = is_string($dateField) ? $dateField : '';

        return new TsmlIntergroupMeeting(
            $id,
            $attendees,
            $officers,
            $date
        );
    }

    /**
     * Parse a field into an array of post IDs
     *
     * @param mixed $field The raw field value from ACF
     * @return array<int> Array of post IDs
     */
    private function parsePostIds($field): array
    {
        $ids = [];

        if (is_array($field)) {
            foreach ($field as $item) {
                if ($item instanceof \WP_Post) {
                    $ids[] = $item->ID;
                } elseif (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }
        }

        return $ids;
    }
}