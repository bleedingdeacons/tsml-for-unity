<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use WP_Mock;
use WP_Post;

/**
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
 *
 * @covers \TsmlForUnity\Members\TsmlMemberFactory
 */
class TsmlMemberFactoryPositionFieldTest extends TestCase
{
    private const POST_ID = 123;

    private TsmlMemberFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);

        $this->factory = new TsmlMemberFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Stub get_field() so the named field returns $value and every other
     * field falls back to a harmless empty string.
     */
    private function stubFields(array $values): void
    {
        WP_Mock::userFunction('get_field')->andReturnUsing(
            static fn (string $field, int $id = 0) => $values[$field] ?? ''
        );
    }

    private function build(): ?object
    {
        return $this->factory->createFromSource(self::POST_ID);
    }

    // ─── intergroup position shapes ─────────────────────────────────

    /** @test */
    public function a_post_object_field_yields_its_id(): void
    {
        $post = new WP_Post(['ID' => 55, 'post_type' => 'intergroup-position']);
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => $post]);

        $this->assertSame(55, $this->build()->getIntergroupPosition());
    }

    /** @test */
    public function an_array_of_post_objects_yields_the_first_id(): void
    {
        // ACF returns an array when the field allows multiple selections.
        $first  = new WP_Post(['ID' => 61, 'post_type' => 'intergroup-position']);
        $second = new WP_Post(['ID' => 62, 'post_type' => 'intergroup-position']);
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => [$first, $second]]);

        $this->assertSame(61, $this->build()->getIntergroupPosition());
    }

    /** @test */
    public function an_array_of_ids_yields_the_first_id(): void
    {
        // Configured to return ids rather than objects.
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => ['71', '72']]);

        $this->assertSame(71, $this->build()->getIntergroupPosition());
    }

    /** @test */
    public function a_bare_numeric_field_yields_that_id(): void
    {
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => '81']);

        $this->assertSame(81, $this->build()->getIntergroupPosition());
    }

    /** @test */
    public function an_unset_position_field_means_no_position(): void
    {
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => '']);

        $this->assertSame(0, $this->build()->getIntergroupPosition());
    }

    /** @test */
    public function an_array_holding_something_unrecognised_means_no_position(): void
    {
        // Neither a WP_Post nor numeric — better to report "no position"
        // than to guess.
        $this->stubFields([TsmlMemberFields::FIELD_INTERGROUP_POSITION => [['nested' => 'array']]]);

        $this->assertSame(0, $this->build()->getIntergroupPosition());
    }

    // ─── GDPR acceptance timestamp ──────────────────────────────────

    /** @test */
    public function an_acf_formatted_acceptance_time_is_normalised(): void
    {
        $this->stubFields([
            TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '05/03/2026 2:30 pm',
        ]);

        $this->assertSame('2026-03-05 14:30:00', $this->build()->getGdprAcceptedAt());
    }

    /** @test */
    public function an_unparseable_acceptance_time_is_preserved_as_stored(): void
    {
        // Rather than discard a value it cannot parse, the factory hands
        // back what was stored so the data is still visible.
        $this->stubFields([
            TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => 'sometime last Tuesday',
        ]);

        $this->assertSame('sometime last Tuesday', $this->build()->getGdprAcceptedAt());
    }

    /** @test */
    public function a_member_who_never_accepted_has_an_empty_timestamp(): void
    {
        $this->stubFields([TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '']);

        $this->assertSame('', $this->build()->getGdprAcceptedAt());
    }
}
