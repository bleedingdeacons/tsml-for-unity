<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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

        // ACF date_picker returns d/m/Y (per the field's return_format).
        // Normalise to Y-m-d so the value is safe for DateTime parsing,
        // strcmp sorting, and comparison with WordPress date functions.
        $rawRotation = get_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $id) ?? '';
        $rotation = '';
        if ($rawRotation !== '') {
            $parsed = \DateTime::createFromFormat('d/m/Y', $rawRotation);
            $rotation = $parsed ? $parsed->format('Y-m-d') : $rawRotation;
        }

        // Use post_modified_gmt (UTC) rather than post_modified (site-local
        // timezone) so the REST API's formatUpdatedTimestamp is accurate.
        $post = get_post($id);
        $updated = ($post && isset($post->post_modified_gmt)) ? $post->post_modified_gmt : '';

        return new TsmlMember(
            $id,
            get_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $id) ?? '',
            (bool) (get_field(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $id) ?? false),
            (bool) (get_field(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $id) ?? false),
            get_field(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $id) ?? '',
            $intergroupPositionId,
            $rotation,
            $homeGroupId,
            (bool) (get_field(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $id) ?? false),
            get_field(TsmlMemberFields::FIELD_MEETING_PO, $id) ?? null,
            get_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $id) ?? '',
            get_field(TsmlMemberFields::FIELD_MOBILE_NUMBER, $id) ?? '',
            $updated
        );
    }

    /**
     * Create a new Member from raw field values
     *
     * Builds a TsmlMember directly from supplied data without reading
     * ACF fields from the database. Used by Reconcile and other importers
     * that already have the member data in hand.
     *
     * @param int    $id                          WordPress post ID
     * @param string $anonymousName               Anonymous name
     * @param bool   $showAnonymousName           Show anonymous name flag
     * @param bool   $showMemberProfile            Show profile flag
     * @param string $anonymousProfile             Profile text
     * @param int    $intergroupPosition           Position post ID
     * @param string $intergroupPositionRotation   Rotation date
     * @param int    $homeGroup                    Home group post ID
     * @param bool   $isGSR                        GSR flag
     * @param mixed  $meetingPO                    Meeting PO reference
     * @param string $personalEmail                Personal email
     * @param string $mobileNumber                 Mobile number
     * @param string $updated                      Last updated datetime
     * @return Member
     */
    public function createNew(
        int $id,
        string $anonymousName = '',
        bool $showAnonymousName = false,
        bool $showMemberProfile = false,
        string $anonymousProfile = '',
        int $intergroupPosition = 0,
        string $intergroupPositionRotation = '',
        int $homeGroup = 0,
        bool $isGSR = false,
        mixed $meetingPO = null,
        string $personalEmail = '',
        string $mobileNumber = '',
        string $updated = ''
    ): Member {
        return new TsmlMember(
            $id,
            $anonymousName,
            $showAnonymousName,
            $showMemberProfile,
            $anonymousProfile,
            $intergroupPosition,
            $intergroupPositionRotation,
            $homeGroup,
            $isGSR,
            $meetingPO,
            $personalEmail,
            $mobileNumber,
            $updated
        );
    }

}