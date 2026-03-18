<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Groups\Interfaces\GroupFactory;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupRepository;
use Exception;
use function get_posts;
use function is_wp_error;
use function update_field;
use function wp_insert_post;
use function wp_parse_args;
use function wp_update_post;

/**
 * TSML Group Repository class
 */
class TsmlGroupRepository implements GroupRepository
{
    private GroupFactory $factory;

    /**
     * TsmlGroupRepository constructor
     *
     * @param GroupFactory $factory The group factory
     */
    public function __construct(GroupFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Group
    {
        return $this->factory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlGroupFields::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        $queryArgs = wp_parse_args($args, $defaultArgs);
        $posts = get_posts($queryArgs);
        $groups = [];

        foreach ($posts as $post) {
            if (empty($post->ID)) {
                continue;
            }
            $group = $this->factory->createFromSource($post->ID);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Group $group): bool
    {
        $postId = $group->getId();

        if ($postId > 0) {
            return $this->update($group);
        }

        if (!$group->isValid()) {
            return false;
        }

        $postData = [
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $group->getTitle(),
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $postId = $result;

        if (function_exists('update_field')) {
            update_field(TsmlGroupFields::TITLE, $group->getTitle(), $postId);
            update_field(TsmlGroupFields::EMAIL, $group->getEmail(), $postId);
            update_field(TsmlGroupFields::MEETING, $group->getMeetingIds(), $postId);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Group $group): bool
    {
        $postId = $group->getId();

        if ($postId <= 0) {
            return false;
        }

        if (!$group->isValid()) {
            return false;
        }

        $postData = [
            'ID' => $postId,
            'post_title' => $group->getTitle(),
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_status' => 'publish',
        ];

        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        if (function_exists('update_field')) {
            update_field(TsmlGroupFields::TITLE, $group->getTitle(), $postId);
            update_field(TsmlGroupFields::EMAIL, $group->getEmail(), $postId);
            update_field(TsmlGroupFields::MEETING, $group->getMeetingIds(), $postId);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        throw new Exception('Delete is not implemented');
    }
}