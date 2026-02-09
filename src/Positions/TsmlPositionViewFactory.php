<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

use TsmlForUnity\Members\TsmlMemberFields;
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

        $value = serialize([strval($positionId)]);

        $members = $this->memberRepository->findAll([
            'meta_query' => [
                [
                    'key' => TsmlMemberFields::FIELD_INTERGROUP_POSITION,
                    'value' => $value,
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($members)) {
            return new TsmlPositionView($position, null);
        }

        if (count($members) > 1) {
            $latestMember = $this->findMemberWithLatestRotationDate($members);
        } else {
            $latestMember = $members[0];
        }

        return new TsmlPositionView($position, $latestMember);
    }

    /**
     * {@inheritdoc}
     */
    public function createAll(array $args = []): array
    {
        $positions = $this->positionRepository->findAll($args);
        $views = [];

        foreach ($positions as $position) {
            $positionId = $position->getId();
            $view = $this->createFrom($positionId);

            if ($view !== null) {
                $views[] = $view;
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
