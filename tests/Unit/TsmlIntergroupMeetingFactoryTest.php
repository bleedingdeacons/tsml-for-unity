<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;

/*
 * Tests for TsmlIntergroupMeetingFactory::createFromSource.
 *
 * Exercises the ACF reads, the field-key fallback for posts lacking shadow
 * meta, the d/m/Y → Y-m-d date normalisation, and parsePostIds handling of
 * both numeric IDs and WP_Post objects.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory::class);

beforeEach(function () {
    $this->factory = new TsmlIntergroupMeetingFactory();
});

it('implements the factory interface', function () {
    expect($this->factory)->toBeInstanceOf(IntergroupMeetingFactory::class);
});

it('builds a meeting from acf fields with numeric ids', function () {
    // One expectation, dispatching on the field name.
    //
    // Stacking Functions\expect('get_field')->with(...) calls does not work
    // the way the WP_Mock equivalent did: Brain Monkey keeps one stub per
    // function per test, and the first one registered answers every call
    // whatever its ->with() says. The failure is silent — every field comes
    // back as the title — so the mapping is spelled out instead.
    Functions\expect('get_field')->andReturnUsing(
        static fn (string $field, int $postId): mixed => match ($field) {
            TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE      => 'July Intergroup',
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES          => [1, 2],
            TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS => [3],
            TsmlIntergroupMeetingFields::FIELD_DATE               => '01/07/2026',
            default                                              => null,
        }
    );

    Functions\expect('get_post')->with(42)->andReturn(
        (object) ['post_modified_gmt' => '2026-07-01 20:00:00']
    );

    $meeting = $this->factory->createFromSource(42);

    expect($meeting->getId())->toBe(42)
        ->and($meeting->getTitle())->toBe('July Intergroup')
        ->and($meeting->getGroupAttendees())->toBe([1, 2])
        ->and($meeting->getOfficersAttending())->toBe([3]);
    // d/m/Y normalised to Y-m-d.
    expect($meeting->getDate())->toBe('2026-07-01')
        ->and($meeting->getUpdated())->toBe('2026-07-01 20:00:00');
});

it('falls back to the post title and field key and keeps unparseable dates', function () {
    // ACF meeting_title is empty, so the WP post title is used.
    Functions\expect('get_the_title')->with(42)->andReturn('Fallback Title');

    // One expectation, dispatching on the field name.
    //
    // Stacking Functions\expect('get_field')->with(...) calls does not work
    // the way the WP_Mock equivalent did: Brain Monkey keeps one stub per
    // function per test, and the first one registered answers every call
    // whatever its ->with() says. The failure is silent — every field comes
    // back as the title — so the mapping is spelled out instead.
    // The name-based attendees read fails (no shadow meta), so the factory
    // retries by field key; the officers fail both ways. The date is not in
    // d/m/Y, so it is kept verbatim.
    Functions\expect('get_field')->andReturnUsing(
        fn (string $field, int $postId): mixed => match ($field) {
            TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE          => '',
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES              => false,
            TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES          => [intergroupMeetingWpPost(5), intergroupMeetingWpPost(6)],
            TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS     => false,
            TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS => false,
            TsmlIntergroupMeetingFields::FIELD_DATE                   => '2026-07-01',
            default                                                  => null,
        }
    );

    // The key fallback resolves via the cached option (empty → hardcoded key).
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

    Functions\expect('get_post')->with(42)->andReturn(null);

    $meeting = $this->factory->createFromSource(42);

    expect($meeting->getTitle())->toBe('Fallback Title');
    // WP_Post objects are reduced to their IDs.
    expect($meeting->getGroupAttendees())->toBe([5, 6]);
    // Officer key fallback also returned false → empty list.
    expect($meeting->getOfficersAttending())->toBe([])
        ->and($meeting->getDate())->toBe('2026-07-01')
        ->and($meeting->getUpdated())->toBe('');
});

test('an empty date field yields an empty date', function () {
    // One expectation, dispatching on the field name.
    //
    // Stacking Functions\expect('get_field')->with(...) calls does not work
    // the way the WP_Mock equivalent did: Brain Monkey keeps one stub per
    // function per test, and the first one registered answers every call
    // whatever its ->with() says. The failure is silent — every field comes
    // back as the title — so the mapping is spelled out instead.
    Functions\expect('get_field')->andReturnUsing(
        static fn (string $field, int $postId): mixed => match ($field) {
            TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE      => 'Title',
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES          => [],
            TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS => [],
            TsmlIntergroupMeetingFields::FIELD_DATE               => '',
            default                                              => null,
        }
    );
    Functions\expect('get_post')->with(42)->andReturn(null);

    // Empty arrays are not false/null, so no key fallback and no get_option.
    $meeting = $this->factory->createFromSource(42);

    expect($meeting->getDate())->toBe('')
        ->and($meeting->getGroupAttendees())->toBe([]);
});

function intergroupMeetingWpPost(int $id): \WP_Post
{
    $post = new \WP_Post();
    $post->ID = $id;
    return $post;
}
