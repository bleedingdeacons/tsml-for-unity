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
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\Groups\Interfaces\GroupView;

use Unity\Members\Interfaces\MemberRepository;

/**
 * Concrete TSML Group View Factory class
 */
class TsmlGroupViewFactory implements GroupViewFactory
{
    private GroupRepository $groupRepository;
    private MemberRepository $memberRepository;

    /**
     * TsmlGroupViewFactory constructor
     *
     * @param GroupRepository $groupRepository The group repository
     * @param MemberRepository $memberRepository The member repository
     */
    public function __construct(
        GroupRepository $groupRepository,
        MemberRepository $memberRepository
    ) {
        $this->groupRepository = $groupRepository;
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
}
