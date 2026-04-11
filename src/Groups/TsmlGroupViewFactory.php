<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingViewFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Groups\Interfaces\GroupView;

use Unity\Members\Interfaces\MemberRepository;
use function get_post;

/**
 * Concrete TSML Group View Factory class
 */
class TsmlGroupViewFactory implements GroupViewFactory
{
    private GroupRepository $groupRepository;
    private MeetingRepository $meetingRepository;
    private MemberRepository $memberRepository;

    /**
     * TsmlGroupViewFactory constructor
     * 
     * @param GroupRepository $groupRepository The group repository
     * @param MeetingRepository $meetingRepository The meeting repository
     */
    public function __construct(
        GroupRepository $groupRepository,
        MeetingRepository $meetingRepository,
        MemberRepository $memberRepository
    ) {
        $this->groupRepository = $groupRepository;
        $this->meetingRepository = $meetingRepository;
        $this->memberRepository = $memberRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createFrom(int $sourceId): ? GroupView
    {
        $group = $this->groupRepository->findById($sourceId);

        if (!$group) {
            return null;
        }

//        $args = [
//            'meta_query' => [
//                [
//                    'key'   => TsmlMemberFields::FIELD_HOME_GROUP,
//                    'value' => $group->getId()
//                ]
//            ]
//        ];
//        $members = $this->memberRepository->findAll($args);

        $all = $this->memberRepository->findAll();

        $members = [];

        foreach ($all as $member) {

            if ($member->getHomeGroup() === $group->getId()) {
                $members[] = $member;
            }

        }

        return new TsmlGroupView(
            $group->getId(),
            $group->getTitle(),
            $group->getEmail(),
            $group->getMeetings(),
            $group->getLink(),
            $group->getContacts(),
            $members
        );
    }

    /**
     * Get meeting objects for a group
     * 
     * @param Group $group The group entity
     * @return array Array of Meeting objects
     */
    private function getMeetingsForGroup(Group $group): array
    {
        $meetingIds = $group->getMeetingIds();
        $meetings = [];

        foreach ($meetingIds as $meetingId) {
            $meeting = get_post($meetingId);
            if ($meeting && $meeting->post_type === 'tsml_meeting') {
                $meetingObj = $this->meetingRepository->findById((int)$meetingId);
                if ($meetingObj) {
                    $meetings[] = $meetingObj;
                }
            }
        }

        return $meetings;
    }
}
