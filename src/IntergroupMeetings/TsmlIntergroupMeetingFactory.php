<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use function get_field;
use function get_post;
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
        // Read the meeting title from the ACF field. Fall back to the
        // WordPress post title when the ACF field is empty — this covers
        // legacy posts created before the meeting_title field was added.
        $meetingTitle = get_field(TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE, $id);
        $title = is_string($meetingTitle) && $meetingTitle !== '' ? $meetingTitle : '';
        if ($title === '') {
            $postTitle = get_the_title($id);
            $title = is_string($postTitle) ? $postTitle : '';
        }

        // Use get_field() for ACF relationship fields so the values are
        // consistent with what the ACF admin UI reads and writes.
        // A relationship field returns WP_Post objects or post IDs depending
        // on the field's "Return Format" setting; parsePostIds() handles both.
        //
        // When the post lacks ACF shadow meta (e.g. created via the API),
        // get_field() by name returns false. Fall back to reading by field
        // key, which bypasses the shadow meta lookup entirely.
        $attendeesRaw = $this->getFieldWithKeyFallback(
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES,
            $id
        );
        $attendees = $this->parsePostIds($attendeesRaw);

        $officersRaw = $this->getFieldWithKeyFallback(
            TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS,
            $id
        );
        $officers = $this->parsePostIds($officersRaw);

        // ACF date_picker returns d/m/Y (per the field's return_format).
        // Normalise to Y-m-d so the value is safe for strcmp sorting,
        // strtotime parsing, and comparison with WordPress date functions.
        $dateField = get_field(TsmlIntergroupMeetingFields::FIELD_DATE, $id);
        $rawDate = is_string($dateField) ? $dateField : '';
        $date = '';
        if ($rawDate !== '') {
            $parsed = \DateTime::createFromFormat('d/m/Y', $rawDate);
            $date = $parsed ? $parsed->format('Y-m-d') : $rawDate;
        }

        $post = get_post($id);
        // Use post_modified_gmt (UTC) rather than post_modified (site-local
        // timezone) so the REST API's formatUpdatedTimestamp is accurate.
        $updated = ($post && isset($post->post_modified_gmt)) ? $post->post_modified_gmt : '';

        return new TsmlIntergroupMeeting(
            $id,
            $title,
            $attendees,
            $officers,
            $date,
            $updated
        );
    }

    /**
     * Read an ACF field by name, falling back to the field key if the
     * name-based lookup returns nothing.
     *
     * This handles posts that lack ACF shadow meta rows — typically
     * posts created via the REST API rather than the ACF admin UI.
     * When get_field('attending_groups', $postId) fails because the
     * shadow meta row (_attending_groups → field_xxx) is missing,
     * we retry with the resolved field key, which bypasses the
     * shadow meta lookup entirely.
     *
     * @param string $fieldName The ACF field name
     * @param int    $postId    The WordPress post ID
     * @return mixed The field value, or false/null if not found
     */
    private function getFieldWithKeyFallback(string $fieldName, int $postId): mixed
    {
        $value = get_field($fieldName, $postId);

        if ($value !== false && $value !== null) {
            return $value;
        }

        // Name-based lookup failed — try by field key
        $key = AcfFieldKeyResolver::getKey($fieldName);

        if ($key !== null) {
            return get_field($key, $postId);
        }

        return $value;
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