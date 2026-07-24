<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use WP_Mock;

/**
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
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingFactory
 */
class TsmlMeetingFactoryTypesTest extends TestCase
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
        WP_Mock::userFunction('get_post_custom')->andReturn([]);
        WP_Mock::userFunction('is_serialized')->andReturn(false);
        WP_Mock::userFunction('maybe_unserialize')->andReturnUsing(static fn ($v) => $v);

        $this->factory = new TsmlMeetingFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function source(array $overrides = []): array
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
    private function stubStoredTypes(mixed $types): void
    {
        WP_Mock::userFunction('get_post_meta')->andReturn($types);
    }

    // ─── types from postmeta ────────────────────────────────────────

    /** @test */
    public function stored_type_codes_are_expanded_to_names(): void
    {
        $this->stubStoredTypes(['O', 'D']);

        $meeting = $this->factory->createFromSource($this->source());

        $this->assertNotNull($meeting);
        $this->assertContains('Open', $meeting->getTypes());
    }

    /** @test */
    public function the_online_code_marks_the_meeting_online(): void
    {
        // 'ONL' is TSML's online marker.
        $this->stubStoredTypes(['ONL', 'O']);

        $meeting = $this->factory->createFromSource($this->source());

        $this->assertTrue($meeting->isOnline());
    }

    /** @test */
    public function the_online_type_is_removed_from_the_type_list(): void
    {
        $this->stubStoredTypes(['ONL', 'O']);

        $meeting = $this->factory->createFromSource($this->source());

        // Online is expressed by the flag, not by a type entry, so a
        // meeting is never both flagged and listed.
        $this->assertTrue($meeting->isOnline());
        $this->assertNotContains('Online', $meeting->getTypes());
    }

    /** @test */
    public function unknown_stored_codes_are_discarded(): void
    {
        $this->stubStoredTypes(['NOT_A_CODE']);

        $meeting = $this->factory->createFromSource($this->source());

        $this->assertNotNull($meeting);
        $this->assertNotContains('NOT_A_CODE', $meeting->getTypes());
    }

    /** @test */
    public function stored_types_that_are_not_an_array_are_ignored(): void
    {
        // Older data can hold a bare string rather than an array.
        $this->stubStoredTypes('O');

        $this->assertNotNull($this->factory->createFromSource($this->source()));
    }

    /** @test */
    public function empty_stored_types_are_ignored(): void
    {
        $this->stubStoredTypes([]);

        $this->assertNotNull($this->factory->createFromSource($this->source()));
    }

    /** @test */
    public function types_from_postmeta_and_from_the_source_are_both_included(): void
    {
        $this->stubStoredTypes(['O']);

        $meeting = $this->factory->createFromSource($this->source(['types' => ['O', 'D']]));

        $types = $meeting->getTypes();
        $this->assertContains('Open', $types);
        $this->assertContains('Discussion', $types);
    }

    /**
     * Regression: a type recorded in both places was listed twice.
     *
     * The postmeta codes are expanded to names before the source codes are
     * merged in, so 'Open' and 'O' are two representations of one type.
     * Deduplicating before expansion compared them as strings, found them
     * different, and kept both — which then expanded to 'Open' twice.
     * Expansion now happens first, so the dedup sees like for like.
     *
     * @test
     */
    public function a_type_present_in_both_postmeta_and_source_is_listed_once(): void
    {
        $this->stubStoredTypes(['O']);

        $meeting = $this->factory->createFromSource($this->source(['types' => ['O', 'D']]));

        $types = $meeting->getTypes();
        $this->assertSame(
            1,
            count(array_keys($types, 'Open', true)),
            'The same type recorded in postmeta and source must appear once.'
        );
        $this->assertSame(['Open', 'Discussion'], array_values($types));
    }

    /** @test */
    public function a_deduplicated_type_list_is_still_a_sequential_list(): void
    {
        // Removing a duplicate must not leave a gap in the keys: callers
        // (and json_encode) treat a sparse array as an object, not a list.
        $this->stubStoredTypes(['O']);

        $types = $this->factory
            ->createFromSource($this->source(['types' => ['O', 'D']]))
            ->getTypes();

        $this->assertSame(range(0, count($types) - 1), array_keys($types));
    }

    // ─── failure handling ───────────────────────────────────────────

    /** @test */
    public function a_non_positive_id_is_rejected_rather_than_built(): void
    {
        $this->stubStoredTypes([]);

        // Throws internally, is caught, logged and answered as null.
        $this->assertNull($this->factory->createFromSource($this->source(['id' => 0])));
        $this->assertNull($this->factory->createFromSource($this->source(['id' => -3])));
    }

    /** @test */
    public function postmeta_that_is_not_an_array_is_treated_as_empty(): void
    {
        $this->stubStoredTypes([]);
        // get_post_custom() can return false when a post has no meta.
        WP_Mock::userFunction('get_post_custom')->andReturn(false);

        $this->assertNotNull(
            $this->factory->createFromSource($this->source()),
            'A meeting with no meta at all is still a meeting.'
        );
    }
}
