<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Groups\Interfaces\GroupView;

use function get_post;

/**
 * Concrete TSML Group View Factory class
 */
class TsmlGroupViewFactory implements GroupViewFactory
{
    private GroupRepository $groupRepository;
    private MeetingRepository $meetingRepository;

    /**
     * TsmlGroupViewFactory constructor
     * 
     * @param GroupRepository $groupRepository The group repository
     * @param MeetingRepository $meetingRepository The meeting repository
     */
    public function __construct(
        GroupRepository $groupRepository,
        MeetingRepository $meetingRepository
    ) {
        $this->groupRepository = $groupRepository;
        $this->meetingRepository = $meetingRepository;
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

        $meetings = $this->getMeetingsForGroup($group);

        return new TsmlGroupView(
            $group->getId(),
            $group->getTitle(),
            $group->getEmail(),
            $meetings,
            $group->getLink()
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
                $meetingObj = $this->meetingRepository->find((int)$meetingId);
                if ($meetingObj) {
                    $meetings[] = $meetingObj;
                }
            }
        }

        return $meetings;
    }
}
