<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\MemberChangeTracker;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Exception;
use function add_action;
use function do_action;
use function get_post;
use function get_post_type;
use function wp_update_post;
use const WP_DEBUG;

/**
 * Class TsmlMemberChangeTracker
 *
 * Tracks changes to members via ACF and fires the unity/member_changing hook
 * when actual changes are detected, or unity/member_created when a member is
 * being saved for the first time via the admin form.
 */
class TsmlMemberChangeTracker implements MemberChangeTracker
{
    private static ?Member $originalMember = null;

    /**
     * Whether the current request represents the first persistence of a
     * brand-new admin-created member.
     *
     * Captured by the transition_post_status hook, not inside
     * acf/save_post. The reason: WordPress's admin "Add New" form
     * submission goes through wp_insert_post, which updates the post
     * row to its new status BEFORE firing save_post (and therefore
     * before ACF fires acf/save_post). By the time captureOriginalMember
     * runs, the post status is already `publish`/`draft` and we can no
     * longer tell from the row alone that this was a creation.
     *
     * transition_post_status fires earlier in the same wp_insert_post
     * call, while the previous status is still observable. We catch
     * the auto-draft → publish/draft/private/pending transition there
     * and let the flag survive the few microseconds until checkForChanges
     * consumes it.
     *
     * @var array<int, true> Map of post_id to true for ids flagged as creations
     */
    private static array $newMemberIds = [];

    private MemberRepository $repository;

    /**
     * Constructor
     *
     * @param MemberRepository $repository Repository for accessing members
     */
    public function __construct(MemberRepository $repository)
    {
        $this->repository = $repository;

        // Capture creations at the moment the post leaves the auto-draft
        // status, BEFORE save_post fires. See onPostStatusTransition for
        // why this can't be detected from inside acf/save_post.
        add_action('transition_post_status', [$this, 'onPostStatusTransition'], 10, 3);

        add_action('acf/save_post', [$this, 'captureOriginalMember'], 1);
        add_action('acf/save_post', [$this, 'checkForChanges'], 20);
        add_action('before_delete_post', [$this, 'onMemberDeleted'], 10, 1);
        add_action('wp_trash_post', [$this, 'onMemberDeleted'], 10, 1);
    }

    /**
     * Flag a member post as a creation when it transitions out of
     * auto-draft into a real status.
     *
     * Fires earlier in the wp_insert_post lifecycle than save_post —
     * specifically before WordPress has finished writing the new row
     * — so it still has access to the previous status. This is the
     * one safe moment to distinguish "first save of an Add-New form"
     * from "edit of an existing member": the post row's stored status
     * has already changed by the time acf/save_post runs at priority 1.
     *
     * Status changes between live statuses (publish ↔ draft ↔ pending)
     * are not creations. Transitions into auto-draft itself are the
     * pre-form scaffolding WP does on /post-new.php and are also
     * ignored — no fields will be written for those.
     *
     * @param string  $newStatus The post's status after the transition
     * @param string  $oldStatus The post's status before the transition
     * @param \WP_Post $post     The post object being transitioned
     * @return void
     */
    public function onPostStatusTransition(string $newStatus, string $oldStatus, $post): void
    {
        if (!is_object($post) || ($post->post_type ?? '') !== TsmlMemberFields::POST_TYPE) {
            return;
        }

        if ($oldStatus === 'auto-draft' && $newStatus !== 'auto-draft') {
            self::$newMemberIds[(int) $post->ID] = true;
        }
    }

    /**
     * Capture the original member before ACF makes changes.
     *
     * The "is this a creation?" decision is taken earlier — in
     * onPostStatusTransition — and stored in self::$newMemberIds. Here
     * we just snapshot whatever state the repository reports so a
     * later diff in checkForChanges has something to compare against.
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function captureOriginalMember(int $postId): void
    {
        if (get_post_type($postId) !== TsmlMemberFields::POST_TYPE) {
            return;
        }

        try {
            self::$originalMember = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Original member captured for post ID: ' . $postId);
            }

            do_action('unity/member_before_save', $postId, self::$originalMember);
        } catch (Exception $e) {
            \TsmlForUnity\Plugin::logError('Error capturing original member: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Check for changes after ACF has saved all fields
     *
     * Dispatches one of two hooks depending on whether
     * onPostStatusTransition flagged this post as a creation:
     *   - unity/member_created   for the first save (no diffing — every
     *                            field on the member is "new")
     *   - unity/member_changing  for subsequent edits, only when at least
     *                            one tracked field actually changed
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function checkForChanges(int $postId): void
    {
        if (get_post_type($postId) !== TsmlMemberFields::POST_TYPE) {
            return;
        }

        $isNewMember = isset(self::$newMemberIds[$postId]);
        unset(self::$newMemberIds[$postId]);

        if (!self::$originalMember) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('No original member captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedMember = $this->repository->findById($postId);

            if (!$updatedMember) {
                \TsmlForUnity\Plugin::logError('Could not fetch updated member for post ID: ' . $postId);
                self::$originalMember = null;
                return;
            }

            // Sync post_title to the (encoded) anonymous name. Needed for both
            // creation and update — a newly created post has no meaningful
            // title and an edit may have changed the name — so it sits
            // outside the create/update branching below.
            $post = get_post($postId);
            $encodedName = htmlspecialchars($updatedMember->getAnonymousName(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($post && $post->post_title !== $encodedName) {
                wp_update_post([
                    'ID' => $postId,
                    'post_title' => $encodedName
                ]);
            }

            if ($isNewMember) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('New member detected for post ID: ' . $postId . ', firing unity/member_created hook');
                }

                do_action('unity/member_created', $updatedMember);
            } elseif ($this->hasMemberChanged(self::$originalMember, $updatedMember)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('Changes detected in member ID: ' . $postId . ', firing unity/member_changing hook');
                }

                do_action('unity/member_changing', $updatedMember, self::$originalMember);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('No changes detected in member ID: ' . $postId);
                }
            }

            do_action('unity/member_changed', $postId, $updatedMember, self::$originalMember);

            self::$originalMember = null;
        } catch (Exception $e) {
            self::$originalMember = null;
            \TsmlForUnity\Plugin::logError('Error checking for member changes: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle member deletion (trash or permanent delete)
     *
     * Captures the member before it is removed and fires the
     * unity/member_deleted hook so that listeners can react.
     *
     * @param int $postId The post ID being deleted or trashed
     * @return void
     */
    public function onMemberDeleted(int $postId): void
    {
        if (get_post_type($postId) !== TsmlMemberFields::POST_TYPE) {
            return;
        }

        try {
            $member = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Member deleted, firing unity/member_deleted hook for post ID: ' . $postId);
            }

            do_action('unity/member_deleted', $postId, $member);
        } catch (Exception $e) {
            // Member may already be partially removed; fire with null so
            // listeners can still react to the deletion itself.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Error fetching member during deletion: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }

            do_action('unity/member_deleted', $postId, null);
        }
    }

    /**
     * Check if a member has changed by comparing its properties
     *
     * @param Member $originalMember The original member before changes
     * @param Member $updatedMember The updated member after changes
     * @return bool True if the member has changed, false otherwise
     */
    private function hasMemberChanged(Member $originalMember, Member $updatedMember): bool
    {
        if ($originalMember->getAnonymousName() !== $updatedMember->getAnonymousName()) {
            return true;
        }

        if ($originalMember->getPersonalEmail() !== $updatedMember->getPersonalEmail()) {
            return true;
        }

        if ($originalMember->showAnonymousName() !== $updatedMember->showAnonymousName()) {
            return true;
        }

        if ($originalMember->showMemberProfile() !== $updatedMember->showMemberProfile()) {
            return true;
        }

        if ($originalMember->getAnonymousProfile() !== $updatedMember->getAnonymousProfile()) {
            return true;
        }

        if ($originalMember->getIntergroupPosition() !== $updatedMember->getIntergroupPosition()) {
            return true;
        }

        if ($originalMember->getIntergroupPositionRotation() !== $updatedMember->getIntergroupPositionRotation()) {
            return true;
        }

        if ($originalMember->getHomeGroup() !== $updatedMember->getHomeGroup()) {
            return true;
        }

        if ($originalMember->isGSR() !== $updatedMember->isGSR()) {
            return true;
        }

        if ($originalMember->getMeetingPO() !== $updatedMember->getMeetingPO()) {
            return true;
        }

        if ($originalMember->getMobileNumber() !== $updatedMember->getMobileNumber()) {
            return true;
        }

        if ($originalMember->isTwelfthStepper() !== $updatedMember->isTwelfthStepper()) {
            return true;
        }

        if ($originalMember->getArea() !== $updatedMember->getArea()) {
            return true;
        }

        // Accepts is an unordered checkbox selection. Sort before
        // comparing so a reordered-but-equal selection doesn't fire
        // a spurious change event.
        $originalAccepts = $originalMember->getAccepts();
        $updatedAccepts = $updatedMember->getAccepts();
        sort($originalAccepts);
        sort($updatedAccepts);
        if ($originalAccepts !== $updatedAccepts) {
            return true;
        }

        if ($originalMember->isGdprAccepted() !== $updatedMember->isGdprAccepted()) {
            return true;
        }

        if ($originalMember->getUpdated() !== $updatedMember->getUpdated()) {
            return true;
        }

        return false;
    }
}