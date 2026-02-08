<?php

declare(strict_types=1);

namespace TsmlForUnity;

use Unity\Members\Interfaces\MemberInterface;
use Unity\Members\Interfaces\MemberRepositoryInterface;
use Unity\Positions\Interfaces\PositionRepositoryInterface;
use Unity\Positions\Interfaces\PositionViewFactoryInterface;
use Unity\Positions\Interfaces\PositionViewInterface;
use Unity\Positions\PositionView;
use DateTime;
use Exception;

/**
 * TSML Position View Factory
 *
 * Creates PositionView objects by combining position and member data.
 */
class TsmlPositionViewFactory implements PositionViewFactoryInterface
{
    private PositionRepositoryInterface $positionRepository;
    private MemberRepositoryInterface $memberRepository;

    /**
     * Constructor
     *
     * @param PositionRepositoryInterface $positionRepository Position repository
     * @param MemberRepositoryInterface $memberRepository Member repository
     */
    public function __construct(
        PositionRepositoryInterface $positionRepository,
        MemberRepositoryInterface $memberRepository
    ) {
        $this->positionRepository = $positionRepository;
        $this->memberRepository = $memberRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function createFrom(int $positionId): ?PositionViewInterface
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
            return new PositionView($position, null);
        }

        if (count($members) > 1) {
            $latestMember = $this->findMemberWithLatestRotationDate($members);
        } else {
            $latestMember = $members[0];
        }

        return new PositionView($position, $latestMember);
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

        usort($views, function(PositionViewInterface $a, PositionViewInterface $b) {
            $titleA = $a->getTitle() ?? '';
            $titleB = $b->getTitle() ?? '';

            return strcasecmp($titleA, $titleB);
        });

        return $views;
    }

    /**
     * Find the member with the latest rotation date from a list of members
     *
     * @param array $members Array of MemberInterface objects
     * @return MemberInterface The member with the latest rotation date
     */
    private function findMemberWithLatestRotationDate(array $members): MemberInterface
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
