<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
            $membersWithLatestDate = $this->findMembersWithLatestRotationDate($matchingMembers);
        } else {
            $membersWithLatestDate = $matchingMembers;
        }

        return new TsmlPositionView($position, $membersWithLatestDate[0], $membersWithLatestDate);
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
                $views[] = new TsmlPositionView($position, null);
            } elseif (count($matchingMembers) > 1) {
                $membersWithLatestDate = $this->findMembersWithLatestRotationDate($matchingMembers);
                $views[] = new TsmlPositionView($position, $membersWithLatestDate[0], $membersWithLatestDate);
            } else {
                $views[] = new TsmlPositionView($position, $matchingMembers[0], $matchingMembers);
            }
        }

        usort($views, function(PositionView $a, PositionView $b) {
            $titleA = $a->getTitle() ?? '';
            $titleB = $b->getTitle() ?? '';

            return strcasecmp($titleA, $titleB);
        });

        return $views;
    }

    /**
     * Find all members that share the latest rotation date from a list of members
     *
     * When multiple members have the same (latest) rotation date, all are returned.
     *
     * @param array $members Array of Member objects
     * @return array Array of Member objects with the latest rotation date
     */
    private function findMembersWithLatestRotationDate(array $members): array
    {
        $latestDate = null;
        $latestMembers = [$members[0]];

        foreach ($members as $member) {
            $rotationDateStr = $member->getIntergroupPositionRotation();

            if (empty($rotationDateStr)) {
                continue;
            }

            try {
                // Value is Y-m-d (normalised at factory level). Fall back to
                // d/m/Y for any legacy data that hasn't been re-saved.
                $rotationDate = DateTime::createFromFormat('Y-m-d', $rotationDateStr)
                    ?: DateTime::createFromFormat('d/m/Y', $rotationDateStr);

                if (!$rotationDate) {
                    continue;
                }

                if ($latestDate === null) {
                    $latestDate = $rotationDate;
                    $latestMembers = [$member];
                    continue;
                }

                if ($rotationDate > $latestDate) {
                    $latestDate = $rotationDate;
                    $latestMembers = [$member];
                } elseif ($rotationDate == $latestDate) {
                    $latestMembers[] = $member;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $latestMembers;
    }
}