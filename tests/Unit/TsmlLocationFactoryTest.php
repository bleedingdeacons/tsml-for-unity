<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Locations\TsmlLocationFactory;
use TsmlForUnity\Locations\TsmlLocationFields;
use TsmlForUnity\Tests\TestCase;
use Unity\Locations\Interfaces\Location;

/**
 * @covers \TsmlForUnity\Locations\TsmlLocationFactory
 */
class TsmlLocationFactoryTest extends TestCase
{
    private TsmlLocationFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TsmlLocationFactory();
    }

    /**
     * @test
     */
    public function it_returns_null_when_post_does_not_exist(): void
    {
        Functions\expect('get_post')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->factory->createFromSource(999);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_post_is_wrong_type(): void
    {
        $post = $this->createMockPost([
            'ID' => 123,
            'post_type' => 'post', // Wrong type, should be 'tsml_location'
            'post_title' => 'Wrong Post Type',
        ]);

        Functions\expect('get_post')
            ->once()
            ->with(123)
            ->andReturn($post);

        $result = $this->factory->createFromSource(123);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_creates_location_from_valid_post(): void
    {
        $postId = 100;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Community Center',
        ]);

        $meta = [
            TsmlLocationFields::ADDRESS => ['123 Main Street'],
            TsmlLocationFields::CITY => ['Springfield'],
            TsmlLocationFields::STATE => ['IL'],
            TsmlLocationFields::POSTAL_CODE => ['62701'],
            TsmlLocationFields::COUNTRY => ['USA'],
            TsmlLocationFields::NOTES => ['Enter through side door'],
            TsmlLocationFields::LATITUDE => ['39.7817'],
            TsmlLocationFields::LONGITUDE => ['-89.6501'],
            TsmlLocationFields::TIMEZONE => ['America/Chicago'],
        ];

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        Functions\expect('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        Functions\expect('wp_get_post_terms')
            ->once()
            ->with($postId, TsmlLocationFields::REGION_TAXONOMY, ['fields' => 'names'])
            ->andReturn(['Downtown']);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([200, 201, 202]); // Meeting IDs

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('https://example.com/location/community-center');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertInstanceOf(Location::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Community Center', $result->getName());
        $this->assertEquals('123 Main Street', $result->getAddress());
        $this->assertEquals('Springfield', $result->getCity());
        $this->assertEquals('IL', $result->getState());
        $this->assertEquals('62701', $result->getPostalCode());
        $this->assertEquals('USA', $result->getCountry());
        $this->assertEquals('Downtown', $result->getRegion());
        $this->assertEquals('Enter through side door', $result->getNotes());
        $this->assertEquals('https://example.com/location/community-center', $result->getLink());
        $this->assertEquals(39.7817, $result->getLatitude());
        $this->assertEquals(-89.6501, $result->getLongitude());
        $this->assertEquals('America/Chicago', $result->getTimezone());
        $this->assertEquals([200, 201, 202], $result->getMeetingIds());
    }

    /**
     * @test
     */
    public function it_handles_empty_meta(): void
    {
        $postId = 200;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Minimal Location',
        ]);

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn([]);

        Functions\expect('wp_get_post_terms')
            ->once()
            ->andReturn([]);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Minimal Location', $result->getName());
        $this->assertEquals('', $result->getAddress());
        $this->assertEquals('', $result->getCity());
        $this->assertEquals('', $result->getState());
        $this->assertEquals('', $result->getPostalCode());
        $this->assertEquals('', $result->getCountry());
        $this->assertEquals('', $result->getRegion());
        $this->assertEquals('', $result->getNotes());
        $this->assertNull($result->getLatitude());
        $this->assertNull($result->getLongitude());
        $this->assertEquals('', $result->getTimezone());
        $this->assertEquals([], $result->getMeetingIds());
    }

    /**
     * @test
     */
    public function it_handles_null_coordinates(): void
    {
        $postId = 300;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'No Coordinates Location',
        ]);

        $meta = [
            TsmlLocationFields::ADDRESS => ['456 Oak Avenue'],
            TsmlLocationFields::CITY => ['Chicago'],
            // No latitude/longitude
        ];

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        Functions\expect('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        Functions\expect('wp_get_post_terms')
            ->once()
            ->andReturn([]);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertNull($result->getLatitude());
        $this->assertNull($result->getLongitude());
        $this->assertFalse($result->hasCoordinates());
    }

    /**
     * @test
     */
    public function it_handles_multiple_regions_returning_first(): void
    {
        $postId = 400;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Multi-Region Location',
        ]);

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn([]);

        Functions\expect('wp_get_post_terms')
            ->once()
            ->with($postId, TsmlLocationFields::REGION_TAXONOMY, ['fields' => 'names'])
            ->andReturn(['North Side', 'Downtown', 'Metro Area']);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertEquals('North Side', $result->getRegion());
    }

    /**
     * @test
     */
    public function it_handles_false_permalink(): void
    {
        $postId = 500;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Test Location',
        ]);

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn([]);

        Functions\expect('wp_get_post_terms')
            ->once()
            ->andReturn([]);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn(false);

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertEquals('', $result->getLink());
    }

    /**
     * @test
     */
    public function it_parses_valid_coordinates(): void
    {
        $postId = 600;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Coordinates Test',
        ]);

        $meta = [
            TsmlLocationFields::LATITUDE => ['51.5074'],
            TsmlLocationFields::LONGITUDE => ['-0.1278'],
        ];

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        Functions\expect('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        Functions\expect('wp_get_post_terms')
            ->once()
            ->andReturn([]);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertEquals(51.5074, $result->getLatitude());
        $this->assertEquals(-0.1278, $result->getLongitude());
        $this->assertTrue($result->hasCoordinates());
    }

    /**
     * @test
     */
    public function it_handles_invalid_coordinates(): void
    {
        $postId = 700;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlLocationFields::POST_TYPE,
            'post_title' => 'Invalid Coordinates Test',
        ]);

        $meta = [
            TsmlLocationFields::LATITUDE => ['not-a-number'],
            TsmlLocationFields::LONGITUDE => ['also-not-a-number'],
        ];

        Functions\expect('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        Functions\expect('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        Functions\expect('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        Functions\expect('wp_get_post_terms')
            ->once()
            ->andReturn([]);

        Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        Functions\expect('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Location::class, $result);
        $this->assertNull($result->getLatitude());
        $this->assertNull($result->getLongitude());
        $this->assertFalse($result->hasCoordinates());
    }

    /**
     * Create a mock WP_Post object
     *
     * @param array $properties Post properties
     * @return object Mock post object
     */
    private function createMockPost(array $properties): object
    {
        return (object) array_merge([
            'ID' => 0,
            'post_title' => '',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => '',
        ], $properties);
    }
}
