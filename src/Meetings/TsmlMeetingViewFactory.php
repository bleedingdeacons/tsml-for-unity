<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

use Unity\Groups\Interfaces\GroupRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Meetings\Interfaces\MeetingViewFactory;
use Unity\Meetings\Interfaces\MeetingView;
use DateTime;
use Exception;

/**
 * TSML Meeting View Factory
 *
 * Creates MeetingView objects by combining Meeting and member data.
 */
class TsmlMeetingViewFactory implements MeetingViewFactory
{
    private MeetingRepository $meetingRepository;
    private MemberRepository $memberRepository;
    private GroupRepository $groupRepository;

    /**
     * Constructor
     *
     * @param MeetingRepository $meetingRepository Meeting repository
     * @param MemberRepository $memberRepository Member repository
     * @param GroupRepository $groupRepository Group repository
     */
    public function __construct(
        MeetingRepository $meetingRepository,
        MemberRepository $memberRepository,
        GroupRepository $groupRepository
    )
    {
        $this->meetingRepository = $meetingRepository;
        $this->memberRepository = $memberRepository;
        $this->groupRepository = $groupRepository;
    }

    public function createFrom(int $meetingId): ?MeetingView {}

}