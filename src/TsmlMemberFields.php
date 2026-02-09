<?php

declare(strict_types=1);

namespace TsmlForUnity;

/**
 * Field Constants for TSML Member
 *
 * Contains all field constants used for member data
 */
final class TsmlMemberFields
{
    public const POST_TYPE = 'intergroup-member';
    
    public const FIELD_ANONYMOUS_NAME = 'about-layout-group_anonymous-name';
    public const FIELD_MOBILE_PHONE = 'about-layout-group_mobile-phone';
    public const FIELD_SHOW_ANONYMOUS_NAME = 'about-layout-group_show-anonymous-name';
    public const FIELD_SHOW_MEMBER_PROFILE = 'about-layout-group_show-member-profile';
    public const FIELD_ANONYMOUS_PROFILE = 'about-layout-group_anonymous-profile';
    public const FIELD_INTERGROUP_POSITION = 'service-layout-group_intergroup-position';
    public const FIELD_INTERGROUP_POSITION_ROTATION = 'service-layout-group_intergroup-position-rotation';
    public const FIELD_HOME_GROUP = 'home-layout-group_home-group';
    public const FIELD_HOMEGROUP_GSR = 'home-layout-group_homegroup-gsr';
    public const FIELD_MEETING_PO = 'home-layout-group_meeting_po';
    public const FIELD_PERSONAL_EMAIL = 'about-layout-group_personal-email';
    public const FIELD_MOBILE_NUMBER = 'about-layout-group_mobile-number';

    private function __construct()
    {
    }
}
