<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use Exception;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;

/*
 * Guard and failure paths for the intergroup meeting change tracker.
 *
 * Complements TsmlIntergroupMeetingChangeTrackerTest, which covers the
 * ordinary save flow. These are the branches that run when the tracker is
 * handed something it should ignore, or when the repository cannot answer
 * — it hooks ACF's save lifecycle and WordPress's delete routine, so a
 * failure has to be contained rather than propagated.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker::class);

beforeEach(function () {
    $this->repository = $this->createMock(IntergroupMeetingRepository::class);
    $this->tracker = new TsmlIntergroupMeetingChangeTracker($this->repository);
});

afterEach(function () {
    (new \ReflectionClass(TsmlIntergroupMeetingChangeTracker::class))
        ->getProperty('originalMeeting')->setValue(null, null);
});

function failingIntergroupMeeting(): IntergroupMeeting
{
    return test()->createMock(IntergroupMeeting::class);
}

test('capturing a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalMeeting(3);

    // Returned before reading the meeting.
});

test('a capture failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
    $this->repository->method('findById')->willThrowException(new Exception('boom'));

    $this->tracker->captureOriginalMeeting(3);

    // A failed capture must not abort the save.
})->throwsNoExceptions();

test('checking a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(3);

    // Returned before comparing.
});

test('a check without a captured original stops quietly', function () {
    Functions\expect('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
    // No captureOriginalMeeting() call, so there is nothing to compare.
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(3);

    // No comparison without a snapshot.
});

test('a check that cannot reload the meeting stops quietly', function () {
    Functions\expect('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);

    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(failingIntergroupMeeting(), null);

    $this->tracker->captureOriginalMeeting(3);
    $this->tracker->checkForChanges(3);

    // No event fired without an updated meeting.
})->throwsNoExceptions();

test('a check failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);

    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(
            failingIntergroupMeeting(),
            $this->throwException(new Exception('boom'))
        );

    $this->tracker->captureOriginalMeeting(3);
    $this->tracker->checkForChanges(3);

    // A failed check must not abort the save.
})->throwsNoExceptions();

test('deleting a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onIntergroupMeetingDeleted(3);

    // Only intergroup meetings raise the event.
});

test('a repository failure during deletion is contained', function () {
    Functions\expect('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
    $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

    $this->tracker->onIntergroupMeetingDeleted(3);

    // The exception did not escape the delete routine.
})->throwsNoExceptions();
