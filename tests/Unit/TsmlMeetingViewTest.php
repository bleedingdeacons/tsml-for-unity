<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Meetings\TsmlMeetingView;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingView;
use Unity\Members\Interfaces\Member;

/*
 * Tests for TsmlMeetingView
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingView::class);

it('implements meeting view interface', function () {
    $view = new TsmlMeetingView($this->createMock(Meeting::class), []);

    expect($view)->toBeInstanceOf(MeetingView::class);
});

it('exposes the meeting and members', function () {
    $meeting = $this->createMock(Meeting::class);
    $memberA = $this->createMock(Member::class);
    $memberB = $this->createMock(Member::class);

    $view = new TsmlMeetingView($meeting, [$memberA, $memberB]);

    expect($view->getMeeting())->toBe($meeting)
        ->and($view->getMembers())->toBe([$memberA, $memberB]);
});

/*
 * getGsrNames() maps each associated member to its name via the Member
 * contract (getAnonymousName()).
 */
test('gsr names collects each members name', function () {
    $memberA = $this->createMock(Member::class);
    $memberA->method('getAnonymousName')->willReturn('Alice A.');
    $memberB = $this->createMock(Member::class);
    $memberB->method('getAnonymousName')->willReturn('Bob B.');

    $view = new TsmlMeetingView($this->createMock(Meeting::class), [$memberA, $memberB]);

    expect($view->getGsrNames())->toBe(['Alice A.', 'Bob B.']);
});

test('gsr names is empty for a meeting with no members', function () {
    $view = new TsmlMeetingView($this->createMock(Meeting::class), []);

    expect($view->getGsrNames())->toBe([]);
});
