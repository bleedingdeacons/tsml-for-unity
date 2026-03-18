<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Constants for TSML Intergroup Meeting
 *
 * Contains all field constants used for intergroup meeting data
 */
final class TsmlIntergroupMeetingFields
{
    public const POST_TYPE = 'intergroup-meeting';

    /** ACF field names (used with get_field) */
    public const FIELD_ATTENDEES = 'attending_groups';
    public const FIELD_ATTENDING_OFFICERS = 'attending_officers';
    public const FIELD_DATE = 'intergroup-meeting_date';

    /**
     * ACF field keys (used with update_field to guarantee writes succeed
     * even when the ACF shadow meta key does not yet exist on the post).
     *
     * When update_field() receives a field *name* it must look up the key
     * via the shadow meta row (e.g. _attending_groups → field_xxx). If
     * that shadow row is absent — because the post was created via the API
     * rather than the ACF admin UI — the lookup fails silently and no data
     * is written. Passing the key directly avoids this entirely.
     */
    public const FIELD_KEY_ATTENDEES = 'field_69760086d06fa';
    public const FIELD_KEY_ATTENDING_OFFICERS = 'field_6977908548a32';
    public const FIELD_KEY_DATE = 'field_6976915075f95';

    private function __construct()
    {
    }
}