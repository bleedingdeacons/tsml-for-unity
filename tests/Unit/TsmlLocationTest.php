<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Locations\TsmlLocation;
use Unity\Locations\Interfaces\Location;

/**
 * Tests for TsmlLocation entity
 *
 * @covers \TsmlForUnity\Locations\TsmlLocation
 */
class TsmlLocationTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_location_interface(): void
    {
        $this->assertInstanceOf(Location::class, new TsmlLocation());
    }

    /**
     * @test
     */
    public function it_defaults_every_field(): void
    {
        $location = new TsmlLocation();

        $this->assertSame(0, $location->getId());
        $this->assertSame('', $location->getName());
        $this->assertSame('', $location->getAddress());
        $this->assertSame('', $location->getCity());
        $this->assertSame('', $location->getState());
        $this->assertSame('', $location->getPostalCode());
        $this->assertSame('', $location->getCountry());
        $this->assertSame('', $location->getRegion());
        $this->assertSame('', $location->getNotes());
        $this->assertSame('', $location->getLink());
        $this->assertNull($location->getLatitude());
        $this->assertNull($location->getLongitude());
        $this->assertSame('', $location->getTimezone());
        $this->assertSame([], $location->getMeetingIds());
        $this->assertSame('', $location->getUpdated());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_passed_to_the_constructor(): void
    {
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

        $this->assertSame(12, $location->getId());
        $this->assertSame('Church Hall', $location->getName());
        $this->assertSame('1 High Street', $location->getAddress());
        $this->assertSame('London', $location->getCity());
        $this->assertSame('Greater London', $location->getState());
        $this->assertSame('SW1A 1AA', $location->getPostalCode());
        $this->assertSame('UK', $location->getCountry());
        $this->assertSame('South', $location->getRegion());
        $this->assertSame('Side entrance', $location->getNotes());
        $this->assertSame('https://example.com/loc', $location->getLink());
        $this->assertSame(51.5, $location->getLatitude());
        $this->assertSame(-0.12, $location->getLongitude());
        $this->assertSame('Europe/London', $location->getTimezone());
        $this->assertSame([1, 2, 3], $location->getMeetingIds());
        $this->assertSame('2026-06-01 10:00:00', $location->getUpdated());
    }

    /**
     * @test
     */
    public function is_valid_requires_a_saved_id_and_a_name(): void
    {
        $this->assertFalse((new TsmlLocation(id: 0, name: 'Named'))->isValid());
        $this->assertFalse((new TsmlLocation(id: 5, name: ''))->isValid());
        $this->assertTrue((new TsmlLocation(id: 5, name: 'Named'))->isValid());
    }

    /**
     * @test
     */
    public function has_coordinates_needs_both_latitude_and_longitude(): void
    {
        $this->assertFalse((new TsmlLocation())->hasCoordinates());
        $this->assertFalse((new TsmlLocation(latitude: 51.5))->hasCoordinates());
        $this->assertFalse((new TsmlLocation(longitude: -0.12))->hasCoordinates());
        $this->assertTrue((new TsmlLocation(latitude: 51.5, longitude: -0.12))->hasCoordinates());
        // Zero is a legitimate coordinate and must not read as "missing".
        $this->assertTrue((new TsmlLocation(latitude: 0.0, longitude: 0.0))->hasCoordinates());
    }

    /**
     * @test
     */
    public function formatted_address_joins_only_the_populated_parts(): void
    {
        $full = new TsmlLocation(
            address: '1 High Street',
            city: 'London',
            state: 'Greater London',
            postalCode: 'SW1A 1AA',
            country: 'UK'
        );

        $this->assertSame(
            '1 High Street, London, Greater London, SW1A 1AA, UK',
            $full->getFormattedAddress()
        );
    }

    /**
     * @test
     */
    public function formatted_address_is_empty_when_nothing_is_set(): void
    {
        $this->assertSame('', (new TsmlLocation())->getFormattedAddress());
    }

    /**
     * @test
     */
    public function formatted_address_skips_missing_segments(): void
    {
        // Only city + country: no street line, no state/zip cluster tail.
        $partial = new TsmlLocation(city: 'London', country: 'UK');
        $this->assertSame('London, UK', $partial->getFormattedAddress());

        // Street only.
        $streetOnly = new TsmlLocation(address: '1 High Street');
        $this->assertSame('1 High Street', $streetOnly->getFormattedAddress());
    }
}
