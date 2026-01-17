<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Groups\Interfaces\GroupFactoryInterface;
use Unity\Groups\Interfaces\GroupInterface;
use Unity\Groups\Interfaces\GroupRepositoryInterface;
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
class TsmlGroupRepository implements GroupRepositoryInterface
{
    private GroupFactoryInterface $factory;
    
    /**
     * TsmlGroupRepository constructor
     * 
     * @param GroupFactoryInterface $factory The group factory
     */
    public function __construct(GroupFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?GroupInterface
    {
        return $this->factory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        $queryArgs = wp_parse_args($args, $defaultArgs);
        $posts = get_posts($queryArgs);
        $groups = [];

        foreach ($posts as $post) {
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
    public function save(GroupInterface $group): bool
    {
        $postId = $group->getId();
        
        if ($postId > 0) {
            return $this->update($group);
        }

        if (!$group->isValid()) {
            return false;
        }

        $postData = [
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
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
    public function update(GroupInterface $group): bool
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
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
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
