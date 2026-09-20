<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\Tests\TestCase;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;

/**
 * Tests for TsmlIntergroupMeetingFactory::createFromSource.
 *
 * Exercises the ACF reads, the field-key fallback for posts lacking shadow
 * meta, the d/m/Y → Y-m-d date normalisation, and parsePostIds handling of
 * both numeric IDs and WP_Post objects.
 */
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory::class)]
class TsmlIntergroupMeetingFactoryTest extends TestCase
{
    private TsmlIntergroupMeetingFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TsmlIntergroupMeetingFactory();
    }

    #[Test]
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingFactory::class, $this->factory);
    }

    #[Test]
    public function it_builds_a_meeting_from_acf_fields_with_numeric_ids(): void
    {
        // One expectation, dispatching on the field name.
        //
        // Stacking Functions\expect('get_field')->with(...) calls does not work
        // the way the WP_Mock equivalent did: Brain Monkey keeps one stub per
        // function per test, and the first one registered answers every call
        // whatever its ->with() says. The failure is silent — every field comes
        // back as the title — so the mapping is spelled out instead.
        expect('get_field')->andReturnUsing(
            static fn (string $field, int $postId): mixed => match ($field) {
                TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE      => 'July Intergroup',
                TsmlIntergroupMeetingFields::FIELD_ATTENDEES          => [1, 2],
                TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS => [3],
                TsmlIntergroupMeetingFields::FIELD_DATE               => '01/07/2026',
                default                                              => null,
            }
        );

        expect('get_post')->with(42)->andReturn(
            (object) ['post_modified_gmt' => '2026-07-01 20:00:00']
        );

        $meeting = $this->factory->createFromSource(42);

        $this->assertSame(42, $meeting->getId());
        $this->assertSame('July Intergroup', $meeting->getTitle());
        $this->assertSame([1, 2], $meeting->getGroupAttendees());
        $this->assertSame([3], $meeting->getOfficersAttending());
        // d/m/Y normalised to Y-m-d.
        $this->assertSame('2026-07-01', $meeting->getDate());
        $this->assertSame('2026-07-01 20:00:00', $meeting->getUpdated());
    }

    #[Test]
    public function it_falls_back_to_the_post_title_and_field_key_and_keeps_unparseable_dates(): void
    {
        // ACF meeting_title is empty, so the WP post title is used.
        expect('get_the_title')->with(42)->andReturn('Fallback Title');

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
        expect('get_field')->andReturnUsing(
            fn (string $field, int $postId): mixed => match ($field) {
                TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE          => '',
                TsmlIntergroupMeetingFields::FIELD_ATTENDEES              => false,
                TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES          => [$this->wpPost(5), $this->wpPost(6)],
                TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS     => false,
                TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS => false,
                TsmlIntergroupMeetingFields::FIELD_DATE                   => '2026-07-01',
                default                                                  => null,
            }
        );

        // The key fallback resolves via the cached option (empty → hardcoded key).
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

        expect('get_post')->with(42)->andReturn(null);

        $meeting = $this->factory->createFromSource(42);

        $this->assertSame('Fallback Title', $meeting->getTitle());
        // WP_Post objects are reduced to their IDs.
        $this->assertSame([5, 6], $meeting->getGroupAttendees());
        // Officer key fallback also returned false → empty list.
        $this->assertSame([], $meeting->getOfficersAttending());
        $this->assertSame('2026-07-01', $meeting->getDate());
        $this->assertSame('', $meeting->getUpdated());
    }

    #[Test]
    public function an_empty_date_field_yields_an_empty_date(): void
    {
        // One expectation, dispatching on the field name.
        //
        // Stacking Functions\expect('get_field')->with(...) calls does not work
        // the way the WP_Mock equivalent did: Brain Monkey keeps one stub per
        // function per test, and the first one registered answers every call
        // whatever its ->with() says. The failure is silent — every field comes
        // back as the title — so the mapping is spelled out instead.
        expect('get_field')->andReturnUsing(
            static fn (string $field, int $postId): mixed => match ($field) {
                TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE      => 'Title',
                TsmlIntergroupMeetingFields::FIELD_ATTENDEES          => [],
                TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS => [],
                TsmlIntergroupMeetingFields::FIELD_DATE               => '',
                default                                              => null,
            }
        );
        expect('get_post')->with(42)->andReturn(null);

        // Empty arrays are not false/null, so no key fallback and no get_option.
        $meeting = $this->factory->createFromSource(42);

        $this->assertSame('', $meeting->getDate());
        $this->assertSame([], $meeting->getGroupAttendees());
    }

    private function wpPost(int $id): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        return $post;
    }
}
