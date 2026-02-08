<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Positions\Interfaces\PositionInterface;
use Unity\Positions\Interfaces\PositionRepositoryInterface;
use Exception;
use function add_action;
use function do_action;
use function get_post;
use function get_post_type;
use function wp_update_post;
use const WP_DEBUG;

/**
 * Class TsmlPositionChangeTracker
 *
 * Tracks changes to positions via ACF and fires the position_changed hook
 * when actual changes are detected.
 */
class TsmlPositionChangeTracker
{
    private static ?PositionInterface $originalPosition = null;
    private PositionRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param PositionRepositoryInterface $repository Repository for accessing positions
     */
    public function __construct(PositionRepositoryInterface $repository)
    {
        $this->repository = $repository;

        add_action('acf/save_post', [$this, 'captureOriginalPosition'], 1);
        add_action('acf/save_post', [$this, 'checkForChanges'], 20);
    }

    /**
     * Capture the original position before ACF makes changes
     *
     * @param int $postId The post ID being saved
     * @return void
     */
    public function captureOriginalPosition(int $postId): void
    {
        if (get_post_type($postId) !== TsmlPositionFields::POSITION_POST_TYPE) {
            return;
        }

        try {
            self::$originalPosition = $this->repository->findById($postId);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Original position captured for post ID: ' . $postId);
            }

            do_action('position_before_save', $postId, self::$originalPosition);
        } catch (Exception $e) {
            error_log('Error capturing original position: ' . $e->getMessage());
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
        if (get_post_type($postId) !== TsmlPositionFields::POSITION_POST_TYPE) {
            return;
        }

        if (!self::$originalPosition) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('No original position captured for comparison, post ID: ' . $postId);
            }
            return;
        }

        try {
            $updatedPosition = $this->repository->findById($postId);

            if (!$updatedPosition) {
                error_log('Could not fetch updated position for post ID: ' . $postId);
                return;
            }

            if ($this->hasPositionChanged(self::$originalPosition, $updatedPosition)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Changes detected in position ID: ' . $postId . ', firing position_changed hook');
                }

                $post = get_post($postId);
                if ($post && $post->post_title !== $updatedPosition->getLongName()) {
                    wp_update_post([
                        'ID' => $postId,
                        'post_title' => $updatedPosition->getLongName()
                    ]);
                }

                do_action('position_changed', $updatedPosition, self::$originalPosition);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('No changes detected in position ID: ' . $postId);
                }
            }

            do_action('position_after_save', $postId, $updatedPosition, self::$originalPosition);

            self::$originalPosition = null;
        } catch (Exception $e) {
            error_log('Error checking for position changes: ' . $e->getMessage());
        }
    }

    /**
     * Check if a position has changed by comparing its properties
     *
     * @param PositionInterface $originalPosition The original position before changes
     * @param PositionInterface $updatedPosition The updated position after changes
     * @return bool True if the position has changed, false otherwise
     */
    private function hasPositionChanged(PositionInterface $originalPosition, PositionInterface $updatedPosition): bool
    {
        if ($originalPosition->getLongName() !== $updatedPosition->getLongName()) {
            return true;
        }

        if ($originalPosition->getEmail() !== $updatedPosition->getEmail()) {
            return true;
        }

        if ($originalPosition->getMinimumSobriety() !== $updatedPosition->getMinimumSobriety()) {
            return true;
        }

        if ($originalPosition->getTermYears() !== $updatedPosition->getTermYears()) {
            return true;
        }

        if ($originalPosition->getShortDescription() !== $updatedPosition->getShortDescription()) {
            return true;
        }

        if ($originalPosition->getSummary() !== $updatedPosition->getSummary()) {
            return true;
        }

        if ($originalPosition->getLink() !== $updatedPosition->getLink()) {
            return true;
        }

        return false;
    }
}
