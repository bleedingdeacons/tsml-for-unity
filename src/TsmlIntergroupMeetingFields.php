<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Field Constants for TSML Intergroup Meeting
 *
 * Contains all field constants used for intergroup meeting data
 */
final class TsmlIntergroupMeetingFields
{
    public const INTERGROUP_MEETING_POST_TYPE = 'intergroup-meeting';

    public const FIELD_ATTENDEES = 'attending_groups';
    public const FIELD_ATTENDING_OFFICERS = 'attending_officers';
    public const FIELD_DATE = 'intergroup-meeting_date';

    private function __construct()
    {
    }
}