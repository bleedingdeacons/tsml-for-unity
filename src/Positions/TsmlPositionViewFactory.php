<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\PositionRepository;
use Unity\Positions\Interfaces\PositionViewFactory;
use Unity\Positions\Interfaces\PositionView;
use DateTime;
use Exception;

/**
 * TSML Position View Factory
 *
 * Creates PositionView objects by combining position and member data.
 */
class TsmlPositionViewFactory implements PositionViewFactory
{
    private PositionRepository $positionRepository;
    private MemberRepository $memberRepository;

    /**
     * Constructor
     *
     * @param PositionRepository $positionRepository Position repository
     * @param MemberRepository $memberRepository Member repository
     */
    public function __construct(
        PositionRepository $positionRepository,
        MemberRepository $memberRepository
    ) {
        $this->positionRepository = $positionRepository;
        $this->memberRepository = $memberRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createFrom(int $positionId): ?PositionView
    {
        $position = $this->positionRepository->findById($positionId);

        if ($position === null) {
            return null;
        }

        $allMembers = $this->memberRepository->findAll();
        $matchingMembers = array_filter($allMembers, function (Member $member) use ($positionId) {
            return $member->getIntergroupPosition() === $positionId;
        });

        if (empty($matchingMembers)) {
            return new TsmlPositionView($position, null);
        }

        $matchingMembers = array_values($matchingMembers);

        if (count($matchingMembers) > 1) {
            $latestMember = $this->findMemberWithLatestRotationDate($matchingMembers);
        } else {
            $latestMember = $matchingMembers[0];
        }

        return new TsmlPositionView($position, $latestMember);
    }

    /**
     * {@inheritdoc}
     */
    public function createAll(array $args = []): array
    {
        $positions = $this->positionRepository->findAll($args);
        $allMembers = $this->memberRepository->findAll();
        $views = [];

        foreach ($positions as $position) {
            $positionId = $position->getId();

            $matchingMembers = array_values(array_filter($allMembers, function (Member $member) use ($positionId) {
                return $member->getIntergroupPosition() === $positionId;
            }));

            if (empty($matchingMembers)) {
                $member = null;
            } elseif (count($matchingMembers) > 1) {
                $member = $this->findMemberWithLatestRotationDate($matchingMembers);
            } else {
                $member = $matchingMembers[0];
            }

            $views[] = new TsmlPositionView($position, $member);
        }

        usort($views, function(PositionView $a, PositionView $b) {
            $titleA = $a->getTitle() ?? '';
            $titleB = $b->getTitle() ?? '';

            return strcasecmp($titleA, $titleB);
        });

        return $views;
    }

    /**
     * Find the member with the latest rotation date from a list of members
     *
     * @param array $members Array of Member objects
     * @return Member The member with the latest rotation date
     */
    private function findMemberWithLatestRotationDate(array $members): Member
    {
        $latestMember = $members[0];
        $latestDate = null;

        foreach ($members as $member) {
            $rotationDateStr = $member->getIntergroupPositionRotation();

            if (empty($rotationDateStr)) {
                continue;
            }

            try {
                $rotationDate = new DateTime($rotationDateStr);

                if ($latestDate === null) {
                    $latestDate = $rotationDate;
                    $latestMember = $member;
                    continue;
                }

                if ($rotationDate > $latestDate) {
                    $latestDate = $rotationDate;
                    $latestMember = $member;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $latestMember;
    }
}