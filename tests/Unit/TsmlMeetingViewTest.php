<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingView;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingView;
use Unity\Members\Interfaces\Member;

/**
 * Tests for TsmlMeetingView
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingView
 */
class TsmlMeetingViewTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_meeting_view_interface(): void
    {
        $view = new TsmlMeetingView($this->createMock(Meeting::class), []);

        $this->assertInstanceOf(MeetingView::class, $view);
    }

    /**
     * @test
     */
    public function it_exposes_the_meeting_and_members(): void
    {
        $meeting = $this->createMock(Meeting::class);
        $memberA = $this->createMock(Member::class);
        $memberB = $this->createMock(Member::class);

        $view = new TsmlMeetingView($meeting, [$memberA, $memberB]);

        $this->assertSame($meeting, $view->getMeeting());
        $this->assertSame([$memberA, $memberB], $view->getMembers());
    }

    /**
     * getGsrNames() calls getGsrName() on each entry. That method lives on
     * IntergroupMeetingGroupAttendance rather than Member (see the note in
     * the view), so the collaborators here expose it.
     *
     * @test
     */
    public function gsr_names_collects_each_members_gsr_name(): void
    {
        $memberA = $this->createMock(IntergroupMeetingGroupAttendance::class);
        $memberA->method('getGsrName')->willReturn('Alice A.');
        $memberB = $this->createMock(IntergroupMeetingGroupAttendance::class);
        $memberB->method('getGsrName')->willReturn('Bob B.');

        $view = new TsmlMeetingView($this->createMock(Meeting::class), [$memberA, $memberB]);

        $this->assertSame(['Alice A.', 'Bob B.'], $view->getGsrNames());
    }

    /**
     * @test
     */
    public function gsr_names_is_empty_for_a_meeting_with_no_members(): void
    {
        $view = new TsmlMeetingView($this->createMock(Meeting::class), []);

        $this->assertSame([], $view->getGsrNames());
    }
}
