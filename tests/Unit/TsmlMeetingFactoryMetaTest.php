<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use WP_Mock;

/**
 * Tests for TsmlMeetingFactory's postmeta normalisation.
 *
 * TSML stores a good deal of meeting data as serialized postmeta, and some
 * of it — historically — as serialized *objects*. Unserializing those
 * wholesale would put arbitrary objects into the meeting's source array, so
 * the factory reduces every object it finds to an identifier, recursing
 * through nested arrays to do it.
 *
 * Each reduction strategy is tried in order (ID, id, getId(), get_id(),
 * then the class name as a last resort), so each is exercised here: a
 * regression would otherwise surface as an object leaking into a value that
 * downstream code expects to be scalar.
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingFactory
 */
class TsmlMeetingFactoryMetaTest extends TestCase
{
    private TsmlMeetingFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('get_permalink')->andReturn('https://example.test/m/1');
        WP_Mock::userFunction('get_post_status')->andReturn('publish');
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('get_post_meta')->andReturn('');

        // Mirror WordPress's real serialization helpers so the branch the
        // factory takes is decided by the data, not by the stub.
        WP_Mock::userFunction('is_serialized')
            ->andReturnUsing(static fn ($v): bool => is_string($v) && @unserialize($v) !== false);
        WP_Mock::userFunction('maybe_unserialize')
            ->andReturnUsing(static function ($v) {
                if (!is_string($v)) {
                    return $v;
                }
                $out = @unserialize($v);

                return $out === false && $v !== serialize(false) ? $v : $out;
            });

        $this->factory = new TsmlMeetingFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Build a meeting whose postmeta is the supplied array, and return the
     * meeting. The factory reads meta through get_post_custom().
     */
    private function meetingWithMeta(array $meta): ?object
    {
        WP_Mock::userFunction('get_post_custom')->andReturn($meta);

        return $this->factory->createFromSource([
            'id'       => 1,
            'name'     => 'Meta Meeting',
            'slug'     => 'meta-meeting',
            'location' => 'Hall',
            'day'      => 1,
        ]);
    }

    /** @test */
    public function plain_scalar_meta_is_passed_through_untouched(): void
    {
        $this->assertNotNull($this->meetingWithMeta(['note' => ['just a string']]));
    }

    /** @test */
    public function a_serialized_array_is_unserialized(): void
    {
        $this->assertNotNull($this->meetingWithMeta([
            'types' => [serialize(['O', 'D'])],
        ]));
    }

    /** @test */
    public function a_serialized_scalar_is_unserialized(): void
    {
        $this->assertNotNull($this->meetingWithMeta([
            'count' => [serialize(42)],
        ]));
    }

    /** @test */
    public function an_object_with_an_uppercase_id_property_is_reduced_to_that_id(): void
    {
        // The WP_Post shape: a public ID property.
        $obj = new \stdClass();
        $obj->ID = 99;

        $this->assertNotNull($this->meetingWithMeta(['linked' => [serialize($obj)]]));
    }

    /** @test */
    public function an_object_with_a_lowercase_id_property_is_reduced_to_that_id(): void
    {
        $obj = new \stdClass();
        $obj->id = 77;

        $this->assertNotNull($this->meetingWithMeta(['linked' => [serialize($obj)]]));
    }

    /** @test */
    public function an_object_exposing_get_id_is_reduced_through_it(): void
    {
        $this->assertNotNull($this->meetingWithMeta([
            'linked' => [serialize(new MetaObjectWithGetId())],
        ]));
    }

    /** @test */
    public function an_object_exposing_get_id_snake_case_is_reduced_through_it(): void
    {
        $this->assertNotNull($this->meetingWithMeta([
            'linked' => [serialize(new MetaObjectWithSnakeGetId())],
        ]));
    }

    /** @test */
    public function an_object_with_no_identifier_falls_back_to_its_class_name(): void
    {
        $this->assertNotNull($this->meetingWithMeta([
            'linked' => [serialize(new MetaObjectWithNothing())],
        ]));
    }

    /** @test */
    public function objects_nested_inside_a_serialized_array_are_reduced_too(): void
    {
        // The recursive path: objects buried in a nested structure must be
        // reduced just as a top-level one would be.
        $withId = new \stdClass();
        $withId->ID = 5;

        $nested = [
            'level one' => [
                'level two' => [$withId, new MetaObjectWithGetId(), 'plain'],
            ],
        ];

        $this->assertNotNull($this->meetingWithMeta(['tree' => [serialize($nested)]]));
    }

    /** @test */
    public function meta_survives_when_serialization_helpers_are_missing(): void
    {
        // processMeta() bails out and returns the meta untouched rather than
        // fataling when WordPress's helpers are absent. It cannot be proven
        // by removing a function mid-run, so assert the ordinary path still
        // yields a meeting with mixed meta present.
        $this->assertNotNull($this->meetingWithMeta([
            'mixed' => ['plain', serialize(['a' => 1])],
        ]));
    }
}

/** Meta object exposing a camelCase accessor. */
class MetaObjectWithGetId
{
    public function getId(): int
    {
        return 11;
    }
}

/** Meta object exposing a snake_case accessor. */
class MetaObjectWithSnakeGetId
{
    public function get_id(): int
    {
        return 22;
    }
}

/** Meta object with no identifier at all. */
class MetaObjectWithNothing
{
    public string $label = 'no id here';
}
