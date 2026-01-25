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

    public const FIELD_ATTENDEES = 'intergroup-meeting_attendees';
    public const FIELD_DATE = 'intergroup-meeting_date';

    private function __construct()
    {
    }
}