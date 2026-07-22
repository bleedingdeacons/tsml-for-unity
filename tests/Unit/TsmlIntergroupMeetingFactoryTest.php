<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use WP_Mock;

/**
 * Tests for TsmlIntergroupMeetingFactory::createFromSource.
 *
 * Exercises the ACF reads, the field-key fallback for posts lacking shadow
 * meta, the d/m/Y → Y-m-d date normalisation, and parsePostIds handling of
 * both numeric IDs and WP_Post objects.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFactory
 */
class TsmlIntergroupMeetingFactoryTest extends TestCase
{
    private TsmlIntergroupMeetingFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlIntergroupMeetingFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingFactory::class, $this->factory);
    }

    /**
     * @test
     */
    public function it_builds_a_meeting_from_acf_fields_with_numeric_ids(): void
    {
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE, 42)->andReturn('July Intergroup');
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, 42)->andReturn([1, 2]);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, 42)->andReturn([3]);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_DATE, 42)->andReturn('01/07/2026');

        WP_Mock::userFunction('get_post')->with(42)->andReturn(
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

    /**
     * @test
     */
    public function it_falls_back_to_the_post_title_and_field_key_and_keeps_unparseable_dates(): void
    {
        // ACF meeting_title is empty, so the WP post title is used.
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE, 42)->andReturn('');
        WP_Mock::userFunction('get_the_title')->with(42)->andReturn('Fallback Title');

        // Name-based attendees read fails (no shadow meta) → key fallback.
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, 42)->andReturn(false);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES, 42)
            ->andReturn([$this->wpPost(5), $this->wpPost(6)]);

        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, 42)->andReturn(false);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS, 42)->andReturn(false);

        // Date not in d/m/Y — kept verbatim.
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_DATE, 42)->andReturn('2026-07-01');

        // The key fallback resolves via the cached option (empty → hardcoded key).
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

        WP_Mock::userFunction('get_post')->with(42)->andReturn(null);

        $meeting = $this->factory->createFromSource(42);

        $this->assertSame('Fallback Title', $meeting->getTitle());
        // WP_Post objects are reduced to their IDs.
        $this->assertSame([5, 6], $meeting->getGroupAttendees());
        // Officer key fallback also returned false → empty list.
        $this->assertSame([], $meeting->getOfficersAttending());
        $this->assertSame('2026-07-01', $meeting->getDate());
        $this->assertSame('', $meeting->getUpdated());
    }

    /**
     * @test
     */
    public function an_empty_date_field_yields_an_empty_date(): void
    {
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_MEETING_TITLE, 42)->andReturn('Title');
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDEES, 42)->andReturn([]);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_ATTENDING_OFFICERS, 42)->andReturn([]);
        WP_Mock::userFunction('get_field')
            ->with(TsmlIntergroupMeetingFields::FIELD_DATE, 42)->andReturn('');
        WP_Mock::userFunction('get_post')->with(42)->andReturn(null);

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
