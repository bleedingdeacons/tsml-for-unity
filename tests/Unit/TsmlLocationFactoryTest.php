<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Locations\TsmlLocationFactory;
use TsmlForUnity\Locations\TsmlLocationFields;
use Unity\Locations\Interfaces\Location;

covers(\TsmlForUnity\Locations\TsmlLocationFactory::class);

beforeEach(function () {
    $this->factory = new TsmlLocationFactory();
});

it('returns null when post does not exist', function () {
    Functions\expect('get_post')
        ->once()
        ->with(999)
        ->andReturn(null);

    $result = $this->factory->createFromSource(999);

    expect($result)->toBeNull();
});

it('returns null when post is wrong type', function () {
    $post = locationMockPost([
        'ID' => 123,
        'post_type' => 'post', // Wrong type, should be 'tsml_location'
        'post_title' => 'Wrong Post Type',
    ]);

    Functions\expect('get_post')
        ->once()
        ->with(123)
        ->andReturn($post);

    $result = $this->factory->createFromSource(123);

    expect($result)->toBeNull();
});

it('creates location from valid post', function () {
    $postId = 100;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result)->toBeInstanceOf(Location::class)
        ->and($result->getId())->toEqual($postId)
        ->and($result->getName())->toEqual('Community Center')
        ->and($result->getAddress())->toEqual('123 Main Street')
        ->and($result->getCity())->toEqual('Springfield')
        ->and($result->getState())->toEqual('IL')
        ->and($result->getPostalCode())->toEqual('62701')
        ->and($result->getCountry())->toEqual('USA')
        ->and($result->getRegion())->toEqual('Downtown')
        ->and($result->getNotes())->toEqual('Enter through side door')
        ->and($result->getLink())->toEqual('https://example.com/location/community-center')
        ->and($result->getLatitude())->toEqual(39.7817)
        ->and($result->getLongitude())->toEqual(-89.6501)
        ->and($result->getTimezone())->toEqual('America/Chicago')
        ->and($result->getMeetingIds())->toEqual([200, 201, 202]);
});

it('handles empty meta', function () {
    $postId = 200;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getId())->toEqual($postId)
        ->and($result->getName())->toEqual('Minimal Location')
        ->and($result->getAddress())->toEqual('')
        ->and($result->getCity())->toEqual('')
        ->and($result->getState())->toEqual('')
        ->and($result->getPostalCode())->toEqual('')
        ->and($result->getCountry())->toEqual('')
        ->and($result->getRegion())->toEqual('')
        ->and($result->getNotes())->toEqual('')
        ->and($result->getLatitude())->toBeNull()
        ->and($result->getLongitude())->toBeNull()
        ->and($result->getTimezone())->toEqual('')
        ->and($result->getMeetingIds())->toEqual([]);
});

it('handles null coordinates', function () {
    $postId = 300;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getLatitude())->toBeNull()
        ->and($result->getLongitude())->toBeNull()
        ->and($result->hasCoordinates())->toBeFalse();
});

it('handles multiple regions returning first', function () {
    $postId = 400;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getRegion())->toEqual('North Side');
});

it('handles false permalink', function () {
    $postId = 500;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getLink())->toEqual('');
});

it('parses valid coordinates', function () {
    $postId = 600;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getLatitude())->toEqual(51.5074)
        ->and($result->getLongitude())->toEqual(-0.1278)
        ->and($result->hasCoordinates())->toBeTrue();
});

it('handles invalid coordinates', function () {
    $postId = 700;
    $post = locationMockPost([
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

    expect($result)->toBeInstanceOf(Location::class)
        ->and($result->getLatitude())->toBeNull()
        ->and($result->getLongitude())->toBeNull()
        ->and($result->hasCoordinates())->toBeFalse();
});

/**
 * Create a mock WP_Post object
 *
 * @param array $properties Post properties
 * @return object Mock post object
 */
function locationMockPost(array $properties): object
{
    return (object) array_merge([
        'ID' => 0,
        'post_title' => '',
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_content' => '',
    ], $properties);
}
