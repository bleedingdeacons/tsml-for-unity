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

        // ACF date_time_picker returns 'd/m/Y g:i a' for this field. Normalise
        // to ISO 8601 (Y-m-d H:i:s) for the same reasons as the rotation date,
        // so the GDPR acceptance timestamp can be safely sorted, compared,
        // and serialised by the REST API. Empty when never accepted.
        $rawGdprAcceptedAt = get_field(TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT, $id) ?? '';
        $gdprAcceptedAt = '';
        if ($rawGdprAcceptedAt !== '') {
            $parsedAt = \DateTime::createFromFormat('d/m/Y g:i a', $rawGdprAcceptedAt);
            $gdprAcceptedAt = $parsedAt ? $parsedAt->format('Y-m-d H:i:s') : $rawGdprAcceptedAt;
        }

        // Use post_modified_gmt (UTC) rather than post_modified (site-local
        // timezone) so the REST API's formatUpdatedTimestamp is accurate.
        $post = get_post($id);
        $updated = ($post && isset($post->post_modified_gmt)) ? $post->post_modified_gmt : '';

        return new TsmlMember(
            $id,
            html_entity_decode(get_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $id) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
            (bool) (get_field(TsmlMemberFields::FIELD_TWELFTH_STEPPER, $id) ?? false),
            (bool) (get_field(TsmlMemberFields::FIELD_TELEPHONE_RESPONDER, $id) ?? false),
            (string) (get_field(TsmlMemberFields::FIELD_AREA, $id) ?? ''),
            // ACF checkbox fields return array of selected option values,
            // or null/false when nothing is selected. Normalise to a plain
            // list of strings so callers get a consistent shape.
            array_values(array_map('strval', (array) (get_field(TsmlMemberFields::FIELD_ACCEPTS, $id) ?: []))),
            (bool) (get_field(TsmlMemberFields::FIELD_GDPR_ACCEPTED, $id) ?? false),
            $gdprAcceptedAt,
            (string) (get_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION, $id) ?? ''),
            (string) (get_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD, $id) ?? ''),
            (string) (get_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT, $id) ?? ''),
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
     * @param string             $personalEmail   Personal email
     * @param string             $mobileNumber    Mobile number
     * @param bool               $twelfthStepper  12th-step availability flag
     * @param bool               $telephoneResponder Telephone responder availability flag
     * @param string             $area            Geographic area covered for 12th-step calls
     * @param array<int, string> $accepts         Forms of contact accepted for 12th-step calls
     * @param bool               $gdprAccepted    GDPR acceptance flag
     * @param string $gdprAcceptedAt               GDPR acceptance timestamp (Y-m-d H:i:s)
     * @param string $gdprAcceptanceVersion        Privacy policy version accepted
     * @param string $gdprAcceptanceMethod         How acceptance was captured
     * @param string $gdprAcceptanceStatement      Exact statement that was accepted
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
        bool $twelfthStepper = false,
        bool $telephoneResponder = false,
        string $area = '',
        array $accepts = [],
        bool $gdprAccepted = false,
        string $gdprAcceptedAt = '',
        string $gdprAcceptanceVersion = '',
        string $gdprAcceptanceMethod = '',
        string $gdprAcceptanceStatement = '',
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
            $twelfthStepper,
            $telephoneResponder,
            $area,
            $accepts,
            $gdprAccepted,
            $gdprAcceptedAt,
            $gdprAcceptanceVersion,
            $gdprAcceptanceMethod,
            $gdprAcceptanceStatement,
            $updated
        );
    }

}