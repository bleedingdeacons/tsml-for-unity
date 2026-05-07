<?php

declare(strict_types=1);

namespace TsmlForUnity\PrivacyPolicies;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Constants for TSML Privacy Policy
 *
 * Mirrors the ACF export for the "Gdpr" field group attached to the
 * `privacy-policy` post type (see acf-export). Names are the ACF field
 * `name` values (used with get_field/update_field), keys are the ACF
 * `key` values (used when registering field-key mappings).
 */
final class TsmlPrivacyPolicyFields
{
    public const POST_TYPE = 'privacy-policy';

    public const FIELD_POLICY = 'gdpr-policy';
    public const FIELD_VERSION = 'gdpr-policy-version';
    public const FIELD_ACTIVE = 'gdpr-policy-active';

    public const KEY_POLICY = 'field_69f69e9b3a0dd';
    public const KEY_VERSION = 'field_69f69e793a0dc';
    public const KEY_ACTIVE = 'field_69f69f6807c75';

    public const GROUP_KEY = 'group_69f69e782fe07';

    private function __construct()
    {
    }
}
