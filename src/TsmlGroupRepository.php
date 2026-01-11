<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Exception;
use Unity\Groups\Interfaces\GroupFactoryInterface;
use Unity\Groups\Interfaces\GroupInterface;
use Unity\Groups\Interfaces\GroupRepositoryInterface;

/**
 * Repository for TSML Groups
 * 
 * Implements Unity's GroupRepositoryInterface to provide data access
 * for the 12 Step Meeting List plugin's tsml_group custom post type.
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
        if (!function_exists('get_posts') || !function_exists('wp_parse_args')) {
            return [];
        }

        $defaultArgs = [
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
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
     * Find groups by district ID
     * 
     * @param int   $districtId The district ID
     * @param array $args       Additional query arguments
     * @return array Array of GroupInterface objects
     */
    public function findByDistrict(int $districtId, array $args = []): array
    {
        $args['meta_query'] = [
            [
                'key' => TsmlGroupFields::DISTRICT_ID,
                'value' => $districtId,
                'compare' => '=',
            ],
        ];

        return $this->findAll($args);
    }

    /**
     * Find groups that have contribution options (Venmo, PayPal, or Square)
     * 
     * @param array $args Additional query arguments
     * @return array Array of GroupInterface objects with contribution options
     */
    public function findWithContributionOptions(array $args = []): array
    {
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => TsmlGroupFields::VENMO,
                'value' => '',
                'compare' => '!=',
            ],
            [
                'key' => TsmlGroupFields::PAYPAL,
                'value' => '',
                'compare' => '!=',
            ],
            [
                'key' => TsmlGroupFields::SQUARE,
                'value' => '',
                'compare' => '!=',
            ],
        ];

        return $this->findAll($args);
    }

    /**
     * Search groups by title
     * 
     * @param string $search Search string
     * @param array  $args   Additional query arguments
     * @return array Array of GroupInterface objects
     */
    public function searchByTitle(string $search, array $args = []): array
    {
        $args['s'] = $search;
        return $this->findAll($args);
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

        if (!function_exists('wp_insert_post') || !function_exists('is_wp_error')) {
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

        $this->updateGroupMeta($postId, $group);

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

        if (!function_exists('wp_update_post') || !function_exists('is_wp_error')) {
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

        $this->updateGroupMeta($postId, $group);

        return true;
    }

    /**
     * Update the post meta for a group
     * 
     * @param int            $postId The post ID
     * @param GroupInterface $group  The group object
     */
    private function updateGroupMeta(int $postId, GroupInterface $group): void
    {
        if (!function_exists('update_post_meta')) {
            return;
        }

        update_post_meta($postId, TsmlGroupFields::EMAIL, $group->getEmail());

        // Update additional TSML-specific fields if the group is a TsmlGroup
        if ($group instanceof TsmlGroup) {
            update_post_meta($postId, TsmlGroupFields::GROUP_NOTES, $group->getGroupNotes());
            update_post_meta($postId, TsmlGroupFields::WEBSITE, $group->getWebsite());
            update_post_meta($postId, TsmlGroupFields::PHONE, $group->getPhone());
            update_post_meta($postId, TsmlGroupFields::VENMO, $group->getVenmo());
            update_post_meta($postId, TsmlGroupFields::PAYPAL, $group->getPaypal());
            update_post_meta($postId, TsmlGroupFields::SQUARE, $group->getSquare());

            if ($group->getDistrictId() !== null) {
                update_post_meta($postId, TsmlGroupFields::DISTRICT_ID, $group->getDistrictId());
            }

            if ($group->getLastContact() !== null) {
                update_post_meta($postId, TsmlGroupFields::LAST_CONTACT, $group->getLastContact());
            }

            $this->updateContacts($postId, $group->getContacts());
        }
    }

    /**
     * Update contact information for a group
     * 
     * @param int   $postId   The post ID
     * @param array $contacts Array of contact data
     */
    private function updateContacts(int $postId, array $contacts): void
    {
        if (!function_exists('update_post_meta') || !function_exists('delete_post_meta')) {
            return;
        }

        // Clear existing contacts
        for ($i = 1; $i <= TsmlGroupFields::MAX_CONTACTS; $i++) {
            delete_post_meta($postId, TsmlGroupFields::CONTACT_PREFIX . $i . '_name');
            delete_post_meta($postId, TsmlGroupFields::CONTACT_PREFIX . $i . '_email');
            delete_post_meta($postId, TsmlGroupFields::CONTACT_PREFIX . $i . '_phone');
        }

        // Add new contacts
        $contactIndex = 1;
        foreach ($contacts as $contact) {
            if ($contactIndex > TsmlGroupFields::MAX_CONTACTS) {
                break;
            }

            if (is_array($contact)) {
                $prefix = TsmlGroupFields::CONTACT_PREFIX . $contactIndex;
                
                if (!empty($contact['name'])) {
                    update_post_meta($postId, $prefix . '_name', $contact['name']);
                }
                if (!empty($contact['email'])) {
                    update_post_meta($postId, $prefix . '_email', $contact['email']);
                }
                if (!empty($contact['phone'])) {
                    update_post_meta($postId, $prefix . '_phone', $contact['phone']);
                }

                $contactIndex++;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        throw new Exception('Delete is not implemented for TSML groups');
    }

    /**
     * Get the total count of groups
     * 
     * @param array $args Optional query arguments
     * @return int The count of groups
     */
    public function count(array $args = []): int
    {
        if (!function_exists('wp_count_posts')) {
            return 0;
        }

        $counts = wp_count_posts(TsmlGroupFields::GROUP_POST_TYPE);
        return (int) ($counts->publish ?? 0);
    }
}
