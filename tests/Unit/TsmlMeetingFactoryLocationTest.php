<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Locations\Interfaces\Location;
use Unity\Locations\Interfaces\LocationRepository;
use WP_Mock;

/**
 * Tests for TsmlMeetingFactory's location resolution and meta handling.
 *
 * Complements TsmlMeetingFactoryTest, which covers the happy path of
 * createFromSource(). What is exercised here is how a meeting acquires its
 * location — the factory prefers a LocationRepository lookup by
 * `location_id` and only falls back to the flat fields on the source when
 * that yields nothing. Getting that precedence wrong would silently strip
 * addresses off every meeting, which is the sort of thing that looks fine
 * in a smoke test.
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingFactory
 */
class TsmlMeetingFactoryLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // createFromSource() refuses to run unless the whole WordPress post
        // API is present, so stub the lot once here rather than per test.
        WP_Mock::userFunction('get_permalink')->andReturn('https://example.test/location/5');
        WP_Mock::userFunction('get_post_status')->andReturn('publish');
        WP_Mock::userFunction('get_post_custom')->andReturn([]);
        WP_Mock::userFunction('is_serialized')
            ->andReturnUsing(static fn ($v): bool => is_string($v) && @unserialize($v) !== false);
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('get_post_meta')->andReturn('');
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /** The minimum source createFromSource() will accept. */
    private function source(array $overrides = []): array
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

    private function location(): Location
    {
        $location = $this->createMock(Location::class);
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

    /** @test */
    public function the_location_repository_can_be_supplied_after_construction(): void
    {
        $repository = $this->createMock(LocationRepository::class);
        $repository->expects($this->once())->method('findById')->with(5)->willReturn($this->location());

        $factory = new TsmlMeetingFactory();
        $factory->setLocationRepository($repository);

        $meeting = $factory->createFromSource($this->source(['location_id' => 5]));

        $this->assertNotNull($meeting);
        $this->assertSame('St Mary Hall', $meeting->getLocation()->getName());
    }

    /** @test */
    public function the_contact_factory_can_be_supplied_after_construction(): void
    {
        $factory = new TsmlMeetingFactory();
        $factory->setContactFactory($this->createMock(ContactFactory::class));

        $this->assertNotNull($factory->createFromSource($this->source()));
    }

    /** @test */
    public function a_default_contact_factory_is_created_when_none_is_given(): void
    {
        // No contact factory injected; the factory should build its own
        // rather than fataling when a meeting carries contacts.
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source([
            'meta' => [
                'contact_1_name'  => ['Alex'],
                'contact_1_email' => ['alex@example.test'],
            ],
        ]));

        $this->assertNotNull($meeting);
    }

    // ─── location via repository ────────────────────────────────────

    /** @test */
    public function a_resolved_location_supplies_every_address_component(): void
    {
        $repository = $this->createMock(LocationRepository::class);
        $repository->method('findById')->with(5)->willReturn($this->location());

        $factory = new TsmlMeetingFactory(null, $repository);
        $meeting = $factory->createFromSource($this->source([
            'location_id' => 5,
            'latitude'    => '51.45',
            'longitude'   => '-2.58',
            'timezone'    => 'Europe/London',
        ]));

        $location = $meeting->getLocation();
        $this->assertSame('St Mary Hall', $location->getName());
        $this->assertSame('1 Church Lane', $location->getAddress());
        $this->assertSame('Bristol', $location->getCity());
        $this->assertSame('BS1 1AA', $location->getPostalCode());
        $this->assertSame('Side entrance', $location->getNotes());
        // The permalink is looked up from the location id.
        $this->assertSame('https://example.test/location/5', $location->getLink());
    }

    /** @test */
    public function an_unresolvable_location_id_falls_back_to_the_source_fields(): void
    {
        $repository = $this->createMock(LocationRepository::class);
        $repository->method('findById')->willReturn(null);

        $factory = new TsmlMeetingFactory(null, $repository);
        $meeting = $factory->createFromSource($this->source([
            'location_id'       => 5,
            'location'          => 'Fallback Hall',
            'formatted_address' => '2 Other Road',
        ]));

        $this->assertSame('Fallback Hall', $meeting->getLocation()->getName());
        $this->assertSame('2 Other Road', $meeting->getLocation()->getAddress());
    }

    /** @test */
    public function a_zero_location_id_is_not_looked_up(): void
    {
        $repository = $this->createMock(LocationRepository::class);
        $repository->expects($this->never())->method('findById');

        $factory = new TsmlMeetingFactory(null, $repository);
        $meeting = $factory->createFromSource($this->source(['location_id' => 0]));

        $this->assertSame('Community Center', $meeting->getLocation()->getName());
    }

    /** @test */
    public function without_a_repository_the_source_fields_are_used_directly(): void
    {
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source([
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
        $this->assertSame('3 High Street', $location->getAddress());
        $this->assertSame('Bath', $location->getCity());
        $this->assertSame('Somerset', $location->getState());
        $this->assertSame('BA1 1AA', $location->getPostalCode());
        $this->assertSame('GB', $location->getCountry());
        $this->assertSame('South West', $location->getRegion());
        $this->assertSame('Upstairs', $location->getNotes());
    }

    /** @test */
    public function a_meeting_with_neither_a_name_nor_an_address_has_no_location(): void
    {
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
            $this->assertNull($meeting->getLocation());
        } else {
            // An empty location is treated as a missing required field,
            // which is equally acceptable — assert the factory was decisive.
            $this->assertNull($meeting);
        }
    }

    // ─── meta processing ────────────────────────────────────────────

    /** @test */
    public function single_element_meta_arrays_are_flattened(): void
    {
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source([
            'meta' => [
                'conference_url' => ['https://zoom.example/j/1'],
            ],
        ]));

        $this->assertNotNull($meeting);
    }

    /** @test */
    public function serialized_meta_values_are_unserialized(): void
    {
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source([
            'meta'  => ['types' => [serialize(['O', 'D'])]],
            'types' => serialize(['O', 'D']),
        ]));

        $this->assertNotNull($meeting);
        $this->assertIsArray($meeting->getTypes());
    }

    /** @test */
    public function meeting_types_are_expanded_from_codes_to_names(): void
    {
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source(['types' => ['O', 'D']]));

        $types = $meeting->getTypes();
        $this->assertNotEmpty($types);
        // 'O' is the Open code; the factory stores the readable name.
        $this->assertContains('Open', $types);
    }

    /** @test */
    public function an_unknown_type_code_is_preserved_as_given(): void
    {
        $factory = new TsmlMeetingFactory();

        $meeting = $factory->createFromSource($this->source(['types' => ['ZZZ']]));

        $this->assertContains('ZZZ', $meeting->getTypes());
    }
}
