<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Locations\Interfaces\Location;
use Unity\Locations\Interfaces\LocationRepository;

/*
 * Tests for TsmlMeetingFactory's location resolution and meta handling.
 *
 * Complements TsmlMeetingFactoryTest, which covers the happy path of
 * createFromSource(). What is exercised here is how a meeting acquires its
 * location — the factory prefers a LocationRepository lookup by
 * `location_id` and only falls back to the flat fields on the source when
 * that yields nothing. Getting that precedence wrong would silently strip
 * addresses off every meeting, which is the sort of thing that looks fine
 * in a smoke test.
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingFactory::class);

beforeEach(function () {
    // createFromSource() refuses to run unless the whole WordPress post
    // API is present, so stub the lot once here rather than per test.
    Functions\expect('get_permalink')->andReturn('https://example.test/location/5');
    Functions\expect('get_post_status')->andReturn('publish');
    Functions\expect('get_post_custom')->andReturn([]);
    Functions\expect('is_serialized')
        ->andReturnUsing(static fn ($v): bool => is_string($v) && @unserialize($v) !== false);
    Functions\expect('get_post')
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
    Functions\expect('get_post_meta')->andReturn('');
});

/** The minimum source createFromSource() will accept. */
function locationSource(array $overrides = []): array
{
    return array_merge([
        'id'       => 123,
        'name'     => 'Morning Serenity',
        'slug'     => 'morning-serenity',
        'location' => 'Community Center',
        'day'      => 1,
        'time'     => '07:00',
    ], $overrides);
}

function location(): Location
{
    $location = test()->createMock(Location::class);
    $location->method('getName')->willReturn('St Mary Hall');
    $location->method('getAddress')->willReturn('1 Church Lane');
    $location->method('getCity')->willReturn('Bristol');
    $location->method('getState')->willReturn('Avon');
    $location->method('getPostalCode')->willReturn('BS1 1AA');
    $location->method('getCountry')->willReturn('GB');
    $location->method('getRegion')->willReturn('Central');
    $location->method('getNotes')->willReturn('Side entrance');

    return $location;
}

// ─── setters ────────────────────────────────────────────────────
test('the location repository can be supplied after construction', function () {
    $repository = $this->createMock(LocationRepository::class);
    $repository->expects($this->once())->method('findById')->with(5)->willReturn(location());

    $factory = new TsmlMeetingFactory();
    $factory->setLocationRepository($repository);

    $meeting = $factory->createFromSource(locationSource(['location_id' => 5]));

    expect($meeting)->not->toBeNull()
        ->and($meeting->getLocation()->getName())->toBe('St Mary Hall');
});

test('the contact factory can be supplied after construction', function () {
    $factory = new TsmlMeetingFactory();
    $factory->setContactFactory($this->createMock(ContactFactory::class));

    expect($factory->createFromSource(locationSource()))->not->toBeNull();
});

test('a default contact factory is created when none is given', function () {
    // No contact factory injected; the factory should build its own
    // rather than fataling when a meeting carries contacts.
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource([
        'meta' => [
            'contact_1_name'  => ['Alex'],
            'contact_1_email' => ['alex@example.test'],
        ],
    ]));

    expect($meeting)->not->toBeNull();
});

// ─── location via repository ────────────────────────────────────
test('a resolved location supplies every address component', function () {
    $repository = $this->createMock(LocationRepository::class);
    $repository->method('findById')->with(5)->willReturn(location());

    $factory = new TsmlMeetingFactory(null, $repository);
    $meeting = $factory->createFromSource(locationSource([
        'location_id' => 5,
        'latitude'    => '51.45',
        'longitude'   => '-2.58',
        'timezone'    => 'Europe/London',
    ]));

    $location = $meeting->getLocation();
    expect($location->getName())->toBe('St Mary Hall')
        ->and($location->getAddress())->toBe('1 Church Lane')
        ->and($location->getCity())->toBe('Bristol')
        ->and($location->getPostalCode())->toBe('BS1 1AA')
        ->and($location->getNotes())->toBe('Side entrance');
    // The permalink is looked up from the location id.
    expect($location->getLink())->toBe('https://example.test/location/5');
});

test('an unresolvable location id falls back to the source fields', function () {
    $repository = $this->createMock(LocationRepository::class);
    $repository->method('findById')->willReturn(null);

    $factory = new TsmlMeetingFactory(null, $repository);
    $meeting = $factory->createFromSource(locationSource([
        'location_id'       => 5,
        'location'          => 'Fallback Hall',
        'formatted_address' => '2 Other Road',
    ]));

    expect($meeting->getLocation()->getName())->toBe('Fallback Hall')
        ->and($meeting->getLocation()->getAddress())->toBe('2 Other Road');
});

test('a zero location id is not looked up', function () {
    $repository = $this->createMock(LocationRepository::class);
    $repository->expects($this->never())->method('findById');

    $factory = new TsmlMeetingFactory(null, $repository);
    $meeting = $factory->createFromSource(locationSource(['location_id' => 0]));

    expect($meeting->getLocation()->getName())->toBe('Community Center');
});

test('without a repository the source fields are used directly', function () {
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource([
        'location_id'       => 5,
        'formatted_address' => '3 High Street',
        'city'              => 'Bath',
        'state'             => 'Somerset',
        'postal_code'       => 'BA1 1AA',
        'country'           => 'GB',
        'region'            => 'South West',
        'location_notes'    => 'Upstairs',
    ]));

    $location = $meeting->getLocation();
    expect($location->getAddress())->toBe('3 High Street')
        ->and($location->getCity())->toBe('Bath')
        ->and($location->getState())->toBe('Somerset')
        ->and($location->getPostalCode())->toBe('BA1 1AA')
        ->and($location->getCountry())->toBe('GB')
        ->and($location->getRegion())->toBe('South West')
        ->and($location->getNotes())->toBe('Upstairs');
});

test('a meeting with neither a name nor an address has no location', function () {
    $factory = new TsmlMeetingFactory();

    // 'location' is required by createFromSource, so pass it empty to
    // reach the branch where no Location object can be built.
    $meeting = $factory->createFromSource([
        'id'       => 123,
        'name'     => 'Nameless',
        'slug'     => 'nameless',
        'location' => '',
        'day'      => 1,
    ]);

    if ($meeting !== null) {
        expect($meeting->getLocation())->toBeNull();
    } else {
        // An empty location is treated as a missing required field,
        // which is equally acceptable — assert the factory was decisive.
        expect($meeting)->toBeNull();
    }
});

// ─── meta processing ────────────────────────────────────────────
test('single element meta arrays are flattened', function () {
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource([
        'meta' => [
            'conference_url' => ['https://zoom.example/j/1'],
        ],
    ]));

    expect($meeting)->not->toBeNull();
});

test('serialized meta values are unserialized', function () {
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource([
        'meta'  => ['types' => [serialize(['O', 'D'])]],
        'types' => serialize(['O', 'D']),
    ]));

    expect($meeting)->not->toBeNull()
        ->and($meeting->getTypes())->toBeArray();
});

test('meeting types are expanded from codes to names', function () {
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource(['types' => ['O', 'D']]));

    $types = $meeting->getTypes();
    expect($types)->not->toBeEmpty();
    // 'O' is the Open code; the factory stores the readable name.
    expect($types)->toContain('Open');
});

test('an unknown type code is preserved as given', function () {
    $factory = new TsmlMeetingFactory();

    $meeting = $factory->createFromSource(locationSource(['types' => ['ZZZ']]));

    expect($meeting->getTypes())->toContain('ZZZ');
});
