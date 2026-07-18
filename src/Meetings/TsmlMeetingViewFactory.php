<?php

declare(strict_types=1);

namespace TsmlForUnity\Meetings;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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

    /**
     * Not implemented.
     *
     * This class satisfies Unity's MeetingViewFactory contract but has never
     * been finished: the body was empty, so any call fell off the end of a
     * method declared to return ?MeetingView and died with a bare TypeError
     * ("Return value must be of type ... none returned"). Nothing registers
     * this factory in the container and nothing calls it, so that has never
     * fired — but the next person to wire it up deserves to be told why it
     * does not work, rather than to debug a type error.
     *
     * Implement it or delete the class; do not leave it silently returning
     * nothing.
     *
     * @throws \LogicException Always.
     */
    public function createFrom(int $meetingId): ?MeetingView
    {
        throw new \LogicException(
            self::class . '::createFrom() is not implemented. '
            . 'This factory is not registered in Unity\'s container; '
            . 'implement it before wiring it up, or remove the class.'
        );
    }

}