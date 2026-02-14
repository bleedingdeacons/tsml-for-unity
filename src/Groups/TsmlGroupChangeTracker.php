<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

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
                error_log('Original group captured for post ID: ' . $postId);
            }

            do_action('group_before_save', $postId, self::$originalGroup);
        } catch (Exception $e) {
            error_log('Error capturing original group: ' . $e->getMessage());
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
                error_log('No original group captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedGroup = $this->repository->findById($postId);

            if (!$updatedGroup) {
                error_log('Could not fetch updated group for post ID: ' . $postId);
                return;
            }

            if ($this->hasGroupChanged(self::$originalGroup, $updatedGroup)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Changes detected in group ID: ' . $postId . ', firing unity/group_changing hook');
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
                    error_log('No changes detected in group ID: ' . $postId);
                }
            }

            do_action('unity/group_changed', $postId, $updatedGroup, self::$originalGroup);

            self::$originalGroup = null;
        } catch (Exception $e) {
            error_log('Error checking for group changes: ' . $e->getMessage());
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
