<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
    public const FIELD_TWELFTH_STEPPER = 'home-layout-group_is-twelfth-stepper';
    public const FIELD_TELEPHONE_RESPONDER = 'service-layout-group_is-telephone-responder';
    public const FIELD_AREA = 'home-layout-group_member-area';
    public const FIELD_ACCEPTS = 'home-layout-group_member-accepts';
    public const FIELD_MEETING_PO = 'home-layout-group_meeting_po';
    public const FIELD_PERSONAL_EMAIL = 'about-layout-group_personal-email';
    public const FIELD_MOBILE_NUMBER = 'about-layout-group_mobile-number';

    public const FIELD_GDPR_ACCEPTED = 'gdpr-compliance-group_gdpr_accepted';
    public const FIELD_GDPR_ACCEPTED_AT = 'gdpr-compliance-group_gdpr_accepted_at';
    public const FIELD_GDPR_ACCEPTANCE_VERSION = 'gdpr-compliance-group_gdpr_acceptance_version';
    public const FIELD_GDPR_ACCEPTANCE_METHOD = 'gdpr-compliance-group_gdpr_acceptance_method';
    public const FIELD_GDPR_ACCEPTANCE_STATEMENT = 'gdpr-compliance-group_gdpr_acceptance_statement';

    public const KEY_PERSONAL_EMAIL = 'field_67d0eabc277cb';
    public const KEY_MOBILE_NUMBER = 'field_67d0eaea7cdea';

    public const KEY_TWELFTH_STEPPER = 'field_6a01f55ebbad1';
    public const KEY_TELEPHONE_RESPONDER = 'field_6a0b8f2fa3e88';
    public const KEY_AREA = 'field_6a01f07243661';
    public const KEY_ACCEPTS = 'field_6a01f2aff213e';

    public const KEY_GDPR_ACCEPTED = 'field_69efda5e524ed';
    public const KEY_GDPR_ACCEPTED_AT = 'field_69efda79524ee';
    public const KEY_GDPR_ACCEPTANCE_VERSION = 'field_69efdacd524ef';
    public const KEY_GDPR_ACCEPTANCE_METHOD = 'field_69efdaf0524f0';
    public const KEY_GDPR_ACCEPTANCE_STATEMENT = 'field_69efdb05524f1';

    private function __construct()
    {
    }
}