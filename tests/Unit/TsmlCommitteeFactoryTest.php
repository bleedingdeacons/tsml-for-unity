<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Committees\TsmlCommitteeFactory;
use TsmlForUnity\Committees\TsmlCommitteeFields;
use TsmlForUnity\Tests\TestCase;
use Unity\Committees\Interfaces\Committee;
use Unity\Committees\Interfaces\CommitteeFactory;
use WP_Term;

/**
 * Tests for TsmlCommitteeFactory.
 *
 * Two entry points: createFromSource() fetches a term by ID, createFromTerm()
 * hydrates one the caller already holds. Everything the first does beyond
 * fetching, the second does too, so the guards are tested on both.
 *
 * @covers \TsmlForUnity\Committees\TsmlCommitteeFactory
 */
class TsmlCommitteeFactoryTest extends TestCase
{
    private TsmlCommitteeFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TsmlCommitteeFactory();
    }

    /**
     * Build a WP_Term in the committee taxonomy.
     *
     * @param array<string, mixed> $overrides
     */
    private function term(array $overrides = []): WP_Term
    {
        return new WP_Term(array_merge([
            'term_id'     => 7,
            'name'        => 'Telephones',
            'slug'        => 'telephones',
            'taxonomy'    => TsmlCommitteeFields::TAXONOMY,
            'description' => 'The helpline',
            'parent'      => 1,
        ], $overrides));
    }

    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(CommitteeFactory::class, $this->factory);
    }

    /**
     * @test
     */
    public function it_hydrates_every_field_from_the_term(): void
    {
        $committee = $this->factory->createFromTerm($this->term());

        $this->assertInstanceOf(Committee::class, $committee);
        $this->assertSame(7, $committee->getId());
        $this->assertSame('Telephones', $committee->getName());
        $this->assertSame('telephones', $committee->getSlug());
        $this->assertSame('The helpline', $committee->getDescription());
        $this->assertSame(1, $committee->getParentId());
        $this->assertFalse($committee->isRoot());
    }

    /**
     * Term names are stored HTML-encoded by WordPress, exactly as
     * TsmlPositionFactory decodes position names.
     *
     * @test
     */
    public function it_decodes_entities_in_the_name(): void
    {
        $committee = $this->factory->createFromTerm(
            $this->term(['name' => 'Health &amp; Corrections'])
        );

        $this->assertNotNull($committee);
        $this->assertSame('Health & Corrections', $committee->getName());
    }

    /**
     * @test
     */
    public function it_refuses_a_term_from_another_taxonomy(): void
    {
        $this->assertNull(
            $this->factory->createFromTerm($this->term(['taxonomy' => 'category']))
        );
    }

    /**
     * @test
     */
    public function create_from_source_fetches_the_term_and_hydrates_it(): void
    {
        $term = $this->term();

        Functions\expect('get_term')
            ->once()
            ->with(7, TsmlCommitteeFields::TAXONOMY)
            ->andReturn($term);

        $committee = $this->factory->createFromSource(7);

        $this->assertNotNull($committee);
        $this->assertSame('telephones', $committee->getSlug());
    }

    /**
     * @test
     */
    public function create_from_source_returns_null_for_a_missing_term(): void
    {
        Functions\expect('get_term')->once()->andReturn(null);

        $this->assertNull($this->factory->createFromSource(404));
    }

    /**
     * get_term() returns a WP_Error for an unregistered taxonomy. There is no
     * separate is_wp_error() branch to test: a WP_Error is not a WP_Term, so
     * the instanceof check already refuses it, and a second guard would be a
     * condition no input could reach.
     *
     * @test
     */
    public function create_from_source_returns_null_for_an_error(): void
    {
        Functions\expect('get_term')->once()->andReturn(new \WP_Error());

        $this->assertNull($this->factory->createFromSource(7));
    }

    /**
     * @test
     */
    public function create_from_source_rejects_a_non_positive_id_without_querying(): void
    {
        Functions\expect('get_term')->never();

        $this->assertNull($this->factory->createFromSource(0));
        $this->assertNull($this->factory->createFromSource(-1));
    }
}
