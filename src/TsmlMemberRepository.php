<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Members\Interfaces\MemberFactoryInterface;
use Unity\Members\Interfaces\MemberInterface;
use Unity\Members\Interfaces\MemberRepositoryInterface;
use function get_post;
use function get_posts;
use function update_field;
use function wp_delete_post;

/**
 * TSML Member Repository
 */
class TsmlMemberRepository implements MemberRepositoryInterface
{
    private MemberFactoryInterface $memberFactory;

    /**
     * TsmlMemberRepository constructor
     *
     * @param MemberFactoryInterface $memberFactory
     */
    public function __construct(MemberFactoryInterface $memberFactory)
    {
        $this->memberFactory = $memberFactory;
    }

    /**
     * Find a member by ID
     *
     * @param int $id
     * @return MemberInterface|null
     */
    public function find(int $id): ?MemberInterface
    {
        $post = get_post($id);

        if (!$post || $post->post_type !== TsmlMemberFields::POST_TYPE) {
            return null;
        }

        return $this->memberFactory->createFromSource($id);
    }

    /**
     * Find all members with optional filtering
     *
     * @param array $args Optional get_posts arguments
     * @return array Array of MemberInterface objects
     */
    public function findAll(array $args = []): array
    {
        $defaultArgs = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish'
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);
        $members = [];

        if (!empty($posts)) {
            foreach ($posts as $post) {
                $member = $this->find($post->ID);
                if ($member) {
                    $members[] = $member;
                }
            }
        }

        return $members;
    }

    /**
     * Get total count of members matching criteria
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public function count(array $args = []): int
    {
        $defaultArgs = [
            'post_type' => TsmlMemberFields::POST_TYPE,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ];

        $queryArgs = array_merge($defaultArgs, $args);
        $posts = get_posts($queryArgs);

        return is_array($posts) ? count($posts) : 0;
    }

    /**
     * Save member data
     *
     * @param MemberInterface $member
     * @return bool
     */
    public function save(MemberInterface $member): bool
    {
        $id = $member->getId();

        update_field(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $member->getAnonymousName(), $id);
        update_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $member->getEmail(), $id);
        update_field(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $member->showAnonymousName(), $id);
        update_field(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $member->showMemberProfile(), $id);
        update_field(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $member->getAnonymousProfile(), $id);
        update_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION, $member->getIntergroupPosition(), $id);
        update_field(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $member->getIntergroupPositionRotation(), $id);
        update_field(TsmlMemberFields::FIELD_HOME_GROUP, $member->getHomeGroup(), $id);
        update_field(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $member->isGSR(), $id);
        update_field(TsmlMemberFields::FIELD_MEETING_PO, $member->getMeetingPO(), $id);
        update_field(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $member->getPersonalEmail(), $id);
        update_field(TsmlMemberFields::FIELD_MOBILE_NUMBER, $member->getMobileNumber(), $id);

        return true;
    }

    /**
     * Delete a member
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return (bool) wp_delete_post($id, true);
    }
}