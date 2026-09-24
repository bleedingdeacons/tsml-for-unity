<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Meetings\TsmlMeetingFactory;

/*
 * Tests for meeting type resolution and the factory's failure handling.
 *
 * TSML records a meeting's types as short codes in postmeta, and the
 * factory expands them to readable names. Two of those codes are load
 * bearing rather than cosmetic: 'ONL' marks a meeting as online, and the
 * expanded 'Online' name is stripped back out of the type list once it has
 * set that flag — so a meeting must never end up listed as both.
 *
 * The failure path matters too. createFromSource() wraps its work in a
 * try/catch and answers null, because a single malformed meeting must not
 * break a page listing a hundred of them.
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingFactory::class);

beforeEach(function () {
    Functions\expect('get_permalink')->andReturn('https://example.test/m/1');
    Functions\expect('get_post_status')->andReturn('publish');
    Functions\expect('get_post')
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
    Functions\expect('get_post_custom')->andReturn([]);
    Functions\expect('is_serialized')->andReturn(false);
    Functions\expect('maybe_unserialize')->andReturnUsing(static fn ($v) => $v);

    $this->factory = new TsmlMeetingFactory();
});

function typesSource(array $overrides = []): array
{
    return array_merge([
        'id'       => 1,
        'name'     => 'Types Meeting',
        'slug'     => 'types-meeting',
        'location' => 'Hall',
        'day'      => 1,
    ], $overrides);
}

/** Make get_post_meta() answer with the given stored type codes. */
function stubStoredTypes(mixed $types): void
{
    Functions\expect('get_post_meta')->andReturn($types);
}

// ─── types from postmeta ────────────────────────────────────────
test('stored type codes are expanded to names', function () {
    stubStoredTypes(['O', 'D']);

    $meeting = $this->factory->createFromSource(typesSource());

    expect($meeting)->not->toBeNull()
        ->and($meeting->getTypes())->toContain('Open');
});

test('the online code marks the meeting online', function () {
    // 'ONL' is TSML's online marker.
    stubStoredTypes(['ONL', 'O']);

    $meeting = $this->factory->createFromSource(typesSource());

    expect($meeting->isOnline())->toBeTrue();
});

test('the online type is removed from the type list', function () {
    stubStoredTypes(['ONL', 'O']);

    $meeting = $this->factory->createFromSource(typesSource());

    // Online is expressed by the flag, not by a type entry, so a
    // meeting is never both flagged and listed.
    expect($meeting->isOnline())->toBeTrue()
        ->and($meeting->getTypes())->not->toContain('Online');
});

test('unknown stored codes are discarded', function () {
    stubStoredTypes(['NOT_A_CODE']);

    $meeting = $this->factory->createFromSource(typesSource());

    expect($meeting)->not->toBeNull()
        ->and($meeting->getTypes())->not->toContain('NOT_A_CODE');
});

test('stored types that are not an array are ignored', function () {
    // Older data can hold a bare string rather than an array.
    stubStoredTypes('O');

    expect($this->factory->createFromSource(typesSource()))->not->toBeNull();
});

test('empty stored types are ignored', function () {
    stubStoredTypes([]);

    expect($this->factory->createFromSource(typesSource()))->not->toBeNull();
});

test('types from postmeta and from the source are both included', function () {
    stubStoredTypes(['O']);

    $meeting = $this->factory->createFromSource(typesSource(['types' => ['O', 'D']]));

    $types = $meeting->getTypes();
    expect($types)->toContain('Open')
        ->and($types)->toContain('Discussion');
});

/*
 * Regression: a type recorded in both places was listed twice.
 *
 * The postmeta codes are expanded to names before the source codes are
 * merged in, so 'Open' and 'O' are two representations of one type.
 * Deduplicating before expansion compared them as strings, found them
 * different, and kept both — which then expanded to 'Open' twice.
 * Expansion now happens first, so the dedup sees like for like.
 */
test('a type present in both postmeta and source is listed once', function () {
    stubStoredTypes(['O']);

    $meeting = $this->factory->createFromSource(typesSource(['types' => ['O', 'D']]));

    $types = $meeting->getTypes();
    expect(count(array_keys($types, 'Open', true)))->toBe(1, 'The same type recorded in postmeta and source must appear once.')
        ->and(array_values($types))->toBe(['Open', 'Discussion']);
});

test('a deduplicated type list is still a sequential list', function () {
    // Removing a duplicate must not leave a gap in the keys: callers
    // (and json_encode) treat a sparse array as an object, not a list.
    stubStoredTypes(['O']);

    $types = $this->factory
        ->createFromSource(typesSource(['types' => ['O', 'D']]))
        ->getTypes();

    expect(array_keys($types))->toBe(range(0, count($types) - 1));
});

// ─── failure handling ───────────────────────────────────────────
test('a non positive id is rejected rather than built', function () {
    stubStoredTypes([]);

    // Throws internally, is caught, logged and answered as null.
    expect($this->factory->createFromSource(typesSource(['id' => 0])))->toBeNull()
        ->and($this->factory->createFromSource(typesSource(['id' => -3])))->toBeNull();
});

test('postmeta that is not an array is treated as empty', function () {
    stubStoredTypes([]);
    // get_post_custom() can return false when a post has no meta.
    Functions\expect('get_post_custom')->andReturn(false);

    expect($this->factory->createFromSource(typesSource()))->not->toBeNull('A meeting with no meta at all is still a meeting.');
});
