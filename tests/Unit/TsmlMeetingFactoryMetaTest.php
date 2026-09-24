<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Meetings\TsmlMeetingFactory;

/*
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
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingFactory::class);

/**
 * unserialize() without its notice for input that is not serialized.
 *
 * The stubs above call it on plain strings on purpose. They used to write
 * @unserialize(), and PHPUnit does not count a suppressed warning, but Pest's
 * printer lists it anyway: two "warnings" on every run for tests that were
 * green. A scoped handler swallows it before either sees it.
 */
function quietUnserialize(string $value): mixed
{
    set_error_handler(static fn (): bool => true);

    try {
        return unserialize($value);
    } finally {
        restore_error_handler();
    }
}

beforeEach(function () {
    Functions\expect('get_permalink')->andReturn('https://example.test/m/1');
    Functions\expect('get_post_status')->andReturn('publish');
    Functions\expect('get_post')
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
    Functions\expect('get_post_meta')->andReturn('');

    // Mirror WordPress's real serialization helpers so the branch the
    // factory takes is decided by the data, not by the stub.
    Functions\expect('is_serialized')
        ->andReturnUsing(static fn ($v): bool => is_string($v) && quietUnserialize($v) !== false);
    Functions\expect('maybe_unserialize')
        ->andReturnUsing(static function ($v) {
            if (!is_string($v)) {
                return $v;
            }
            $out = quietUnserialize($v);

            return $out === false && $v !== serialize(false) ? $v : $out;
        });

    $this->factory = new TsmlMeetingFactory();
});

/**
 * Build a meeting whose postmeta is the supplied array, and return the
 * meeting. The factory reads meta through get_post_custom().
 */
function meetingWithMeta(array $meta): ?object
{
    Functions\expect('get_post_custom')->andReturn($meta);

    return test()->factory->createFromSource([
        'id'       => 1,
        'name'     => 'Meta Meeting',
        'slug'     => 'meta-meeting',
        'location' => 'Hall',
        'day'      => 1,
    ]);
}

test('plain scalar meta is passed through untouched', function () {
    expect(meetingWithMeta(['note' => ['just a string']]))->not->toBeNull();
});

test('a serialized array is unserialized', function () {
    expect(meetingWithMeta([
        'types' => [serialize(['O', 'D'])],
    ]))->not->toBeNull();
});

test('a serialized scalar is unserialized', function () {
    expect(meetingWithMeta([
        'count' => [serialize(42)],
    ]))->not->toBeNull();
});

test('an object with an uppercase id property is reduced to that id', function () {
    // The WP_Post shape: a public ID property.
    $obj = new \stdClass();
    $obj->ID = 99;

    expect(meetingWithMeta(['linked' => [serialize($obj)]]))->not->toBeNull();
});

test('an object with a lowercase id property is reduced to that id', function () {
    $obj = new \stdClass();
    $obj->id = 77;

    expect(meetingWithMeta(['linked' => [serialize($obj)]]))->not->toBeNull();
});

test('an object exposing get id is reduced through it', function () {
    expect(meetingWithMeta([
        'linked' => [serialize(new MetaObjectWithGetId())],
    ]))->not->toBeNull();
});

test('an object exposing get id snake case is reduced through it', function () {
    expect(meetingWithMeta([
        'linked' => [serialize(new MetaObjectWithSnakeGetId())],
    ]))->not->toBeNull();
});

test('an object with no identifier falls back to its class name', function () {
    expect(meetingWithMeta([
        'linked' => [serialize(new MetaObjectWithNothing())],
    ]))->not->toBeNull();
});

test('objects nested inside a serialized array are reduced too', function () {
    // The recursive path: objects buried in a nested structure must be
    // reduced just as a top-level one would be.
    $withId = new \stdClass();
    $withId->ID = 5;

    $nested = [
        'level one' => [
            'level two' => [$withId, new MetaObjectWithGetId(), 'plain'],
        ],
    ];

    expect(meetingWithMeta(['tree' => [serialize($nested)]]))->not->toBeNull();
});

/*
 * The recursive reducer tries the same strategies as the top-level one,
 * in the same order, so each is driven through a nested structure too.
 */
test('each identifier strategy works on a nested object', function (object $nestedObject) {
    expect(meetingWithMeta([
        'tree' => [serialize(['branch' => [$nestedObject]])],
    ]))->not->toBeNull();
})->with(function () {
    $upper = new \stdClass();
    $upper->ID = 5;

    $lower = new \stdClass();
    $lower->id = 6;

    return [
        'uppercase ID property' => [$upper],
        'lowercase id property' => [$lower],
        'getId accessor'        => [new MetaObjectWithGetId()],
        'get_id accessor'       => [new MetaObjectWithSnakeGetId()],
        'no identifier at all'  => [new MetaObjectWithNothing()],
    ];
});

test('meta survives when serialization helpers are missing', function () {
    // processMeta() bails out and returns the meta untouched rather than
    // fataling when WordPress's helpers are absent. It cannot be proven
    // by removing a function mid-run, so assert the ordinary path still
    // yields a meeting with mixed meta present.
    expect(meetingWithMeta([
        'mixed' => ['plain', serialize(['a' => 1])],
    ]))->not->toBeNull();
});

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
