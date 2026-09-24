<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;

/*
 * Tests for TsmlIntergroupMeetingChangeTracker.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker::class);

uses(ActionExpectations::class);

beforeEach(function () {
    $this->repository = $this->createMock(IntergroupMeetingRepository::class);
    $this->tracker = new TsmlIntergroupMeetingChangeTracker($this->repository);
});

afterEach(function () {
    (new \ReflectionClass(TsmlIntergroupMeetingChangeTracker::class))
        ->getProperty('originalMeeting')->setValue(null, null);
});

function stubIntergroupMeetingPostTypeGuard(int $postId): void
{
    Functions\expect('get_post_type')
        ->with($postId)
        ->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
}

function stubIntergroupMeetingTitleSyncIsNoop(int $postId, string $title): void
{
    Functions\expect('get_post')
        ->with($postId)
        ->andReturn((object) ['ID' => $postId, 'post_title' => $title]);
}

function trackedIntergroupMeeting(array $overrides = []): TsmlIntergroupMeeting
{
    return new TsmlIntergroupMeeting(...array_merge([
        'id' => 42, 'title' => 'July Intergroup', 'date' => '2026-07-01',
    ], $overrides));
}

/**
 * @return array{TsmlIntergroupMeeting, TsmlIntergroupMeeting}
 */
function runIntergroupMeetingSave(TsmlIntergroupMeeting $original, TsmlIntergroupMeeting $updated): array
{
    stubIntergroupMeetingPostTypeGuard(42);
    stubIntergroupMeetingTitleSyncIsNoop(42, 'July Intergroup');

    test()->repository->expects(test()->exactly(2))
        ->method('findById')
        ->with(42)
        ->willReturnOnConsecutiveCalls($original, $updated);

    return [$original, $updated];
}

test('changing the title fires the changing hook', function () {
    [$original, $updated] = runIntergroupMeetingSave(
        trackedIntergroupMeeting(['title' => 'July Intergroup']),
        trackedIntergroupMeeting(['title' => 'August Intergroup'])
    );

    // Title changed, so the title-sync path runs; allow the update.
    Functions\expect('wp_update_post')->andReturn(42);

    expectDone('unity/intergroup_meeting_before_save')->once()->with(42, $original);
    expectDone('unity/intergroup_meeting_changing')->once()->with($updated, $original);
    expectDone('unity/intergroup_meeting_changed')->once()->with(42, $updated, $original);

    $this->tracker->captureOriginalMeeting(42);
    $this->tracker->checkForChanges(42);
});

test('each tracked field triggers a change', function (array $originalArgs, array $updatedArgs) {
    [$original, $updated] = runIntergroupMeetingSave(trackedIntergroupMeeting($originalArgs), trackedIntergroupMeeting($updatedArgs));

    expectDone('unity/intergroup_meeting_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalMeeting(42);
    $this->tracker->checkForChanges(42);
})->with([
    'date'      => [['date' => '2026-07-01'], ['date' => '2026-08-01']],
    'add group' => [['groupAttendees' => [1]], ['groupAttendees' => [1, 2]]],
    'add officer' => [['officersAttending' => [3]], ['officersAttending' => [3, 4]]],
]);

test('reordered attendees are not a change', function () {
    $original = trackedIntergroupMeeting(['groupAttendees' => [1, 2], 'officersAttending' => [3, 4]]);
    $updated  = trackedIntergroupMeeting(['groupAttendees' => [2, 1], 'officersAttending' => [4, 3]]);

    stubIntergroupMeetingPostTypeGuard(42);
    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with(42)
        ->willReturnOnConsecutiveCalls($original, $updated);

    $this->expectActionNotFired('unity/intergroup_meeting_changing', $updated, $original);
    expectDone('unity/intergroup_meeting_changed')->once()->with(42, $updated, $original);

    $this->tracker->captureOriginalMeeting(42);
    $this->tracker->checkForChanges(42);
});

test('capture ignores a non meeting post type', function () {
    Functions\expect('get_post_type')->with(99)->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalMeeting(99);
});

test('deleting fires the deleted hook with the meeting', function () {
    $meeting = trackedIntergroupMeeting();
    stubIntergroupMeetingPostTypeGuard(42);
    $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($meeting);

    expectDone('unity/intergroup_meeting_deleted')->once()->with(42, $meeting);

    $this->tracker->onIntergroupMeetingDeleted(42);
});

test('deletion fires with null when the lookup throws', function () {
    stubIntergroupMeetingPostTypeGuard(42);
    $this->repository->expects($this->once())
        ->method('findById')
        ->willThrowException(new \RuntimeException('gone'));

    expectDone('unity/intergroup_meeting_deleted')->once()->with(42, null);

    $this->tracker->onIntergroupMeetingDeleted(42);
});

test('deleting ignores a non meeting post type', function () {
    Functions\expect('get_post_type')->with(99)->andReturn('post');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onIntergroupMeetingDeleted(99);
});
