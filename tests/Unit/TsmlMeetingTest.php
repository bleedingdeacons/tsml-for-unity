<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Locations\TsmlLocation;
use TsmlForUnity\Meetings\TsmlMeeting;
use Unity\Meetings\Interfaces\Meeting;

/*
 * Tests for TsmlMeeting entity
 */

covers(\TsmlForUnity\Meetings\TsmlMeeting::class);

it('implements meeting interface', function () {
    expect(minimalMeeting())->toBeInstanceOf(Meeting::class);
});

it('exposes every required field', function () {
    $location = new TsmlLocation(id: 3, name: 'Hall');

    $meeting = new TsmlMeeting(
        id: 10,
        name: 'Monday Nooners',
        slug: 'monday-nooners',
        location: $location,
        url: 'https://example.com/meeting',
        day: 1,
        dayOfWeek: 'Monday',
        time: '12:00',
        endTime: '13:00',
        types: ['O', 'D'],
        state: 'active',
        online: true,
        contacts: ['c1'],
        meta: ['key' => 'value'],
        onlineLink: 'https://zoom.example/1',
        onlineNotes: 'Password 123',
        updated: '2026-06-01 10:00:00'
    );

    expect($meeting->getId())->toBe(10)
        ->and($meeting->getName())->toBe('Monday Nooners')
        ->and($meeting->getSlug())->toBe('monday-nooners')
        ->and($meeting->getLocation())->toBe($location)
        ->and($meeting->getUrl())->toBe('https://example.com/meeting')
        ->and($meeting->getDay())->toBe(1)
        ->and($meeting->getDayOfWeek())->toBe('Monday')
        ->and($meeting->getTime())->toBe('12:00')
        ->and($meeting->getEndTime())->toBe('13:00')
        ->and($meeting->getTypes())->toBe(['O', 'D'])
        ->and($meeting->getState())->toBe('active')
        ->and($meeting->isOnline())->toBeTrue()
        ->and($meeting->getContacts())->toBe(['c1'])
        ->and($meeting->getMeta())->toBe(['key' => 'value'])
        ->and($meeting->getOnlineLink())->toBe('https://zoom.example/1')
        ->and($meeting->getOnlineNotes())->toBe('Password 123')
        ->and($meeting->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('optional trailing fields default to empty', function () {
    $meeting = minimalMeeting();

    expect($meeting->getContacts())->toBe([])
        ->and($meeting->getMeta())->toBe([])
        ->and($meeting->getOnlineLink())->toBe('')
        ->and($meeting->getOnlineNotes())->toBe('')
        ->and($meeting->getUpdated())->toBe('');
});

test('location may be null', function () {
    expect(minimalMeeting()->getLocation())->toBeNull();
});

test('offline meeting reports not online', function () {
    expect(minimalMeeting()->isOnline())->toBeFalse();
});

function minimalMeeting(): TsmlMeeting
{
    return new TsmlMeeting(
        id: 1,
        name: 'Meeting',
        slug: 'meeting',
        location: null,
        url: '',
        day: 0,
        dayOfWeek: 'Sunday',
        time: '18:00',
        endTime: '19:00',
        types: [],
        state: 'active',
        online: false
    );
}
