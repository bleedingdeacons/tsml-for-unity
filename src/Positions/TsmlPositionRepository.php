<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

use Unity\Positions\Interfaces\PositionFactory;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;
use Exception;
use function get_posts;
use function is_wp_error;
use function update_field;
use function wp_insert_post;
use function wp_parse_args;
use function wp_update_post;

/**
 * TSML Position Repository
 */
class TsmlPositionRepository implements PositionRepository
{
    private PositionFactory $factory;

    /**
     * TsmlPositionRepository constructor
     *
     * @param PositionFactory $factory The position factory
     */
    public function __construct(PositionFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Position
    {
        return $this->factory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlPositionFields::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        $queryArgs = wp_parse_args($args, $defaultArgs);
        $posts = get_posts($queryArgs);
        $positions = [];

        foreach ($posts as $post) {
            $position = $this->factory->createFromSource($post->ID);
            if ($position !== null) {
                $positions[] = $position;
            }
        }

        return $positions;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $args = []): int
    {
        $defaultArgs = [
            'post_type' => TsmlPositionFields::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ];

        $queryArgs = wp_parse_args($args, $defaultArgs);
        $posts = get_posts($queryArgs);

        return is_array($posts) ? count($posts) : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function save(Position $position): bool
    {
        $postId = $position->getId();

        if ($postId > 0) {
            return $this->update($position);
        }

        if (!$position->isValid()) {
            return false;
        }

        $postData = [
            'post_type' => TsmlPositionFields::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $position->getLongName(),
            'post_content' => '',
        ];

        $result = wp_insert_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        $postId = $result;

        if (function_exists('update_field')) {
            update_field(TsmlPositionFields::MINIMUM_SOBRIETY, $position->getMinimumSobriety(), $postId);
            update_field(TsmlPositionFields::TERM_YEARS, $position->getTermYears(), $postId);
            update_field(TsmlPositionFields::EMAIL_ADDRESS, $position->getEmail(), $postId);
            update_field(TsmlPositionFields::LONG_NAME, $position->getLongName(), $postId);
            update_field(TsmlPositionFields::SHORT_DESCRIPTION, $position->getShortDescription(), $postId);
            update_field(TsmlPositionFields::SUMMARY, $position->getSummary(), $postId);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Position $position): bool
    {
        $postId = $position->getId();

        if ($postId <= 0) {
            return false;
        }

        if (!$position->isValid()) {
            return false;
        }

        $postData = [
            'ID' => $postId,
            'post_title' => $position->getLongName(),
            'post_type' => TsmlPositionFields::POST_TYPE,
            'post_status' => 'publish',
        ];

        $result = wp_update_post($postData, true);

        if (is_wp_error($result)) {
            return false;
        }

        if (function_exists('update_field')) {
            update_field(TsmlPositionFields::MINIMUM_SOBRIETY, $position->getMinimumSobriety(), $postId);
            update_field(TsmlPositionFields::TERM_YEARS, $position->getTermYears(), $postId);
            update_field(TsmlPositionFields::EMAIL_ADDRESS, $position->getEmail(), $postId);
            update_field(TsmlPositionFields::LONG_NAME, $position->getLongName(), $postId);
            update_field(TsmlPositionFields::SHORT_DESCRIPTION, $position->getShortDescription(), $postId);
            update_field(TsmlPositionFields::SUMMARY, $position->getSummary(), $postId);
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