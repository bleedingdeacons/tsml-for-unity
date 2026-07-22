<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupViewFactory;
use TsmlForUnity\Members\TsmlMember;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\Members\Interfaces\MemberRepository;

/**
 * Tests for TsmlGroupViewFactory
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupViewFactory
 */
class TsmlGroupViewFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $factory = new TsmlGroupViewFactory(
            $this->createMock(GroupRepository::class),
            $this->createMock(MemberRepository::class)
        );

        $this->assertInstanceOf(GroupViewFactory::class, $factory);
    }

    /**
     * @test
     */
    public function create_from_returns_null_for_a_missing_group(): void
    {
        $groups = $this->createMock(GroupRepository::class);
        $groups->method('findById')->with(99)->willReturn(null);

        $factory = new TsmlGroupViewFactory($groups, $this->createMock(MemberRepository::class));

        $this->assertNull($factory->createFrom(99));
    }

    /**
     * @test
     */
    public function create_from_attaches_only_members_whose_home_group_matches(): void
    {
        $group = new TsmlGroup(
            id: 10,
            title: 'Tuesday Group',
            email: 'group@example.com',
            link: 'https://example.com/group'
        );

        $groups = $this->createMock(GroupRepository::class);
        $groups->method('findById')->with(10)->willReturn($group);

        $inGroup    = new TsmlMember(id: 1, anonymousName: 'In', homeGroup: 10);
        $otherGroup = new TsmlMember(id: 2, anonymousName: 'Out', homeGroup: 20);
        $noGroup    = new TsmlMember(id: 3, anonymousName: 'None');

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([$inGroup, $otherGroup, $noGroup]);

        $factory = new TsmlGroupViewFactory($groups, $members);
        $view = $factory->createFrom(10);

        $this->assertNotNull($view);
        $this->assertSame(10, $view->getId());
        $this->assertSame('Tuesday Group', $view->getTitle());
        $this->assertSame('group@example.com', $view->getEmail());
        $this->assertSame('https://example.com/group', $view->getLink());
        $this->assertSame([$inGroup], $view->getMembers());
    }

    /**
     * @test
     */
    public function create_from_yields_no_members_when_none_match(): void
    {
        $group = new TsmlGroup(id: 10, title: 'Lonely Group');

        $groups = $this->createMock(GroupRepository::class);
        $groups->method('findById')->with(10)->willReturn($group);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findAll')->willReturn([
            new TsmlMember(id: 1, homeGroup: 20),
        ]);

        $factory = new TsmlGroupViewFactory($groups, $members);
        $view = $factory->createFrom(10);

        $this->assertSame([], $view->getMembers());
    }
}
