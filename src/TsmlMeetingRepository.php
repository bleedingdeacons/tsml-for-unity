<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Common\Interfaces\CacheInterface;
use Unity\Meetings\Interfaces\MeetingFactoryInterface;
use Unity\Meetings\Interfaces\MeetingInterface;
use Unity\Meetings\Interfaces\MeetingRepositoryInterface;

/**
 * TSML Meeting Repository
 *
 * Repository for retrieving Meeting objects from WordPress.
 */
class TsmlMeetingRepository implements MeetingRepositoryInterface
{
    private const POST_TYPE = 'tsml_meeting';
    private const CACHE_GROUP = 'unity_meetings';
    private const CACHE_TTL = 3600; // 1 hour

    private MeetingFactoryInterface $factory;
    private ?CacheInterface $cache;

    /**
     * TsmlMeetingRepository constructor.
     *
     * @param MeetingFactoryInterface $factory Meeting factory
     * @param CacheInterface|null $cache Optional cache implementation
     */
    public function __construct(
        MeetingFactoryInterface $factory,
        ?CacheInterface $cache = null
    ) {
        $this->factory = $factory;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?MeetingInterface
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
        if (!$post || $post->post_type !== self::POST_TYPE) {
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
            'post_type' => self::POST_TYPE,
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
        $args['meta_query'][] = [
            'key' => 'day',
            'value' => $day,
            'compare' => '=',
        ];

        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function findOnline(array $args = []): array
    {
        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'key' => 'attendance_option',
            'value' => 'online',
            'compare' => '=',
        ];

        return $this->findAll($args);
    }

    /**
     * {@inheritdoc}
     */
    public function findInPerson(array $args = []): array
    {
        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'key' => 'attendance_option',
            'value' => 'in_person',
            'compare' => '=',
        ];

        return $this->findAll($args);
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
        $args['fields'] = 'ids';
        $args['posts_per_page'] = -1;

        $posts = $this->findAll($args);
        return count($posts);
    }

    /**
     * Create a Meeting object from a WordPress post.
     *
     * @param \WP_Post $post WordPress post object
     * @return MeetingInterface|null Meeting object or null if creation fails
     */
    private function createMeetingFromPost(\WP_Post $post): ?MeetingInterface
    {
        $meta = get_post_meta($post->ID);

        $source = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'slug' => $post->post_name,
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
     * @return MeetingInterface[] Array of Meeting objects
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
