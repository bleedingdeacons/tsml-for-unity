<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use WP_Post;

/*
 * Tests for reading the intergroup-position field and the GDPR timestamp.
 *
 * ACF's post-object field returns a different shape depending on how it is
 * configured — a WP_Post, an array of WP_Posts, an array of ids, or a bare
 * id — and the factory has to reduce all four to a single post id. A shape
 * it fails to recognise silently becomes position 0, i.e. "no position",
 * which is why each one is pinned separately here.
 *
 * The GDPR acceptance timestamp is stored by ACF in d/m/Y g:i a and
 * normalised to Y-m-d H:i:s so it parses and serialises predictably.
 */

covers(\TsmlForUnity\Members\TsmlMemberFactory::class);

const POSITION_FIELD_POST_ID = 123;

beforeEach(function () {
    Functions\expect('get_post')
        ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);

    $this->factory = new TsmlMemberFactory();
});

/**
 * Stub get_field() so the named field returns $value and every other
 * field falls back to a harmless empty string.
 */
function stubPositionFields(array $values): void
{
    Functions\expect('get_field')->andReturnUsing(
        static fn (string $field, int $id = 0) => $values[$field] ?? ''
    );
}

function build(): ?object
{
    return test()->factory->createFromSource(POSITION_FIELD_POST_ID);
}

// ─── intergroup position shapes ─────────────────────────────────
test('a post object field yields its id', function () {
    $post = new WP_Post(['ID' => 55, 'post_type' => 'intergroup-position']);
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => $post]);

    expect(build()->getIntergroupPosition())->toBe(55);
});

test('an array of post objects yields the first id', function () {
    // ACF returns an array when the field allows multiple selections.
    $first  = new WP_Post(['ID' => 61, 'post_type' => 'intergroup-position']);
    $second = new WP_Post(['ID' => 62, 'post_type' => 'intergroup-position']);
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => [$first, $second]]);

    expect(build()->getIntergroupPosition())->toBe(61);
});

test('an array of ids yields the first id', function () {
    // Configured to return ids rather than objects.
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => ['71', '72']]);

    expect(build()->getIntergroupPosition())->toBe(71);
});

test('a bare numeric field yields that id', function () {
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => '81']);

    expect(build()->getIntergroupPosition())->toBe(81);
});

test('an unset position field means no position', function () {
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => '']);

    expect(build()->getIntergroupPosition())->toBe(0);
});

test('an array holding something unrecognised means no position', function () {
    // Neither a WP_Post nor numeric — better to report "no position"
    // than to guess.
    stubPositionFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => [['nested' => 'array']]]);

    expect(build()->getIntergroupPosition())->toBe(0);
});

// ─── GDPR acceptance timestamp ──────────────────────────────────
test('an acf formatted acceptance time is normalised', function () {
    stubPositionFields([
        TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '05/03/2026 2:30 pm',
    ]);

    expect(build()->getGdprAcceptedAt())->toBe('2026-03-05 14:30:00');
});

test('an unparseable acceptance time is preserved as stored', function () {
    // Rather than discard a value it cannot parse, the factory hands
    // back what was stored so the data is still visible.
    stubPositionFields([
        TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => 'sometime last Tuesday',
    ]);

    expect(build()->getGdprAcceptedAt())->toBe('sometime last Tuesday');
});

test('a member who never accepted has an empty timestamp', function () {
    stubPositionFields([TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '']);

    expect(build()->getGdprAcceptedAt())->toBe('');
});
