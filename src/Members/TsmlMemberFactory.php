<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\Interfaces\Member;
use function get_field;
use function get_the_title;

/**
 * TSML Member Factory Implementation
 *
 * Factory class for creating Member objects from TSML data.
 * This implementation uses TsmlMemberFields constants for field names.
 */
class TsmlMemberFactory implements MemberFactory
{
    /**
     * Create a new Member instance from a WordPress post ID
     *
     * @param int $id WordPress post ID
     * @return Member
     */
    public function createFromSource(int $id): Member
    {
        $homeGroupField = get_field(TsmlMemberFields::FIELD_HOME_GROUP, $id);
        $homeGroupId = 0;

        if ($homeGroupField instanceof \WP_Post) {
            // ACF post object field returns WP_Post object
            $homeGroupId = $homeGroupField->ID;
        } elseif (is_array($homeGroupField) && !empty($homeGroupField)) {
            // ACF relationship field returns array of post objects or IDs
            $firstItem = $homeGroupField[0];
            if ($firstItem instanceof \WP_Post) {
                $homeGroupId = $firstItem->ID;
            } elseif (is_numeric($firstItem)) {
                $homeGroupId = (int) $firstItem;
            }
        } elseif (is_numeric($homeGroupField)) {
            $homeGroupId = (int) $homeGroupField;
        }

        // Handle intergroup position field (ACF post object field)
        $intergroupPositionField = get_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION, $id);
        $intergroupPositionId = 0;

        if ($intergroupPositionField instanceof \WP_Post) {
            $intergroupPositionId = $intergroupPositionField->ID;
        } elseif (is_array($intergroupPositionField) && !empty($intergroupPositionField)) {
            $firstItem = $intergroupPositionField[0];
            if ($firstItem instanceof \WP_Post) {
                $intergroupPositionId = $firstItem->ID;
            } elseif (is_numeric($firstItem)) {
                $intergroupPositionId = (int) $firstItem;
            }
        } elseif (is_numeric($intergroupPositionField)) {
            $intergroupPositionId = (int) $intergroupPositionField;
        }

        return new TsmlMember(
            $id,
            get_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $id) ?? '',
            get_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $id) ?? '',
            (bool) (get_field(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $id) ?? false),
            (bool) (get_field(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $id) ?? false),
            get_field(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $id) ?? '',
            $intergroupPositionId,
            get_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $id) ?? '',
            $homeGroupId,
            (bool) (get_field(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $id) ?? false),
            get_field(TsmlMemberFields::FIELD_MEETING_PO, $id) ?? null,
            get_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $id) ?? '',
            get_field(TsmlMemberFields::FIELD_MOBILE_NUMBER, $id) ?? ''
        );
    }

    public function createFrom(int $id): Member
    {
        // TODO: Implement createFrom() method.
    }
}