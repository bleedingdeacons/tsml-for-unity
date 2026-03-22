<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Core\Interfaces\ConfigurationInterface;
use Unity\Groups\Interfaces\GroupChangeTracker;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupRepository;
use Exception;
use function add_action;
use function do_action;
use function get_post;
use function get_post_type;
use function wp_update_post;
use const WP_DEBUG;

/**
 * Class TsmlGroupChangeTracker
 *
 * Tracks changes to groups via ACF and fires the unity/group_changing hook
 * when actual changes are detected.
 */
class TsmlGroupChangeTracker implements GroupChangeTracker
{
    private static ?Group $originalGroup = null;
    private GroupRepository $repository;

    /**
     * Constructor
     *
     * @param GroupRepository $repository Repository for accessing groups
     */
    public function __construct(GroupRepository $repository)
    {
        $this->repository = $repository;

        add_action('acf/save_post', [$this, 'captureOriginalGroup'], 1);
        add_action('acf/save_post', [$this, 'checkForChanges'], 20);
        add_action('before_delete_post', [$this, 'onGroupDeleted'], 10, 1);
        add_action('wp_trash_post', [$this, 'onGroupDeleted'], 10, 1);
        add_action('transition_post_status', [$this, 'onGroupHidden'], 10, 3);
    }

    /**
     * Capture the original group before ACF makes changes
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function captureOriginalGroup(int $postId): void
    {
        if (get_post_type($postId) !== TsmlGroupFields::POST_TYPE) {
            return;
        }

        try {
            self::$originalGroup = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Original group captured for post ID: ' . $postId);
            }

            do_action('group_before_save', $postId, self::$originalGroup);
        } catch (Exception $e) {
            \TsmlForUnity\Plugin::logError('Error capturing original group: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
        if (get_post_type($postId) !== TsmlGroupFields::POST_TYPE) {
            return;
        }

        if (!self::$originalGroup) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('No original group captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedGroup = $this->repository->findById($postId);

            if (!$updatedGroup) {
                \TsmlForUnity\Plugin::logError('Could not fetch updated group for post ID: ' . $postId);
                return;
            }

            if ($this->hasGroupChanged(self::$originalGroup, $updatedGroup)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('Changes detected in group ID: ' . $postId . ', firing unity/group_changing hook');
                }

                $post = get_post($postId);
                if ($post && $post->post_title !== $updatedGroup->getTitle()) {
                    wp_update_post([
                        'ID' => $postId,
                        'post_title' => $updatedGroup->getTitle()
                    ]);
                }

                do_action('unity/group_changing', $updatedGroup, self::$originalGroup);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('No changes detected in group ID: ' . $postId);
                }
            }

            do_action('unity/group_changed', $postId, $updatedGroup, self::$originalGroup);

            self::$originalGroup = null;
        } catch (Exception $e) {
            \TsmlForUnity\Plugin::logError('Error checking for group changes: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle group deletion (trash or permanent delete)
     *
     * Captures the group before it is removed and fires the
     * unity/group_deleted hook so that listeners can react.
     *
     * @param int $postId The post ID being deleted or trashed
     * @return void
     */
    public function onGroupDeleted(int $postId): void
    {
        if (get_post_type($postId) !== TsmlGroupFields::POST_TYPE) {
            return;
        }

        try {
            $group = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Group deleted, firing unity/group_deleted hook for post ID: ' . $postId);
            }

            do_action('unity/group_deleted', $postId, $group);
        } catch (Exception $e) {
            // Group may already be partially removed; fire with null so
            // listeners can still react to the deletion itself.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Error fetching group during deletion: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }

            do_action('unity/group_deleted', $postId, null);
        }
    }

    /**
     * Handle a group being hidden (post status set to private)
     *
     * Fires the unity/group_hidden hook when a group's publish state
     * transitions to private.
     *
     * @param string $newStatus The new post status
     * @param string $oldStatus The previous post status
     * @param \WP_Post $post The post object
     * @return void
     */
    public function onGroupHidden(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($post->post_type !== TsmlGroupFields::POST_TYPE) {
            return;
        }

        if ($newStatus !== 'private' || $oldStatus === 'private') {
            return;
        }

        try {
            $group = $this->repository->findById($post->ID);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Group hidden (set to private), firing unity/group_hidden hook for post ID: ' . $post->ID);
            }

            do_action('unity/group_hidden', $post->ID, $group);
        } catch (Exception $e) {
            // The repository may not return private posts; fire with null
            // so listeners can still react.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Error fetching group during hide: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }

            do_action('unity/group_hidden', $post->ID, null);
        }
    }

    /**
     * Check if a group has changed by comparing its properties
     *
     * @param Group $originalGroup The original group before changes
     * @param Group $updatedGroup The updated group after changes
     * @return bool True if the group has changed, false otherwise
     */
    private function hasGroupChanged(Group $originalGroup, Group $updatedGroup): bool
    {
        if ($originalGroup->getTitle() !== $updatedGroup->getTitle()) {
            return true;
        }

        if ($originalGroup->getEmail() !== $updatedGroup->getEmail()) {
            return true;
        }

        if ($originalGroup->getGroupNotes() !== $updatedGroup->getGroupNotes()) {
            return true;
        }

        if ($originalGroup->getWebsite() !== $updatedGroup->getWebsite()) {
            return true;
        }

        if ($originalGroup->getPhone() !== $updatedGroup->getPhone()) {
            return true;
        }

        if ($originalGroup->getVenmo() !== $updatedGroup->getVenmo()) {
            return true;
        }

        if ($originalGroup->getPaypal() !== $updatedGroup->getPaypal()) {
            return true;
        }

        if ($originalGroup->getSquare() !== $updatedGroup->getSquare()) {
            return true;
        }

        if ($originalGroup->getDistrictId() !== $updatedGroup->getDistrictId()) {
            return true;
        }

        if ($originalGroup->getLastContact() !== $updatedGroup->getLastContact()) {
            return true;
        }

        $originalMeetingIds = $this->getMeetingIds($originalGroup);
        $updatedMeetingIds = $this->getMeetingIds($updatedGroup);

        sort($originalMeetingIds);
        sort($updatedMeetingIds);

        if ($originalMeetingIds !== $updatedMeetingIds) {
            return true;
        }

        if ($this->haveContactsChanged($originalGroup->getContacts(), $updatedGroup->getContacts())) {
            return true;
        }

        return false;
    }

    /**
     * Check if contacts have changed by comparing arrays of Contact objects
     *
     * @param array $originalContacts Original contacts
     * @param array $updatedContacts Updated contacts
     * @return bool True if contacts have changed
     */
    private function haveContactsChanged(array $originalContacts, array $updatedContacts): bool
    {
        if (count($originalContacts) !== count($updatedContacts)) {
            return true;
        }

        $normalize = function (array $contacts): array {
            $keys = [];
            foreach ($contacts as $contact) {
                $keys[] = $contact->getName() . '|' . $contact->getEmail() . '|' . $contact->getPhone();
            }
            sort($keys);
            return $keys;
        };

        return $normalize($originalContacts) !== $normalize($updatedContacts);
    }

    /**
     * Extract meeting IDs from a group's meetings
     *
     * @param Group $group The group to extract meeting IDs from
     * @return int[] Array of meeting IDs
     */
    private function getMeetingIds(Group $group): array
    {
        $ids = [];
        foreach ($group->getMeetings() as $meeting) {
            $ids[] = $meeting->getId();
        }
        return $ids;
    }
}
