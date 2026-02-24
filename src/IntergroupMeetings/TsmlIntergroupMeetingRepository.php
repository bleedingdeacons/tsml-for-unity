<?php

declare(strict_types=1);

namespace TsmlForUnity\IntergroupMeetings;

use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use function get_post;
use function get_posts;
use function update_field;
use function update_post_meta;
use function wp_delete_post;

/**
 * TSML Intergroup Meeting Repository
 */
class TsmlIntergroupMeetingRepository implements IntergroupMeetingRepository
{
    private IntergroupMeetingFactory $intergroupMeetingFactory;

    /**
     * TsmlIntergroupMeetingRepository constructor
     *
     * @param IntergroupMeetingFactory $intergroupMeetingFactory
     */
    public function __construct(IntergroupMeetingFactory $intergroupMeetingFactory)
    {
        $this->intergroupMeetingFactory = $intergroupMeetingFactory;
    }

    /**
     * Find an intergroup meeting by ID
     *
     * @param int $id
     * @return IntergroupMeeting|null
     */
    public function find(int $id): ?IntergroupMeeting
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TsmlIntergroupMeetingFields::POST_TYPE) {
            return null;
        }

        return $this->intergroupMeetingFactory->createFromSource($id);
    }

    /**
     * Find all intergroup meetings with optional filtering
     *
     * @param array $args Optional get_posts arguments
     * @return array<IntergroupMeeting>
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
            'post_type' => TsmlIntergroupMeetingFields::POST_TYPE,
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
     * Uses update_field() for the ACF relationship fields so that the data
     * is stored in the format ACF expects. This ensures the values are visible
     * in the ACF admin UI and that get_field() reads them correctly.
     *
     * ACF relationship fields store an array of post IDs in postmeta. When
     * update_field() is called with the field name, ACF resolves the field
     * key via the shadow meta key (e.g. _attending_groups) and writes the
     * data in its canonical format.
     *
     * @param IntergroupMeeting $intergroupMeeting
     * @return bool
     */
    public function save(IntergroupMeeting $intergroupMeeting): bool
    {
        $id = $intergroupMeeting->getId();

        // Use update_field() with the ACF field KEY (not the field name).
        //
        // When called with a field name like 'attending_groups', ACF must
        // resolve the key via the shadow meta row (_attending_groups →
        // field_69760086d06fa). If that shadow row doesn't exist — e.g. the
        // post was created via the API and never saved in the ACF admin —
        // the lookup fails silently and nothing is written.
        //
        // Passing the field key directly (field_69760086d06fa) bypasses this
        // lookup entirely, so the write always succeeds. ACF will also create
        // the shadow meta row automatically, so future get_field() calls by
        // name will work too.
        update_field(
            TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES,
            $intergroupMeeting->getGroupAttendees(),
            $id
        );

        update_field(
            TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS,
            $intergroupMeeting->getOfficersAttending(),
            $id
        );

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