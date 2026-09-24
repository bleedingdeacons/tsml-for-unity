<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Locations\TsmlLocation;
use Unity\Locations\Interfaces\Location;

/*
 * Tests for TsmlLocation entity
 */

covers(\TsmlForUnity\Locations\TsmlLocation::class);

it('implements location interface', function () {
    expect(new TsmlLocation())->toBeInstanceOf(Location::class);
});

it('defaults every field', function () {
    $location = new TsmlLocation();

    expect($location->getId())->toBe(0)
        ->and($location->getName())->toBe('')
        ->and($location->getAddress())->toBe('')
        ->and($location->getCity())->toBe('')
        ->and($location->getState())->toBe('')
        ->and($location->getPostalCode())->toBe('')
        ->and($location->getCountry())->toBe('')
        ->and($location->getRegion())->toBe('')
        ->and($location->getNotes())->toBe('')
        ->and($location->getLink())->toBe('')
        ->and($location->getLatitude())->toBeNull()
        ->and($location->getLongitude())->toBeNull()
        ->and($location->getTimezone())->toBe('')
        ->and($location->getMeetingIds())->toBe([])
        ->and($location->getUpdated())->toBe('');
});

it('exposes every field passed to the constructor', function () {
    $location = new TsmlLocation(
        id: 12,
        name: 'Church Hall',
        address: '1 High Street',
        city: 'London',
        state: 'Greater London',
        postalCode: 'SW1A 1AA',
        country: 'UK',
        region: 'South',
        notes: 'Side entrance',
        link: 'https://example.com/loc',
        latitude: 51.5,
        longitude: -0.12,
        timezone: 'Europe/London',
        meetingIds: [1, 2, 3],
        updated: '2026-06-01 10:00:00'
    );

    expect($location->getId())->toBe(12)
        ->and($location->getName())->toBe('Church Hall')
        ->and($location->getAddress())->toBe('1 High Street')
        ->and($location->getCity())->toBe('London')
        ->and($location->getState())->toBe('Greater London')
        ->and($location->getPostalCode())->toBe('SW1A 1AA')
        ->and($location->getCountry())->toBe('UK')
        ->and($location->getRegion())->toBe('South')
        ->and($location->getNotes())->toBe('Side entrance')
        ->and($location->getLink())->toBe('https://example.com/loc')
        ->and($location->getLatitude())->toBe(51.5)
        ->and($location->getLongitude())->toBe(-0.12)
        ->and($location->getTimezone())->toBe('Europe/London')
        ->and($location->getMeetingIds())->toBe([1, 2, 3])
        ->and($location->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('is valid requires a saved id and a name', function () {
    expect((new TsmlLocation(id: 0, name: 'Named'))->isValid())->toBeFalse()
        ->and((new TsmlLocation(id: 5, name: ''))->isValid())->toBeFalse()
        ->and((new TsmlLocation(id: 5, name: 'Named'))->isValid())->toBeTrue();
});

test('has coordinates needs both latitude and longitude', function () {
    expect((new TsmlLocation())->hasCoordinates())->toBeFalse()
        ->and((new TsmlLocation(latitude: 51.5))->hasCoordinates())->toBeFalse()
        ->and((new TsmlLocation(longitude: -0.12))->hasCoordinates())->toBeFalse()
        ->and((new TsmlLocation(latitude: 51.5, longitude: -0.12))->hasCoordinates())->toBeTrue();
    // Zero is a legitimate coordinate and must not read as "missing".
    expect((new TsmlLocation(latitude: 0.0, longitude: 0.0))->hasCoordinates())->toBeTrue();
});

test('formatted address joins only the populated parts', function () {
    $full = new TsmlLocation(
        address: '1 High Street',
        city: 'London',
        state: 'Greater London',
        postalCode: 'SW1A 1AA',
        country: 'UK'
    );

    expect($full->getFormattedAddress())->toBe('1 High Street, London, Greater London, SW1A 1AA, UK');
});

test('formatted address is empty when nothing is set', function () {
    expect((new TsmlLocation())->getFormattedAddress())->toBe('');
});

test('formatted address skips missing segments', function () {
    // Only city + country: no street line, no state/zip cluster tail.
    $partial = new TsmlLocation(city: 'London', country: 'UK');
    expect($partial->getFormattedAddress())->toBe('London, UK');

    // Street only.
    $streetOnly = new TsmlLocation(address: '1 High Street');
    expect($streetOnly->getFormattedAddress())->toBe('1 High Street');
});
