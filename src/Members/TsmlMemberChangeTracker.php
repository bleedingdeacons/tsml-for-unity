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
 * when actual changes are detected.
 */
class TsmlMemberChangeTracker implements MemberChangeTracker
{
    private static ?Member $originalMember = null;
    private MemberRepository $repository;

    /**
     * Constructor
     *
     * @param MemberRepository $repository Repository for accessing members
     */
    public function __construct(MemberRepository $repository)
    {
        $this->repository = $repository;

        add_action('acf/save_post', [$this, 'captureOriginalMember'], 1);
        add_action('acf/save_post', [$this, 'checkForChanges'], 20);
        add_action('before_delete_post', [$this, 'onMemberDeleted'], 10, 1);
        add_action('wp_trash_post', [$this, 'onMemberDeleted'], 10, 1);
    }

    /**
     * Capture the original member before ACF makes changes
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
            self::$originalMember = $this->repository->find($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Original member captured for post ID: ' . $postId);
            }

            do_action('unity/member_before_save', $postId, self::$originalMember);
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Error capturing original member: ' . $e->getMessage());
        }
    }

    /**
     * Check for changes after ACF has saved all fields
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function checkForChanges(int $postId): void
    {
        if (get_post_type($postId) !== TsmlMemberFields::POST_TYPE) {
            return;
        }

        if (!self::$originalMember) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('No original member captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedMember = $this->repository->find($postId);

            if (!$updatedMember) {
                error_log('Could not fetch updated member for post ID: ' . $postId);
                return;
            }

            if ($this->hasMemberChanged(self::$originalMember, $updatedMember)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Changes detected in member ID: ' . $postId . ', firing unity/member_changing hook');
                }

                $post = get_post($postId);
                if ($post && $post->post_title !== $updatedMember->getAnonymousName()) {
                    wp_update_post([
                        'ID' => $postId,
                        'post_title' => $updatedMember->getAnonymousName()
                    ]);
                }

                do_action('unity/member_changing', $updatedMember, self::$originalMember);

            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('No changes detected in member ID: ' . $postId);
                }
            }

            do_action('unity/member_changed', $postId, $updatedMember, self::$originalMember);

            self::$originalMember = null;
        } catch (Exception $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Error checking for member changes: ' . $e->getMessage());
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
            $member = $this->repository->find($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Member deleted, firing unity/member_deleted hook for post ID: ' . $postId);
            }

            do_action('unity/member_deleted', $postId, $member);
        } catch (Exception $e) {
            // Member may already be partially removed; fire with null so
            // listeners can still react to the deletion itself.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error fetching member during deletion: ' . $e->getMessage());
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

        if ($originalMember->getPersonalEmail() !== $updatedMember->getPersonalEmail()) {
            return true;
        }

        if ($originalMember->getMobileNumber() !== $updatedMember->getMobileNumber()) {
            return true;
        }

        if ($originalMember->getUpdated() !== $updatedMember->getUpdated()) {
            return true;
        }

        return false;
    }
}