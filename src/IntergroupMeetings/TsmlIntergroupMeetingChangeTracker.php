<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingChangeTracker;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use Exception;
use function add_action;
use function do_action;
use function get_post;
use function get_post_type;
use function wp_update_post;
use const WP_DEBUG;

/**
 * Class TsmlIntergroupMeetingChangeTracker
 *
 * Tracks changes to intergroup meetings via ACF and fires lifecycle hooks
 * when actual changes are detected.
 *
 * Hooks fired:
 *   - unity/intergroup_meeting_before_save  (priority 1, before ACF writes)
 *   - unity/intergroup_meeting_changing     (only when fields actually differ)
 *   - unity/intergroup_meeting_changed      (always, after comparison)
 *   - unity/intergroup_meeting_deleted       (on trash or permanent delete)
 */
class TsmlIntergroupMeetingChangeTracker implements IntergroupMeetingChangeTracker
{
    private static ?IntergroupMeeting $originalMeeting = null;
    private IntergroupMeetingRepository $repository;

    /**
     * Constructor
     *
     * @param IntergroupMeetingRepository $repository Repository for accessing intergroup meetings
     */
    public function __construct(IntergroupMeetingRepository $repository)
    {
        $this->repository = $repository;

        add_action('acf/save_post', [$this, 'captureOriginalMeeting'], 1);
        add_action('acf/save_post', [$this, 'checkForChanges'], 20);
        add_action('before_delete_post', [$this, 'onIntergroupMeetingDeleted'], 10, 1);
        add_action('wp_trash_post', [$this, 'onIntergroupMeetingDeleted'], 10, 1);
    }

    /**
     * Capture the original intergroup meeting before ACF makes changes
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function captureOriginalMeeting(int $postId): void
    {
        if (get_post_type($postId) !== TsmlIntergroupMeetingFields::POST_TYPE) {
            return;
        }

        try {
            self::$originalMeeting = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Original intergroup meeting captured for post ID: ' . $postId);
            }

            do_action('unity/intergroup_meeting_before_save', $postId, self::$originalMeeting);
        } catch (Exception $e) {
            \TsmlForUnity\Plugin::logError('Error capturing original intergroup meeting: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
        if (get_post_type($postId) !== TsmlIntergroupMeetingFields::POST_TYPE) {
            return;
        }

        if (!self::$originalMeeting) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('No original intergroup meeting captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedMeeting = $this->repository->findById($postId);

            if (!$updatedMeeting) {
                \TsmlForUnity\Plugin::logError('Could not fetch updated intergroup meeting for post ID: ' . $postId);
                return;
            }

            if ($this->hasMeetingChanged(self::$originalMeeting, $updatedMeeting)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('Changes detected in intergroup meeting ID: ' . $postId . ', firing unity/intergroup_meeting_changing hook');
                }

                // Sync the meeting title ACF field to the WordPress post title
                $post = get_post($postId);
                if ($post && $post->post_title !== $updatedMeeting->getTitle()) {
                    wp_update_post([
                        'ID' => $postId,
                        'post_title' => $updatedMeeting->getTitle()
                    ]);
                }

                do_action('unity/intergroup_meeting_changing', $updatedMeeting, self::$originalMeeting);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    \TsmlForUnity\Plugin::logError('No changes detected in intergroup meeting ID: ' . $postId);
                }
            }

            do_action('unity/intergroup_meeting_changed', $postId, $updatedMeeting, self::$originalMeeting);

            self::$originalMeeting = null;
        } catch (Exception $e) {
            \TsmlForUnity\Plugin::logError('Error checking for intergroup meeting changes: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle intergroup meeting deletion (trash or permanent delete)
     *
     * Captures the intergroup meeting before it is removed and fires the
     * unity/intergroup_meeting_deleted hook so that listeners can react.
     *
     * @param int $postId The post ID being deleted or trashed
     * @return void
     */
    public function onIntergroupMeetingDeleted(int $postId): void
    {
        if (get_post_type($postId) !== TsmlIntergroupMeetingFields::POST_TYPE) {
            return;
        }

        try {
            $meeting = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Intergroup meeting deleted, firing unity/intergroup_meeting_deleted hook for post ID: ' . $postId);
            }

            do_action('unity/intergroup_meeting_deleted', $postId, $meeting);
        } catch (Exception $e) {
            // Meeting may already be partially removed; fire with null so
            // listeners can still react to the deletion itself.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \TsmlForUnity\Plugin::logError('Error fetching intergroup meeting during deletion: ' . $e->getMessage(), ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }

            do_action('unity/intergroup_meeting_deleted', $postId, null);
        }
    }

    /**
     * Check if an intergroup meeting has changed by comparing its properties
     *
     * @param IntergroupMeeting $originalMeeting The original meeting before changes
     * @param IntergroupMeeting $updatedMeeting The updated meeting after changes
     * @return bool True if the meeting has changed, false otherwise
     */
    private function hasMeetingChanged(IntergroupMeeting $originalMeeting, IntergroupMeeting $updatedMeeting): bool
    {
        if ($originalMeeting->getTitle() !== $updatedMeeting->getTitle()) {
            return true;
        }

        if ($originalMeeting->getDate() !== $updatedMeeting->getDate()) {
            return true;
        }

        $originalGroupAttendees = $originalMeeting->getGroupAttendees();
        $updatedGroupAttendees = $updatedMeeting->getGroupAttendees();
        sort($originalGroupAttendees);
        sort($updatedGroupAttendees);

        if ($originalGroupAttendees !== $updatedGroupAttendees) {
            return true;
        }

        $originalOfficers = $originalMeeting->getOfficersAttending();
        $updatedOfficers = $updatedMeeting->getOfficersAttending();
        sort($originalOfficers);
        sort($updatedOfficers);

        if ($originalOfficers !== $updatedOfficers) {
            return true;
        }

        return false;
    }
}