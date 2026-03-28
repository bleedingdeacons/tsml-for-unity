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
            $latestMembers = $this->findMembersWithLatestRotationDate($matchingMembers);
            return new TsmlPositionView($position, $latestMembers[0], $latestMembers);
        }

        return new TsmlPositionView($position, $matchingMembers[0]);
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
                $members = [];
            } elseif (count($matchingMembers) > 1) {
                $members = $this->findMembersWithLatestRotationDate($matchingMembers);
                $member = $members[0];
            } else {
                $member = $matchingMembers[0];
                $members = [$member];
            }

            $views[] = new TsmlPositionView($position, $member, $members);
        }

        usort($views, function(PositionView $a, PositionView $b) {
            $titleA = $a->getTitle() ?? '';
            $titleB = $b->getTitle() ?? '';

            return strcasecmp($titleA, $titleB);
        });

        return $views;
    }

    /**
     * Find all members sharing the latest rotation date from a list of members.
     *
     * Parses each member's rotation date, determines the overall latest date,
     * then returns every member whose date matches. If no dates are parseable
     * the first member is returned as a single-element array.
     *
     * @param array<Member> $members Array of Member objects (must not be empty)
     * @return array<Member> Members with the latest rotation date
     */
    private function findMembersWithLatestRotationDate(array $members): array
    {
        $latestDateStr = null;
        $parsed = []; // memberId => normalised Y-m-d string

        foreach ($members as $member) {
            $rotationDateStr = $member->getIntergroupPositionRotation();

            if (empty($rotationDateStr)) {
                continue;
            }

            try {
                $dt = DateTime::createFromFormat('Y-m-d', $rotationDateStr)
                    ?: DateTime::createFromFormat('d/m/Y', $rotationDateStr);

                if (!$dt) {
                    continue;
                }

                $normalised = $dt->format('Y-m-d');
                $parsed[$member->getId()] = $normalised;

                if ($latestDateStr === null || $normalised > $latestDateStr) {
                    $latestDateStr = $normalised;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        if ($latestDateStr === null) {
            return [$members[0]];
        }

        $result = [];
        foreach ($members as $member) {
            if (isset($parsed[$member->getId()]) && $parsed[$member->getId()] === $latestDateStr) {
                $result[] = $member;
            }
        }

        return $result;
    }
}