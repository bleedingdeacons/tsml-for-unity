<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use function get_field;
use function get_the_title;

/**
 * TSML Intergroup Meeting Factory Implementation
 *
 * Factory class for creating IntergroupMeeting objects from TSML data.
 * This implementation uses TsmlIntergroupMeetingFields constants for field names.
 *
 * All ACF fields are read via get_field() so that relationship fields resolve
 * correctly regardless of the configured return format (post object or ID).
 */
class TsmlIntergroupMeetingFactory implements IntergroupMeetingFactory
{
    /**
     * Create a new IntergroupMeeting instance from a WordPress post ID
     *
     * @param int $id WordPress post ID
     * @return IntergroupMeeting
     */
    public function createFromSource(int $id): IntergroupMeeting
    {
        $title = get_the_title($id);
        $title = is_string($title) ? $title : '';

        // Use get_field() for ACF relationship fields so the values are
        // consistent with what the ACF admin UI reads and writes.
        // A relationship field returns WP_Post objects or post IDs depending
        // on the field's "Return Format" setting; parsePostIds() handles both.
        $attendeesRaw = get_field(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, $id);
        $attendees = $this->parsePostIds($attendeesRaw);

        $officersRaw = get_field(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, $id);
        $officers = $this->parsePostIds($officersRaw);

        $dateField = get_field(TsmlIntergroupMeetingFields::FIELD_DATE, $id);
        $date = is_string($dateField) ? $dateField : '';

        return new TsmlIntergroupMeeting(
            $id,
            $title,
            $attendees,
            $officers,
            $date
        );
    }

    /**
     * Parse a field into an array of post IDs
     *
     * Handles ACF relationship fields which may return:
     *   - An array of WP_Post objects (return format: "Post Object")
     *   - An array of integer IDs (return format: "Post ID")
     *   - false/null when the field is empty
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