<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves ACF field names to their field keys at activation time.
 *
 * ACF's update_field() and get_field() rely on a "shadow meta" row
 * (e.g. _attending_groups → field_xxx) to map a human-readable field
 * name to the internal field key. When a post is created via the REST
 * API rather than the ACF admin UI, this shadow row may not exist,
 * causing silent read/write failures.
 *
 * This resolver looks up field keys using acf_get_field() — which
 * queries ACF's registered field groups directly — and caches the
 * mapping in a WordPress option. The cached keys are then used by
 * repositories for reliable update_field() / get_field() calls
 * regardless of whether the shadow meta exists on a given post.
 *
 * Usage:
 *   - Call resolve() during plugin activation (after ACF has loaded).
 *   - Call getKey() at runtime to retrieve a cached key by field name.
 */
final class AcfFieldKeyResolver
{
    /**
     * WordPress option name for the cached field key mapping.
     */
    private const OPTION_NAME = 'tsml_unity_acf_field_keys';

    /**
     * The field names to resolve.
     *
     * Add any ACF field name here that is used with update_field()
     * or get_field() in a context where posts may lack shadow meta
     * (e.g. posts created via the API).
     */
    private const FIELD_NAMES = [
        TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE,
        TsmlIntergroupMeetingFields::FIELD_ATTENDEES,
        TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS,
        TsmlIntergroupMeetingFields::FIELD_DATE,
    ];

    /**
     * Prevent instantiation — all methods are static.
     */
    private function __construct()
    {
    }

    /**
     * Resolve all configured field names to their ACF keys and cache
     * the mapping in a WordPress option.
     *
     * Should be called during plugin activation, after ACF is loaded.
     * If ACF is not available, this is a no-op and any previously
     * cached mapping is preserved.
     *
     * @return array<string, string> The resolved mapping (name → key),
     *                               empty if ACF is unavailable.
     */
    public static function resolve(): array
    {
        if (!function_exists('acf_get_field')) {
            return [];
        }

        $mapping = [];

        foreach (self::FIELD_NAMES as $fieldName) {
            $field = acf_get_field($fieldName);

            if (is_array($field) && !empty($field['key'])) {
                $mapping[$fieldName] = $field['key'];
            }
        }

        if (!empty($mapping)) {
            update_option(self::OPTION_NAME, $mapping, false);
        }

        return $mapping;
    }

    /**
     * Get the cached ACF field key for a given field name.
     *
     * Returns the resolved key if available, or falls back to the
     * hardcoded constant from TsmlIntergroupMeetingFields when the
     * cache is empty (e.g. first install before activation completes).
     *
     * @param string $fieldName The ACF field name (e.g. 'attending_groups')
     * @return string|null The field key, or null if not found
     */
    public static function getKey(string $fieldName): ?string
    {
        $mapping = get_option(self::OPTION_NAME, []);

        if (is_array($mapping) && isset($mapping[$fieldName])) {
            return $mapping[$fieldName];
        }

        // Fall back to hardcoded constants for backwards compatibility.
        // This covers the window between a fresh install and the first
        // activation, or if the option was accidentally deleted.
        return self::getFallbackKey($fieldName);
    }

    /**
     * Check whether the cached mapping is populated.
     *
     * Useful for diagnostics and health checks.
     *
     * @return bool
     */
    public static function isCached(): bool
    {
        $mapping = get_option(self::OPTION_NAME, []);
        return is_array($mapping) && !empty($mapping);
    }

    /**
     * Clear the cached mapping.
     *
     * Useful during deactivation or when field groups are re-imported.
     */
    public static function clear(): void
    {
        delete_option(self::OPTION_NAME);
    }

    /**
     * Hardcoded fallback keys.
     *
     * These match the FIELD_KEY_ constants in TsmlIntergroupMeetingFields
     * and serve as a safety net when the option cache is unavailable.
     *
     * @param string $fieldName
     * @return string|null
     */
    private static function getFallbackKey(string $fieldName): ?string
    {
        $fallbacks = [
            TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE => TsmlIntergroupMeetingFields::FIELD_KEY_MEETING_TITLE,
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES => TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES,
            TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS => TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS,
            TsmlIntergroupMeetingFields::FIELD_DATE => TsmlIntergroupMeetingFields::FIELD_KEY_DATE,
        ];

        return $fallbacks[$fieldName] ?? null;
    }
}