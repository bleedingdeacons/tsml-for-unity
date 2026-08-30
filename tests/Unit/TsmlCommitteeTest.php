<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Committees\TsmlCommittee;
use TsmlForUnity\Tests\TestCase;
use Unity\Committees\Interfaces\Committee;

/**
 * Tests for TsmlCommittee.
 *
 * A value object over a taxonomy term: accessors and the one derived answer,
 * isRoot().
 *
 * @covers \TsmlForUnity\Committees\TsmlCommittee
 */
class TsmlCommitteeTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_the_committee_interface(): void
    {
        $this->assertInstanceOf(Committee::class, new TsmlCommittee());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_it_was_built_with(): void
    {
        $committee = new TsmlCommittee(
            id: 12,
            slug: 'public-information-health',
            name: 'Health',
            description: 'Carrying the message into healthcare settings',
            parentId: 7
        );

        $this->assertSame(12, $committee->getId());
        $this->assertSame('public-information-health', $committee->getSlug());
        $this->assertSame('Health', $committee->getName());
        $this->assertSame(
            'Carrying the message into healthcare settings',
            $committee->getDescription()
        );
        $this->assertSame(7, $committee->getParentId());
    }

    /**
     * @test
     */
    public function it_defaults_to_an_empty_root_committee(): void
    {
        $committee = new TsmlCommittee();

        $this->assertSame(0, $committee->getId());
        $this->assertSame('', $committee->getSlug());
        $this->assertSame('', $committee->getName());
        $this->assertSame('', $committee->getDescription());
        $this->assertSame(0, $committee->getParentId());
    }

    /**
     * @test
     */
    public function a_committee_without_a_parent_is_a_root(): void
    {
        $this->assertTrue((new TsmlCommittee(id: 3, parentId: 0))->isRoot());
    }

    /**
     * @test
     */
    public function a_committee_with_a_parent_is_not_a_root(): void
    {
        $this->assertFalse((new TsmlCommittee(id: 3, parentId: 1))->isRoot());
    }
}
