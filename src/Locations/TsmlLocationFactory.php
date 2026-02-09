<?php

declare(strict_types=1);

namespace TsmlForUnity\Locations;

use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\Location;

/**
 * Factory class for creating Location objects from TSML data
 *
 * This factory creates Location objects from the 12 Step Meeting List plugin's
 * tsml_location custom post type.
 */
class TsmlLocationFactory implements LocationFactory
{
    /**
     * Create a location from a WordPress post ID
     *
     * @param int $sourceId The WordPress post ID
     * @return Location|null The created location or null if not found/invalid
     */
    public function createFromSource(int $sourceId): ?Location
    {
        if (!function_exists('get_post')) {
            $this->logError('Required WordPress function get_post is not available');
            return null;
        }

        $post = get_post($sourceId);

        if (!$post || $post->post_type !== TsmlLocationFields::POST_TYPE) {
            return null;
        }

        $meta = $this->getPostMeta($sourceId);
        $link = $this->getPermalink($sourceId);
        $region = $this->getRegion($sourceId);
        $meetingIds = $this->getMeetingIdsForLocation($sourceId);

        return new TsmlLocation(
            id: $sourceId,
            name: $post->post_title ?? '',
            address: $this->getMetaField($meta, TsmlLocationFields::ADDRESS, ''),
            city: $this->getMetaField($meta, TsmlLocationFields::CITY, ''),
            state: $this->getMetaField($meta, TsmlLocationFields::STATE, ''),
            postalCode: $this->getMetaField($meta, TsmlLocationFields::POSTAL_CODE, ''),
            country: $this->getMetaField($meta, TsmlLocationFields::COUNTRY, ''),
            region: $region,
            notes: $this->getMetaField($meta, TsmlLocationFields::NOTES, ''),
            link: $link,
            latitude: $this->parseFloat($this->getMetaField($meta, TsmlLocationFields::LATITUDE, null)),
            longitude: $this->parseFloat($this->getMetaField($meta, TsmlLocationFields::LONGITUDE, null)),
            timezone: $this->getMetaField($meta, TsmlLocationFields::TIMEZONE, ''),
            meetingIds: $meetingIds
        );
    }

    /**
     * Get post meta for a post ID
     *
     * @param int $postId The post ID
     * @return array Post meta data
     */
    private function getPostMeta(int $postId): array
    {
        if (!function_exists('get_post_custom')) {
            return [];
        }

        $meta = get_post_custom($postId);
        return is_array($meta) ? $meta : [];
    }

    /**
     * Get a meta field value with a default
     *
     * @param array  $meta    Meta data array
     * @param string $field   Field name
     * @param mixed  $default Default value if field not found
     * @return mixed Field value or default
     */
    private function getMetaField(array $meta, string $field, mixed $default = ''): mixed
    {
        if (!isset($meta[$field]) || !is_array($meta[$field]) || empty($meta[$field])) {
            return $default;
        }

        $value = $meta[$field][0] ?? $default;

        // Handle serialized data
        if (function_exists('maybe_unserialize')) {
            $value = maybe_unserialize($value);
        }

        return $value;
    }

    /**
     * Get the region name from taxonomy
     *
     * @param int $postId The post ID
     * @return string Region name or empty string
     */
    private function getRegion(int $postId): string
    {
        if (!function_exists('wp_get_post_terms')) {
            return '';
        }

        $terms = wp_get_post_terms($postId, TsmlLocationFields::REGION_TAXONOMY, ['fields' => 'names']);

        if (is_array($terms) && !empty($terms)) {
            return (string) $terms[0];
        }

        return '';
    }

    /**
     * Get meeting IDs associated with this location
     *
     * In TSML, meetings reference their location via the location_id post_meta field.
     *
     * @param int $locationId The location post ID
     * @return array Array of meeting post IDs
     */
    private function getMeetingIdsForLocation(int $locationId): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }

        $meetings = get_posts([
            'post_type' => 'tsml_meeting',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => TsmlLocationFields::LOCATION_ID_META_KEY,
                    'value' => $locationId,
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);

        return is_array($meetings) ? $meetings : [];
    }

    /**
     * Get the permalink for a post
     *
     * @param int $postId The post ID
     * @return string The permalink or empty string
     */
    private function getPermalink(int $postId): string
    {
        if (!function_exists('get_permalink')) {
            return '';
        }

        $permalink = get_permalink($postId);
        return is_string($permalink) ? $permalink : '';
    }

    /**
     * Parse a value as float, returning null if invalid
     *
     * @param mixed $value The value to parse
     * @return float|null The parsed float or null
     */
    private function parseFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Log an error message
     *
     * @param string $message The error message
     * @param array  $context Additional context
     */
    private function logError(string $message, array $context = []): void
    {
        if (!isset($context['class'])) {
            $context['class'] = __CLASS__;
        }

        $contextStr = empty($context) ? '' : ' ' . json_encode($context);

        if (function_exists('error_log')) {
            error_log("[TSML Location Factory Error] {$message}{$contextStr}");
        }
    }
}
