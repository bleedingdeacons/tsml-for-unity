<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use TsmlForUnity\Meetings\TsmlMeetingFields;

use Unity\Core\Interfaces\Cache;
use Unity\Meetings\Interfaces\MeetingFactory;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;

/**
 * TSML Meeting Repository
 *
 * Repository for retrieving Meeting objects from WordPress.
 */
class TsmlMeetingRepository implements MeetingRepository
{
    private const CACHE_GROUP = 'unity_meetings';
    private const CACHE_TTL = 3600; // 1 hour

    private MeetingFactory $factory;
    private ?Cache $cache;

    /**
     * TsmlMeetingRepository constructor.
     *
     * @param MeetingFactory $factory Meeting factory
     * @param Cache|null $cache Optional cache implementation
     */
    public function __construct(
        MeetingFactory $factory,
        ?Cache $cache = null
    ) {
        $this->factory = $factory;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?Meeting
    {
        if ($id <= 0) {
            return null;
        }

        // Try cache first
        $cacheKey = "meeting_{$id}";
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Get post
        $post = get_post($id);
        if (!$post || $post->post_type !== TsmlMeetingFields::POST_TYPE) {
            return null;
        }

        // Create meeting from post
        $meeting = $this->createMeetingFromPost($post);

        // Cache result
        if ($meeting && $this->cache) {
            $this->cache->set($cacheKey, $meeting, self::CACHE_GROUP, self::CACHE_TTL);
        }

        return $meeting;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaults = [
            'post_type' => TsmlMeetingFields::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $args = array_merge($defaults, $args);

        $posts = get_posts($args);
        return $this->createMeetingsFromPosts($posts);
    }

    /**
     * {@inheritdoc}
     */
    public function findByDay(int $day, array $args = []): array
    {
        $args['meta_query'] = $args['meta_query'] ?? [];

        // Set relation to AND if there are multiple meta queries
        if (!empty($args['meta_query']) && !isset($args['meta_query']['relation'])) {
            $args['meta_query']['relation'] = 'AND';
        }

        $args['meta_query'][] = [
            'key' => 'day',
            'value' => (string) $day,  // WordPress stores meta values as strings
            'compare' => '=',
        ];

        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function findOnline(array $args = []): array
    {
        // Get all meetings matching the other criteria
        $allMeetings = $this->findAll($args);

        // Filter to only online meetings using the Meeting's isOnline() method
        // This handles all the different ways TSML can mark a meeting as online
        $onlineMeetings = array_filter($allMeetings, function($meeting) {
            return $meeting->isOnline();
        });

        return array_values($onlineMeetings);
    }

    /**
     * {@inheritdoc}
     */
    public function findInPerson(array $args = []): array
    {
        // Get all meetings matching the other criteria
        $allMeetings = $this->findAll($args);

        // Filter to only in-person meetings (NOT online)
        $inPersonMeetings = array_filter($allMeetings, function($meeting) {
            return !$meeting->isOnline();
        });

        return array_values($inPersonMeetings);
    }

    /**
     * {@inheritdoc}
     */
    public function findByGroupId(int $groupId, array $args = []): array
    {
        if ($groupId <= 0) {
            return [];
        }

        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'key' => 'group_id',
            'value' => $groupId,
            'compare' => '=',
        ];

        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function findByLocationId(int $locationId, array $args = []): array
    {
        if ($locationId <= 0) {
            return [];
        }

        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'key' => 'location_id',
            'value' => $locationId,
            'compare' => '=',
        ];

        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $keyword, array $args = []): array
    {
        if (empty($keyword)) {
            return [];
        }

        $args['s'] = $keyword;
        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $args = []): int
    {
        $defaults = [
            'post_type'      => TsmlMeetingFields::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $args = array_merge($defaults, $args);

        $posts = get_posts($args);
        return count($posts);
    }

    /**
     * Create a Meeting object from a WordPress post.
     *
     * @param \WP_Post $post WordPress post object
     * @return Meeting|null Meeting object or null if creation fails
     */
    private function createMeetingFromPost(\WP_Post $post): ?Meeting
    {
        $meta = get_post_meta($post->ID);

        $source = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
            'location_id' => $post->post_parent,
            'meta' => $meta,
        ];

        // Add common meta fields to source for easier access
        foreach ($meta as $key => $value) {
            if (!isset($source[$key]) && isset($value[0])) {
                $source[$key] = $value[0];
            }
        }

        return $this->factory->createFromSource($source);
    }

    /**
     * Create Meeting objects from an array of WordPress posts.
     *
     * @param \WP_Post[] $posts Array of WordPress post objects
     * @return Meeting[] Array of Meeting objects
     */
    private function createMeetingsFromPosts(array $posts): array
    {
        $meetings = [];

        foreach ($posts as $post) {
            $meeting = $this->createMeetingFromPost($post);
            if ($meeting !== null) {
                $meetings[] = $meeting;
            }
        }

        return $meetings;
    }
}