<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;

/*
 * Tests for TsmlIntergroupMeeting entity
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting::class);

it('implements the interface', function () {
    expect(new TsmlIntergroupMeeting(id: 1))->toBeInstanceOf(IntergroupMeeting::class);
});

it('exposes constructor values', function () {
    $meeting = new TsmlIntergroupMeeting(
        id: 5,
        title: 'July Intergroup',
        groupAttendees: [10, 20],
        officersAttending: [1, 2],
        date: '2026-07-01',
        updated: '2026-07-01 20:00:00'
    );

    expect($meeting->getId())->toBe(5)
        ->and($meeting->getTitle())->toBe('July Intergroup')
        ->and($meeting->getGroupAttendees())->toBe([10, 20])
        ->and($meeting->getOfficersAttending())->toBe([1, 2])
        ->and($meeting->getDate())->toBe('2026-07-01')
        ->and($meeting->getUpdated())->toBe('2026-07-01 20:00:00');
});

it('defaults collections to empty', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1);

    expect($meeting->getTitle())->toBe('')
        ->and($meeting->getGroupAttendees())->toBe([])
        ->and($meeting->getOfficersAttending())->toBe([])
        ->and($meeting->getDate())->toBe('')
        ->and($meeting->getUpdated())->toBe('');
});

// ── group attendee mutators ────────────────────────────────────────
test('adding a group attendee returns true and records it', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1);

    expect($meeting->addGroupAttendee(10))->toBeTrue()
        ->and($meeting->hasGroupAttendee(10))->toBeTrue()
        ->and($meeting->getGroupAttendees())->toBe([10]);
});

test('adding a duplicate group attendee returns false', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

    expect($meeting->addGroupAttendee(10))->toBeFalse()
        ->and($meeting->getGroupAttendees())->toBe([10]);
});

test('removing a group attendee reindexes the list', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10, 20, 30]);

    expect($meeting->removeGroupAttendee(20))->toBeTrue()
        ->and($meeting->getGroupAttendees())->toBe([10, 30])
        ->and($meeting->hasGroupAttendee(20))->toBeFalse();
});

test('removing an absent group attendee returns false', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

    expect($meeting->removeGroupAttendee(99))->toBeFalse()
        ->and($meeting->getGroupAttendees())->toBe([10]);
});

// ── officer attendee mutators ──────────────────────────────────────
test('adding an officer attendee returns true and records it', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1);

    expect($meeting->addOfficerAttendee(3))->toBeTrue()
        ->and($meeting->hasOfficerAttendee(3))->toBeTrue()
        ->and($meeting->getOfficersAttending())->toBe([3]);
});

test('adding a duplicate officer attendee returns false', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3]);

    expect($meeting->addOfficerAttendee(3))->toBeFalse()
        ->and($meeting->getOfficersAttending())->toBe([3]);
});

test('removing an officer attendee reindexes the list', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3, 4, 5]);

    expect($meeting->removeOfficerAttendee(4))->toBeTrue()
        ->and($meeting->getOfficersAttending())->toBe([3, 5])
        ->and($meeting->hasOfficerAttendee(4))->toBeFalse();
});

test('removing an absent officer attendee returns false', function () {
    $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3]);

    expect($meeting->removeOfficerAttendee(99))->toBeFalse()
        ->and($meeting->getOfficersAttending())->toBe([3]);
});
