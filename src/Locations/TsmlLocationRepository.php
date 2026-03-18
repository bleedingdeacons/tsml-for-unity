<?php

declare(strict_types=1);

namespace TsmlForUnity\Locations;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\Location;
use Unity\Locations\Interfaces\LocationRepository;
use Exception;
use function get_posts;
use function wp_parse_args;

/**
 * TSML Location Repository
 *
 * Handles retrieval of Location entities from the WordPress database.
 * Save/update/delete operations are not implemented as locations are
 * typically managed by the TSML plugin.
 */
class TsmlLocationRepository implements LocationRepository
{
    private LocationFactory $factory;

    /**
     * The location post type - uses TSML's location post type
     */
    private const LOCATION_POST_TYPE = 'tsml_location';

    /**
     * TsmlLocationRepository constructor
     *
     * @param LocationFactory $factory The location factory
     */
    public function __construct(LocationFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Location
    {
        return $this->factory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => self::LOCATION_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        $queryArgs = wp_parse_args($args, $defaultArgs);
        $posts = get_posts($queryArgs);
        $locations = [];

        foreach ($posts as $post) {
            $location = $this->factory->createFromSource($post->ID);
            if ($location !== null) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    /**
     * {@inheritdoc}
     */
    public function findByCity(string $city): array
    {
        return $this->findAll([
            'meta_query' => [
                [
                    'key' => 'city',
                    'value' => $city,
                    'compare' => '=',
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function findByRegion(string $region): array
    {
        return $this->findAll([
            'tax_query' => [
                [
                    'taxonomy' => 'tsml_region',
                    'field' => 'name',
                    'terms' => $region,
                ],
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function save(Location $location): bool
    {
        throw new Exception('Save is not implemented - locations are managed by the TSML plugin');
    }

    /**
     * {@inheritdoc}
     */
    public function update(Location $location): bool
    {
        throw new Exception('Update is not implemented - locations are managed by the TSML plugin');
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        throw new Exception('Delete is not implemented - locations are managed by the TSML plugin');
    }
}
