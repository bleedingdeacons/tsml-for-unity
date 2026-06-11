<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use function do_action;
use function get_post;
use function get_posts;
use function is_wp_error;
use function update_field;
use function wp_delete_post;
use function wp_insert_post;
use function wp_update_post;

/**
 * TSML Member Repository
 */
class TsmlMemberRepository implements MemberRepository
{
    private MemberFactory $memberFactory;

    /**
     * TsmlMemberRepository constructor
     *
     * @param MemberFactory $memberFactory
     */
    public function __construct(MemberFactory $memberFactory)
    {
        $this->memberFactory = $memberFactory;
    }

    /**
     * Find a member by ID
     *
     * @param int $id
     * @return Member|null
     */
    public function findById(int $id): ?Member
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TsmlMemberFields::POST_TYPE) {
            return null;
        }

        return $this->memberFactory->createFromSource($id);
    }

    /**
     * Find all members with optional filtering
     *
     * @param array $args Optional get_posts arguments
     * @return array Array of Member objects
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish'
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);
        $members = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $member = $this->findById($post->ID);
                if ($member) {
                    $members[] = $member;
                }
            }
        }

        return $members;
    }

    /**
     * Find all members flagged as telephone responders
     *
     * Runs a single get_posts query filtered by a meta_query on the
     * telephone-responder ACF field, so the database does the selection
     * rather than loading every member and filtering in PHP. ACF stores
     * a true_false field as the string '1' when checked, so the clause
     * matches that stored value; members with the flag unset (stored
     * '0' or absent) are excluded.
     *
     * Asks for 'fields' => 'ids' and builds each Member straight from
     * the factory, avoiding the per-post get_post() round trip that
     * delegating to findAll() would incur. The post_type filter already
     * guarantees every returned id is a member, so the findById() type
     * guard is unnecessary here.
     *
     * @return array Array of Member objects
     */
    public function findTelephoneResponders(): array
    {
        $ids = get_posts([
            'post_type' => TsmlMemberFields::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => TsmlMemberFields::FIELD_TELEPHONE_RESPONDER,
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($ids)) {
            return [];
        }

        return array_map(
            fn (int $id): Member => $this->memberFactory->createFromSource($id),
            $ids
        );
    }

    /**
     * Find a member by personal email address
     *
     * Delegates to findAll() with a meta_query keyed on the personal
     * email ACF field. ACF stores field values as post meta under the
     * field name, so a meta_query on FIELD_PERSONAL_EMAIL matches the
     * stored value directly. Email addresses are expected to be unique
     * across members; if more than one match exists, the first is
     * returned.
     *
     * Returns null for empty input rather than running an unbounded
     * query that would treat the empty string as a valid match.
     *
     * @param string $email Email address to search for
     * @return Member|null The matching member, or null if none found
     */
    public function findByEmail(string $email): ?Member
    {
        if ($email === '') {
            return null;
        }

        $members = $this->findAll([
            'numberposts' => 1,
            'meta_query' => [
                [
                    'key' => TsmlMemberFields::FIELD_PERSONAL_EMAIL,
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ]);

        return $members[0] ?? null;
    }

    /**
     * Get total count of members matching criteria
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public function count(array $args = []): int
    {
        $defaultArgs = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);

        return is_array($posts) ? count($posts) : 0;
    }

    /**
     * Save member data (insert or update)
     *
     * For inserts, fires unity/member_created after fields are written.
     * For updates, delegates to update() which fires unity/member_changing.
     *
     * Both events let listeners (notably Scrutiny's audit tracker) react
     * to programmatic writes via the repository — including from the
     * Integrity REST API, WP-CLI, cron, or any caller that doesn't go
     * through ACF's form-save lifecycle. The admin edit form has its
     * own path via acf/save_post and does not reach the repository.
     *
     * @param Member $member
     * @return bool
     */
    public function save(Member $member): bool
    {
        $postId = $member->getId();

        if ($postId > 0) {
            return $this->update($member);
        }

        $encodedName = htmlspecialchars($member->getAnonymousName(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $encodedName,
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $postId = $result;

        $this->updateFields($member, $postId);

        // Re-read so listeners see the persisted state — including any
        // values that acf/update_value filters may have transformed.
        $createdMember = $this->findById($postId);

        if ($createdMember !== null) {
            do_action('unity/member_created', $createdMember);
        }

        return true;
    }

    /**
     * Insert a new member record with just an anonymous name
     *
     * Owns the wp_insert_post call so callers don't have to pre-create
     * the post and pass its ID in. After insertion the persisted member
     * is re-read and unity/member_created is fired so audit listeners
     * pick up the event.
     *
     * Intended for the two-phase create-then-fill flow used by the
     * admin form and the Integrity REST API: insert the post here to
     * get an ID, then build a fully populated Member around that ID and
     * pass it to save() to persist the rest of the fields.
     *
     * @param string $anonymousName The anonymous name for the new member
     * @return int The new post ID, or 0 if insertion failed
     */
    public function create(string $anonymousName): int
    {
        $encodedName = htmlspecialchars($anonymousName, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $encodedName,
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return 0;
        }

        $postId = (int) $result;

        // Persist the anonymous name into ACF too, so the field-backed
        // value matches the post_title from the very first read.
        update_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $anonymousName, $postId);

        // Re-read so listeners see the persisted state — including any
        // values that acf/update_value filters may have transformed.
        $createdMember = $this->findById($postId);

        if ($createdMember !== null) {
            do_action('unity/member_created', $createdMember);
        }

        return $postId;
    }

    /**
     * Update an existing member
     *
     * Captures the original member before writing, then re-reads after
     * writing and fires unity/member_changing if the two differ in any
     * field the change tracker cares about. The re-read step is what
     * makes this honest in the face of acf/update_value filters that
     * may transform or reject values (e.g. MemberFieldsObscurer): the
     * event reflects what actually landed in the database, not what
     * the caller asked for.
     *
     * @param Member $member
     * @return bool
     */
    public function update(Member $member): bool
    {
        $postId = $member->getId();

        if ($postId <= 0) {
            return false;
        }

        // Snapshot the original state before any writes. Captured here
        // rather than later because update_field() calls below would
        // otherwise blur the before/after boundary if findById() were
        // deferred.
        $originalMember = $this->findById($postId);

        $encodedName = htmlspecialchars($member->getAnonymousName(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'ID' => $postId,
            'post_title' => $encodedName,
            'post_type' => TsmlMemberFields::POST_TYPE,
            'post_status' => 'publish',
        ];

        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $this->updateFields($member, $postId);

        // Re-read after all writes so the event reflects persisted
        // state, including values that filters may have rewritten.
        $updatedMember = $this->findById($postId);

        if ($originalMember !== null && $updatedMember !== null) {
            do_action('unity/member_changing', $updatedMember, $originalMember);
        }

        return true;
    }

    /**
     * Update ACF fields for a member
     *
     * @param Member $member
     * @param int $postId
     * @return void
     */
    private function updateFields(Member $member, int $postId): void
    {
        update_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $member->getAnonymousName(), $postId);
        update_field(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $member->showAnonymousName(), $postId);
        update_field(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $member->showMemberProfile(), $postId);
        update_field(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $member->getAnonymousProfile(), $postId);
        update_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION, $member->getIntergroupPosition(), $postId);
        update_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $member->getIntergroupPositionRotation(), $postId);
        update_field(TsmlMemberFields::FIELD_HOME_GROUP, $member->getHomeGroup(), $postId);
        update_field(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $member->isGSR(), $postId);
        update_field(TsmlMemberFields::FIELD_MEETING_PO, $member->getMeetingPO(), $postId);
        update_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $member->getPersonalEmail(), $postId);
        update_field(TsmlMemberFields::FIELD_MOBILE_NUMBER, $member->getMobileNumber(), $postId);
        update_field(TsmlMemberFields::FIELD_TWELFTH_STEPPER, $member->isTwelfthStepper(), $postId);
        update_field(TsmlMemberFields::FIELD_TELEPHONE_RESPONDER, $member->isTelephoneResponder(), $postId);
        update_field(TsmlMemberFields::FIELD_AREA, $member->getArea(), $postId);
        // ACF stores checkbox fields as an array of selected option
        // values, so pass the list straight through.
        update_field(TsmlMemberFields::FIELD_ACCEPTS, $member->getAccepts(), $postId);

        // GDPR compliance fields. ACF stores the date_time_picker value
        // internally as Y-m-d H:i:s regardless of the field's return_format,
        // so the domain value (already normalised to Y-m-d H:i:s) can be
        // written back as-is.
        update_field(TsmlMemberFields::FIELD_GDPR_ACCEPTED, $member->isGdprAccepted(), $postId);
        update_field(TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT, $member->getGdprAcceptedAt(), $postId);
        update_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION, $member->getGdprAcceptanceVersion(), $postId);
        update_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD, $member->getGdprAcceptanceMethod(), $postId);
        update_field(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT, $member->getGdprAcceptanceStatement(), $postId);
    }

    /**
     * Delete a member
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return (bool) wp_delete_post($id, true);
    }
}