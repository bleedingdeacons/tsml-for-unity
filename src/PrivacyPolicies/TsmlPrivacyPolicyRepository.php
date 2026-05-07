<?php

declare(strict_types=1);

namespace TsmlForUnity\PrivacyPolicies;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\PrivacyPolicies\Interfaces\PrivacyPolicy;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyRepository;
use function do_action;
use function get_post;
use function get_posts;
use function is_wp_error;
use function update_field;
use function wp_delete_post;
use function wp_insert_post;
use function wp_update_post;

/**
 * TSML Privacy Policy Repository
 *
 * Persistence for the `privacy-policy` post type and its "Gdpr" ACF
 * field group. Follows the same shape as TsmlMemberRepository:
 *
 *   - findById / findAll / count are read-only and trivially testable.
 *   - create() owns wp_insert_post for the two-phase admin flow.
 *   - save() routes to update() when the policy already has an ID.
 *   - update() snapshots the original, writes, re-reads, and fires
 *     unity/privacy_policy_changing so audit listeners see the
 *     persisted state (not the caller's intent).
 *   - findActive() encapsulates the "single active policy" query so
 *     callers don't reinvent the meta_query each time.
 */
class TsmlPrivacyPolicyRepository implements PrivacyPolicyRepository
{
    private PrivacyPolicyFactory $policyFactory;

    /**
     * Constructor
     *
     * @param PrivacyPolicyFactory $policyFactory
     */
    public function __construct(PrivacyPolicyFactory $policyFactory)
    {
        $this->policyFactory = $policyFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?PrivacyPolicy
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TsmlPrivacyPolicyFields::POST_TYPE) {
            return null;
        }

        return $this->policyFactory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     *
     * Queries posts where `gdpr-policy-active` is truthy. ACF stores
     * true_false fields as '1' / '0' strings in postmeta, so the
     * meta_query compares against '1' rather than a boolean.
     *
     * If multiple policies are flagged active (an invariant violation
     * the admin UI shouldn't allow but nothing in WP enforces), the
     * most recently modified one wins. Callers wanting to detect the
     * violation can compare findActive() against findAll() with the
     * same filter applied.
     */
    public function findActive(): ?PrivacyPolicy
    {
        $posts = get_posts([
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'numberposts' => 1,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => TsmlPrivacyPolicyFields::FIELD_ACTIVE,
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($posts)) {
            return null;
        }

        return $this->findById($posts[0]->ID);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish',
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);
        $policies = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $policy = $this->findById($post->ID);
                if ($policy) {
                    $policies[] = $policy;
                }
            }
        }

        return $policies;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $args = []): int
    {
        $defaultArgs = [
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);

        return is_array($posts) ? count($posts) : 0;
    }

    /**
     * {@inheritdoc}
     *
     * Fires unity/privacy_policy_created on insert (after the persisted
     * state is re-read) and delegates to update() for existing policies.
     * Both branches fire events so audit listeners react identically to
     * programmatic writes — including from REST, WP-CLI, or cron — without
     * needing to hook into ACF's form-save lifecycle.
     */
    public function save(PrivacyPolicy $policy): bool
    {
        $postId = $policy->getId();

        if ($postId > 0) {
            return $this->update($policy);
        }

        $encodedTitle = htmlspecialchars($policy->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $encodedTitle,
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $postId = (int) $result;

        $this->updateFields($policy, $postId);

        // Re-read so listeners see the persisted state, including any
        // values that acf/update_value filters may have transformed.
        $createdPolicy = $this->findById($postId);

        if ($createdPolicy !== null) {
            do_action('unity/privacy_policy_created', $createdPolicy);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Inserts a fresh post with just a title and persists no other
     * fields. Mirrors TsmlMemberRepository::create() — the caller is
     * expected to follow up with a save() carrying the full payload
     * once it has the new ID.
     */
    public function create(string $title): int
    {
        $encodedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $encodedTitle,
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return 0;
        }

        $postId = (int) $result;

        // Re-read so listeners see the persisted state.
        $createdPolicy = $this->findById($postId);

        if ($createdPolicy !== null) {
            do_action('unity/privacy_policy_created', $createdPolicy);
        }

        return $postId;
    }

    /**
     * {@inheritdoc}
     *
     * Snapshots the original before writing, then re-reads after writing
     * and fires unity/privacy_policy_changing if the row changed. The
     * re-read step is what makes the event honest in the face of
     * acf/update_value filters that may transform or reject values:
     * the event reflects what actually landed in the database, not
     * what the caller asked for.
     */
    public function update(PrivacyPolicy $policy): bool
    {
        $postId = $policy->getId();

        if ($postId <= 0) {
            return false;
        }

        // Snapshot before any writes — capturing this later would blur
        // the before/after boundary because update_field() calls below
        // change the underlying state.
        $originalPolicy = $this->findById($postId);

        $encodedTitle = htmlspecialchars($policy->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $postData = [
            'ID' => $postId,
            'post_title' => $encodedTitle,
            'post_type' => TsmlPrivacyPolicyFields::POST_TYPE,
            'post_status' => 'publish',
        ];

        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $this->updateFields($policy, $postId);

        // Re-read after all writes so the event reflects persisted state,
        // including values that filters may have rewritten.
        $updatedPolicy = $this->findById($postId);

        if ($originalPolicy !== null && $updatedPolicy !== null) {
            do_action('unity/privacy_policy_changing', $updatedPolicy, $originalPolicy);
        }

        return true;
    }

    /**
     * Update ACF fields for a privacy policy
     *
     * @param PrivacyPolicy $policy
     * @param int           $postId
     * @return void
     */
    private function updateFields(PrivacyPolicy $policy, int $postId): void
    {
        update_field(TsmlPrivacyPolicyFields::FIELD_POLICY, $policy->getPolicy(), $postId);
        update_field(TsmlPrivacyPolicyFields::FIELD_VERSION, $policy->getVersion(), $postId);
        update_field(TsmlPrivacyPolicyFields::FIELD_ACTIVE, $policy->isActive(), $postId);
    }

    /**
     * {@inheritdoc}
     *
     * Force-deletes (bypasses the trash) to match TsmlMemberRepository.
     */
    public function delete(int $id): bool
    {
        return (bool) wp_delete_post($id, true);
    }
}
