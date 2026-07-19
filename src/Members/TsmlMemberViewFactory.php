<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Groups\Interfaces\GroupRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\Interfaces\MemberView;
use Unity\Members\Interfaces\MemberViewFactory;
use Unity\Positions\Interfaces\PositionRepository;

/**
 * Concrete TSML Member View Factory class
 */
class TsmlMemberViewFactory implements MemberViewFactory
{
    private MemberRepository $memberRepository;
    private GroupRepository $groupRepository;
    private PositionRepository $positionRepository;

    /**
     * TsmlMemberViewFactory constructor
     *
     * @param MemberRepository   $memberRepository   Source of member entities
     * @param GroupRepository    $groupRepository    Used to resolve home group names
     * @param PositionRepository $positionRepository Used to resolve position names
     */
    public function __construct(
        MemberRepository $memberRepository,
        GroupRepository $groupRepository,
        PositionRepository $positionRepository
    ) {
        $this->memberRepository = $memberRepository;
        $this->groupRepository = $groupRepository;
        $this->positionRepository = $positionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createFromSource(array $sourceIds): array
    {
        // Per-call name caches. Many members share the same home group
        // and/or position, so resolving each one once per createFromSource
        // call avoids repeating identical repository lookups. Scoped to
        // the call (not the instance) so a long-lived factory doesn't
        // serve stale names after a group is renamed between calls.
        $groupNameCache = [];
        $positionNameCache = [];

        $views = [];

        foreach ($sourceIds as $sourceId) {
            $id = (int) $sourceId;

            if ($id <= 0) {
                continue;
            }

            $member = $this->memberRepository->findById($id);

            if ($member === null) {
                continue;
            }

            $views[] = $this->buildView($member, $groupNameCache, $positionNameCache);
        }

        return $views;
    }

    /**
     * Hydrate a single MemberView from a Member entity, resolving
     * related group and position names via their repositories. The
     * two cache arrays are passed by reference so resolved names are
     * shared across all members built in the same createFromSource call.
     *
     * @param Member               $member
     * @param array<int, string>   $groupNameCache    Map of group ID to resolved name
     * @param array<int, string>   $positionNameCache Map of position ID to resolved name
     * @return MemberView
     */
    private function buildView(Member $member, array &$groupNameCache, array &$positionNameCache): MemberView
    {
        $homeGroupId = $member->getHomeGroup();
        $homeGroupName = '';

        if ($homeGroupId > 0) {
            // array_key_exists rather than isset so that a cached miss
            // (empty string from a deleted group) still short-circuits.
            if (!array_key_exists($homeGroupId, $groupNameCache)) {
                $group = $this->groupRepository->findById($homeGroupId);
                $groupNameCache[$homeGroupId] = $group !== null ? $group->getTitle() : '';
            }
            $homeGroupName = $groupNameCache[$homeGroupId];
        }

        $positionId = $member->getIntergroupPosition();
        $positionName = '';

        if ($positionId > 0) {
            if (!array_key_exists($positionId, $positionNameCache)) {
                $position = $this->positionRepository->findById($positionId);
                // Position::getLongName() is the human-readable title;
                // matches what TsmlPositionViewFactory uses for display.
                $positionNameCache[$positionId] = $position !== null ? $position->getLongName() : '';
            }
            $positionName = $positionNameCache[$positionId];
        }

        return new TsmlMemberView(
            $member->getId(),
            $member->getAnonymousName(),
            $member->getPersonalEmail(),
            $member->getMobileNumber(),
            $homeGroupId,
            $homeGroupName,
            $member->isGSR(),
            $positionId,
            $positionName,
            $member->getIntergroupPositionRotation(),
            $member->isTwelfthStepper(),
            $member->isTelephoneResponder(),
            $member->getResponderCertification(),
            $member->getArea(),
            $member->getAccepts()
        );
    }
}
