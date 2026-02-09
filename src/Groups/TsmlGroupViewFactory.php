<?php

declare(strict_types=1);

namespace TsmlForUnity\Groups;

use Unity\Groups\Interfaces\GroupInterface;
use Unity\Groups\Interfaces\GroupRepositoryInterface;
use Unity\Groups\Interfaces\GroupViewFactoryInterface;
use Unity\Meetings\Interfaces\MeetingRepositoryInterface;
use Unity\Groups\Interfaces\GroupViewInterface;

use function get_post;

/**
 * Concrete TSML Group View Factory class
 */
class TsmlGroupViewFactory implements GroupViewFactoryInterface
{
    private GroupRepositoryInterface $groupRepository;
    private MeetingRepositoryInterface $meetingRepository;

    /**
     * TsmlGroupViewFactory constructor
     * 
     * @param GroupRepositoryInterface $groupRepository The group repository
     * @param MeetingRepositoryInterface $meetingRepository The meeting repository
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        MeetingRepositoryInterface $meetingRepository
    ) {
        $this->groupRepository = $groupRepository;
        $this->meetingRepository = $meetingRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createFrom(int $sourceId): ? GroupViewInterface
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
     * @param GroupInterface $group The group entity
     * @return array Array of Meeting objects
     */
    private function getMeetingsForGroup(GroupInterface $group): array
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
