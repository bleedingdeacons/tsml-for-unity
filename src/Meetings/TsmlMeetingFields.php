<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

use TsmlForUnity\Groups\TsmlGroupFields;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Constants for TSML Member
 *
 * Contains all field constants used for member data
 */
final class TsmlMeetingFields
{
    public const POST_TYPE = 'tsml_meeting';

    public const GROUP_META_KEY = 'group_id';

    /**
     * The post type GROUP_META_KEY points at.
     *
     * Amber's meeting-list search joins the group post to search its
     * title, and reads this key out of the Meeting config. It was never
     * published, so the join was built with an empty post_type and could
     * not match a row -- group-name search returned nothing from the
     * join. It looked like it worked only because every meeting here is
     * titled after its group, so the meeting's own title matched first.
     */
    public const GROUP_POST_TYPE = TsmlGroupFields::POST_TYPE;

    private function __construct()
    {
    }
}
