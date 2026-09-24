<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Meetings\TsmlMeeting;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use Unity\Contacts\Interfaces\Contact;

covers(\TsmlForUnity\Meetings\TsmlMeetingFactory::class);

beforeEach(function () {
    $this->factory = new TsmlMeetingFactory();
});

test('create from source returns null for empty source', function () {
    $result = $this->factory->createFromSource([]);
    expect($result)->toBeNull();
});

test('create from source returns null for missing required fields', function () {
    $source = [
        'id' => 123,
        'name' => 'Test Meeting',
        // Missing 'slug' and 'location'
    ];

    $result = $this->factory->createFromSource($source);
    expect($result)->toBeNull();
});

test('create from source returns meeting for valid source', function () {
    $source = [
        'id' => 123,
        'name' => 'Morning Serenity',
        'slug' => 'morning-serenity',
        'location' => 'Community Center',
        'day' => 1,
        'time' => '07:00',
        'end_time' => '08:00',
        'types' => ['O', 'D'],
        'attendance_option' => 'in_person',
    ];

    // Mock WordPress functions
    Functions\expect('get_permalink')
        ->once()
        ->with(123)
        ->andReturn('https://example.com/meetings/morning-serenity/');

    Functions\expect('get_post_status')
        ->once()
        ->with(123)
        ->andReturn('publish');

    Functions\expect('get_post_custom')
        ->once()
        ->with(123)
        ->andReturn([]);

    Functions\expect('is_serialized')
        ->andReturn(false);

    stubPostLookups(123);

    $result = $this->factory->createFromSource($source);

    expect($result)->toBeInstanceOf(TsmlMeeting::class)
        ->and($result->getId())->toEqual(123)
        ->and($result->getName())->toEqual('Morning Serenity')
        ->and($result->getSlug())->toEqual('morning-serenity')
        ->and($result->getLocation()->getName())->toBe('Community Center')
        ->and($result->getDayOfWeek())->toEqual('Monday')
        ->and($result->getTime())->toEqual('07:00')
        ->and($result->getEndTime())->toEqual('08:00')
        ->and($result->getTypes())->toContain('Open')
        ->and($result->getTypes())->toContain('Discussion')
        ->and($result->isOnline())->toBeFalse();

    // Pin the fields that map to the constructor's remaining positional
    // slots. Without these, a mid-list parameter insertion could rebind
    // url/state/day/meta/updated to the wrong value and still pass:
    // PHPStan cannot catch a same-typed swap, and these getters were
    // previously unasserted.
    expect($result->getUrl())->toBe('https://example.com/meetings/morning-serenity/')
        ->and($result->getState())->toBe('publish')
        ->and($result->getDay())->toBe(1)
        ->and($result->getMeta())->toBe([])
        ->and($result->getUpdated())->toBe('2024-01-01 00:00:00');
});

test('create from source handles online meeting', function () {
    $source = [
        'id' => 456,
        'name' => 'Online Meeting',
        'slug' => 'online-meeting',
        'location' => 'Zoom',
        'day' => 3,
        'time' => '19:00',
        'types' => ['O', 'VM'],
        'attendance_option' => 'online',
    ];

    Functions\expect('get_permalink')
        ->once()
        ->with(456)
        ->andReturn('https://example.com/meetings/online-meeting/');

    Functions\expect('get_post_status')
        ->once()
        ->with(456)
        ->andReturn('publish');

    Functions\expect('get_post_custom')
        ->once()
        ->with(456)
        ->andReturn([
            'conference_url' => ['https://zoom.us/j/123456789'],
            'conference_url_notes' => ['Password: 12345'],
        ]);

    Functions\expect('is_serialized')
        ->andReturn(false);

    stubPostLookups(456);

    $result = $this->factory->createFromSource($source);

    expect($result)->toBeInstanceOf(TsmlMeeting::class)
        ->and($result->isOnline())->toBeTrue()
        ->and($result->getOnlineLink())->toEqual('https://zoom.us/j/123456789')
        ->and($result->getOnlineNotes())->toEqual('Password: 12345');
});

test('get type name returns correct name', function () {
    expect($this->factory->getTypeName('O'))->toEqual('Open')
        ->and($this->factory->getTypeName('C'))->toEqual('Closed')
        ->and($this->factory->getTypeName('D'))->toEqual('Discussion')
        ->and($this->factory->getTypeName('B'))->toEqual('Big Book')
        ->and($this->factory->getTypeName('X'))->toEqual('Wheelchair Access');
});

test('get type name returns null for unknown code', function () {
    expect($this->factory->getTypeName('UNKNOWN'))->toBeNull();
});

test('get type code returns correct code', function () {
    expect($this->factory->getTypeCode('Open'))->toEqual('O')
        ->and($this->factory->getTypeCode('Closed'))->toEqual('C')
        ->and($this->factory->getTypeCode('Discussion'))->toEqual('D');
});

test('get type code returns null for unknown name', function () {
    expect($this->factory->getTypeCode('Unknown Type'))->toBeNull();
});

test('get all types returns array', function () {
    $types = $this->factory->getAllTypes();

    expect($types)->toBeArray()
        ->and($types)->not->toBeEmpty()
        ->and($types)->toHaveKey('O')
        ->and($types)->toHaveKey('C')
        ->and($types['O'])->toEqual('Open')
        ->and($types['C'])->toEqual('Closed');
});

test('get day name returns correct day', function () {
    expect($this->factory->getDayName(0))->toEqual('Sunday')
        ->and($this->factory->getDayName(1))->toEqual('Monday')
        ->and($this->factory->getDayName(2))->toEqual('Tuesday')
        ->and($this->factory->getDayName(3))->toEqual('Wednesday')
        ->and($this->factory->getDayName(4))->toEqual('Thursday')
        ->and($this->factory->getDayName(5))->toEqual('Friday')
        ->and($this->factory->getDayName(6))->toEqual('Saturday');
});

test('get day name returns null for invalid day', function () {
    expect($this->factory->getDayName(7))->toBeNull()
        ->and($this->factory->getDayName(-1))->toBeNull();
});

test('create from source extracts contacts', function () {
    $source = [
        'id' => 789,
        'name' => 'Test Meeting',
        'slug' => 'test-meeting',
        'location' => 'Test Location',
    ];

    Functions\expect('get_permalink')
        ->once()
        ->with(789)
        ->andReturn('https://example.com/meetings/test-meeting/');

    Functions\expect('get_post_status')
        ->once()
        ->with(789)
        ->andReturn('publish');

    Functions\expect('get_post_custom')
        ->once()
        ->with(789)
        ->andReturn([
            'contact_1_name' => ['John Doe'],
            'contact_1_email' => ['john@example.com'],
            'contact_1_phone' => ['555-1234'],
            'contact_2_name' => ['Jane Smith'],
            'contact_2_email' => ['jane@example.com'],
            'contact_2_phone' => ['555-5678'],
        ]);

    Functions\expect('is_serialized')
        ->andReturn(false);

    stubPostLookups(789);

    $result = $this->factory->createFromSource($source);

    expect($result)->toBeInstanceOf(TsmlMeeting::class);

    $contacts = $result->getContacts();
    expect($contacts)->toHaveCount(2);

    expect($contacts[0])->toBeInstanceOf(Contact::class)
        ->and($contacts[0]->getName())->toEqual('John Doe')
        ->and($contacts[0]->getEmail())->toEqual('john@example.com')
        ->and($contacts[0]->getPhone())->toEqual('555-1234');

    expect($contacts[1])->toBeInstanceOf(Contact::class)
        ->and($contacts[1]->getName())->toEqual('Jane Smith');
});

/**
 * Stub the lookups createFromSource needs beyond the per-test ones.
 *
 * The factory guards on is_wp_error and get_post_meta existing before it
 * will build anything, and reads post_modified_gmt off get_post, so a
 * source that should succeed still yields null without these.
 *
 * @param int $id Meeting post ID.
 * @return void
 */
function stubPostLookups(int $id): void
{
    Functions\expect('get_post')
        ->with($id)
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);

    Functions\expect('get_post_meta')
        ->andReturn('');
}
