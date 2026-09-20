<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\Positions\TsmlPositionFactory;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Tests\TestCase;
use Unity\Positions\Interfaces\PositionFactory;

/**
 * Tests for TsmlPositionFactory
 */
#[CoversClass(\TsmlForUnity\Positions\TsmlPositionFactory::class)]
class TsmlPositionFactoryTest extends TestCase
{
    private TsmlPositionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TsmlPositionFactory();
    }

    #[Test]
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(PositionFactory::class, $this->factory);
    }

    #[Test]
    public function create_from_source_returns_null_when_the_post_is_missing(): void
    {
        expect('get_post')->with(99)->andReturn(null);

        $this->assertNull($this->factory->createFromSource(99));
    }

    #[Test]
    public function create_from_source_returns_null_for_the_wrong_post_type(): void
    {
        expect('get_post')->with(99)->andReturn(
            (object) ['post_type' => 'page', 'post_modified_gmt' => '']
        );

        $this->assertNull($this->factory->createFromSource(99));
    }

    #[Test]
    public function create_from_source_hydrates_a_position_from_acf_fields(): void
    {
        expect('get_post')->with(42)->andReturn((object) [
            'post_type'         => TsmlPositionFields::POST_TYPE,
            'post_modified_gmt' => '2026-06-01 10:00:00',
        ]);

        expect('get_fields')->with(42)->andReturn([
            TsmlPositionFields::MINIMUM_SOBRIETY  => '24',
            TsmlPositionFields::TERM_YEARS        => '3',
            TsmlPositionFields::EMAIL_ADDRESS     => 'chair@example.com',
            TsmlPositionFields::LONG_NAME         => 'Intergroup &amp; Chair',
            TsmlPositionFields::SHORT_DESCRIPTION => 'Chairs',
            TsmlPositionFields::SUMMARY           => 'Runs intergroup',
        ]);

        expect('get_permalink')->with(42)->andReturn('https://example.com/chair');

        $position = $this->factory->createFromSource(42);

        $this->assertNotNull($position);
        $this->assertSame(42, $position->getId());
        $this->assertSame(24, $position->getMinimumSobriety());
        $this->assertSame(3, $position->getTermYears());
        $this->assertSame('chair@example.com', $position->getEmail());
        // HTML entities in the long name are decoded.
        $this->assertSame('Intergroup & Chair', $position->getLongName());
        $this->assertSame('Chairs', $position->getShortDescription());
        $this->assertSame('Runs intergroup', $position->getSummary());
        $this->assertSame('https://example.com/chair', $position->getLink());
        $this->assertSame('2026-06-01 10:00:00', $position->getUpdated());
    }

    #[Test]
    public function create_from_source_applies_defaults_when_acf_returns_nothing(): void
    {
        expect('get_post')->with(42)->andReturn((object) [
            'post_type'         => TsmlPositionFields::POST_TYPE,
            'post_modified_gmt' => '',
        ]);

        expect('get_fields')->with(42)->andReturn(false);
        expect('get_permalink')->with(42)->andReturn(false);

        $position = $this->factory->createFromSource(42);

        $this->assertSame(6, $position->getMinimumSobriety());
        $this->assertSame(1, $position->getTermYears());
        $this->assertSame('', $position->getEmail());
        $this->assertSame('', $position->getLongName());
        $this->assertSame('', $position->getLink());
    }

    #[Test]
    public function create_new_builds_a_position_and_resolves_the_permalink(): void
    {
        expect('get_permalink')->with(7)->andReturn('https://example.com/p/7');

        $position = $this->factory->createNew(
            7,
            12,
            2,
            'sec@example.com',
            'Secretary',
            'Takes minutes',
            'Keeps records'
        );

        $this->assertSame(7, $position->getId());
        $this->assertSame(12, $position->getMinimumSobriety());
        $this->assertSame(2, $position->getTermYears());
        $this->assertSame('sec@example.com', $position->getEmail());
        $this->assertSame('Secretary', $position->getLongName());
        $this->assertSame('https://example.com/p/7', $position->getLink());
    }

    #[Test]
    public function create_new_skips_the_permalink_lookup_for_an_unsaved_position(): void
    {
        // id 0 means "not persisted": get_permalink must not be called.
        expect('get_permalink')->never();

        $position = $this->factory->createNew(0);

        $this->assertSame(0, $position->getId());
        $this->assertSame('', $position->getLink());
    }
}
