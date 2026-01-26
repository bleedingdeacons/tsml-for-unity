<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactoryInterface;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingInterface;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepositoryInterface;
use function get_post;
use function get_posts;
use function update_field;
use function wp_delete_post;

/**
 * TSML Intergroup Meeting Repository
 */
class TsmlIntergroupMeetingRepository implements IntergroupMeetingRepositoryInterface
{
    private IntergroupMeetingFactoryInterface $intergroupMeetingFactory;

    /**
     * TsmlIntergroupMeetingRepository constructor
     *
     * @param IntergroupMeetingFactoryInterface $intergroupMeetingFactory
     */
    public function __construct(IntergroupMeetingFactoryInterface $intergroupMeetingFactory)
    {
        $this->intergroupMeetingFactory = $intergroupMeetingFactory;
    }

    /**
     * Find an intergroup meeting by ID
     *
     * @param int $id
     * @return IntergroupMeetingInterface|null
     */
    public function find(int $id): ?IntergroupMeetingInterface
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TsmlIntergroupMeetingFields::INTERGROUP_MEETING_POST_TYPE) {
            return null;
        }

        return $this->intergroupMeetingFactory->createFromSource($id);
    }

    /**
     * Find all intergroup meetings with optional filtering
     *
     * @param array $args Optional get_posts arguments
     * @return array<IntergroupMeetingInterface>
     */
    public function findAll(array $args = []): array
    {
        $queryArgs = $this->buildQueryArgs($args);
        $posts = get_posts($queryArgs);
        $intergroupMeetings = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $intergroupMeeting = $this->find($post->ID);
                if ($intergroupMeeting) {
                    $intergroupMeetings[] = $intergroupMeeting;
                }
            }
        }

        return $intergroupMeetings;
    }

    /**
     * Get total count of intergroup meetings matching criteria
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public function count(array $args = []): int
    {
        $queryArgs = $this->buildQueryArgs($args);
        $queryArgs['numberposts'] = -1;
        $queryArgs['fields'] = 'ids';

        // Remove pagination for count
        unset($queryArgs['paged'], $queryArgs['offset']);

        $posts = get_posts($queryArgs);

        return is_array($posts) ? count($posts) : 0;
    }

    /**
     * Build query arguments for get_posts
     *
     * @param array $args Input arguments
     * @return array Query arguments for get_posts
     */
    private function buildQueryArgs(array $args): array
    {
        $defaultArgs = [
            'post_type' => TsmlIntergroupMeetingFields::INTERGROUP_MEETING_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
        ];

        // Convert posts_per_page to numberposts for get_posts()
        if (isset($args['posts_per_page'])) {
            $args['numberposts'] = $args['posts_per_page'];
            unset($args['posts_per_page']);
        }

        // Handle pagination offset
        if (isset($args['paged']) && isset($args['numberposts']) && $args['numberposts'] > 0) {
            $args['offset'] = ($args['paged'] - 1) * $args['numberposts'];
            unset($args['paged']);
        }

        return array_merge($defaultArgs, $args);
    }

    /**
     * Save intergroup meeting data
     *
     * @param IntergroupMeetingInterface $intergroupMeeting
     * @return bool
     */
    public function save(IntergroupMeetingInterface $intergroupMeeting): bool
    {
        $id = $intergroupMeeting->getId();

        update_field(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, $intergroupMeeting->getGroupAttendees(), $id);
        update_field(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, $intergroupMeeting->getOfficersAttending(), $id);
        update_field(TsmlIntergroupMeetingFields::FIELD_DATE, $intergroupMeeting->getDate(), $id);

        return true;
    }

    /**
     * Delete an intergroup meeting
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return (bool) wp_delete_post($id, true);
    }
}