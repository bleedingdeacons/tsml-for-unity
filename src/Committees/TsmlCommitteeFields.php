<?php

declare(strict_types=1);

namespace TsmlForUnity\Committees;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Constants for TSML Committee
 *
 * The taxonomy and the ACF fields that write into it. Both are registered
 * through the ACF admin UI and stored in the database, like every other post
 * type and field group in this suite -- nothing here calls register_taxonomy().
 *
 * The FIELD_* entries are recorded for consumers that need to reach the ACF
 * field itself (a conditional-logic rule, an importer, a field-key lookup).
 * Nothing in this plugin reads them: both fields are configured with Save
 * Terms and Load Terms on, so ACF writes real rows into wp_term_relationships
 * and the term relationships -- not the meta copy ACF keeps alongside them --
 * are the source of truth. TsmlCommitteeRepository goes to the term APIs.
 */
final class TsmlCommitteeFields
{
    public const TAXONOMY = 'intergroup-committee';

    /**
     * The member's Committees field.
     *
     * Prefixed because it sits inside the "Service" ACF Group field, which
     * composes sub-field meta keys as `<group>_<field>`.
     */
    public const FIELD_MEMBER_COMMITTEES = 'service-layout-group_member-committees';

    /** The position's Committees field, at the top level of its field group. */
    public const FIELD_POSITION_COMMITTEES = 'position-committees';

    public const KEY_MEMBER_COMMITTEES = 'field_6a9470af5a1b9';
    public const KEY_POSITION_COMMITTEES = 'field_6a9473447f08e';

    private function __construct()
    {
    }
}
