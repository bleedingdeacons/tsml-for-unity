<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionViewFactory;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\PositionRepository;
use Unity\Positions\Interfaces\PositionViewFactory;

/**
 * Tests for TsmlPositionViewFactory
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionViewFactory
 */
class TsmlPositionViewFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $factory = new TsmlPositionViewFactory(
            $this->createMock(PositionRepository::class),
            $this->createMock(MemberRepository::class)
        );

        $this->assertInstanceOf(PositionViewFactory::class, $factory);
    }

    /**
     * @test
     */
    public function create_from_returns_null_when_the_position_is_missing(): void
    {
        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(99)->willReturn(null);

        $factory = new TsmlPositionViewFactory($positions, $this->createMock(MemberRepository::class));

        $this->assertNull($factory->createFrom(99));
    }

    /**
     * @test
     */
    public function create_from_returns_a_vacant_view_when_no_member_matches(): void
    {
        $position = new TsmlPosition(id: 5, shortDescription: 'Chair');

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(5)->willReturn($position);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([
            new TsmlMember(id: 1, intergroupPosition: 99),
        ]);

        $factory = new TsmlPositionViewFactory($positions, $members);
        $view = $factory->createFrom(5);

        $this->assertNotNull($view);
        $this->assertTrue($view->isVacant());
        $this->assertSame($position, $view->getPosition());
    }

    /**
     * @test
     */
    public function create_from_binds_the_single_matching_member(): void
    {
        $position = new TsmlPosition(id: 5, shortDescription: 'Chair');
        $matching = new TsmlMember(id: 1, anonymousName: 'John D.', intergroupPosition: 5);

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(5)->willReturn($position);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([
            $matching,
            new TsmlMember(id: 2, intergroupPosition: 6),
        ]);

        $factory = new TsmlPositionViewFactory($positions, $members);
        $view = $factory->createFrom(5);

        $this->assertFalse($view->isVacant());
        $this->assertSame($matching, $view->getMember());
    }

    /**
     * @test
     */
    public function create_from_picks_the_latest_rotation_when_several_members_match(): void
    {
        $position = new TsmlPosition(id: 5, shortDescription: 'Chair');
        $older  = new TsmlMember(id: 1, anonymousName: 'Older', intergroupPosition: 5, intergroupPositionRotation: '2024-01-01');
        $newer  = new TsmlMember(id: 2, anonymousName: 'Newer', intergroupPosition: 5, intergroupPositionRotation: '2026-01-01');

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(5)->willReturn($position);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([$older, $newer]);

        $factory = new TsmlPositionViewFactory($positions, $members);
        $view = $factory->createFrom(5);

        $this->assertSame($newer, $view->getMember());
        $this->assertSame([$newer], $view->getMembers());
    }

    /**
     * @test
     */
    public function create_all_builds_one_view_per_position_sorted_by_title(): void
    {
        $chair = new TsmlPosition(id: 5, shortDescription: 'Chair');
        $treasurer = new TsmlPosition(id: 6, shortDescription: 'Aardvark');

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findAll')->willReturn([$chair, $treasurer]);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([
            new TsmlMember(id: 1, anonymousName: 'John', intergroupPosition: 5),
        ]);

        $factory = new TsmlPositionViewFactory($positions, $members);
        $views = $factory->createAll();

        $this->assertCount(2, $views);
        // Sorted case-insensitively by title: "Aardvark" before "Chair".
        $this->assertSame('Aardvark', $views[0]->getTitle());
        $this->assertSame('Chair', $views[1]->getTitle());
        // The Chair view has its member bound; the Aardvark view is vacant.
        $this->assertTrue($views[0]->isVacant());
        $this->assertFalse($views[1]->isVacant());
    }

    /**
     * @test
     */
    public function create_all_returns_an_empty_array_when_there_are_no_positions(): void
    {
        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findAll')->willReturn([]);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([]);

        $factory = new TsmlPositionViewFactory($positions, $members);

        $this->assertSame([], $factory->createAll());
    }
}
