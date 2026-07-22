<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Members\TsmlMemberViewFactory;
use TsmlForUnity\Positions\TsmlPosition;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\Interfaces\MemberViewFactory;
use Unity\Positions\Interfaces\PositionRepository;

/**
 * Tests for TsmlMemberViewFactory
 *
 * @covers \TsmlForUnity\Members\TsmlMemberViewFactory
 */
class TsmlMemberViewFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $factory = new TsmlMemberViewFactory(
            $this->createMock(MemberRepository::class),
            $this->createMock(GroupRepository::class),
            $this->createMock(PositionRepository::class)
        );

        $this->assertInstanceOf(MemberViewFactory::class, $factory);
    }

    /**
     * @test
     */
    public function it_resolves_group_and_position_names(): void
    {
        $member = new TsmlMember(
            id: 1,
            anonymousName: 'John D.',
            personalEmail: 'john@example.com',
            homeGroup: 10,
            intergroupPosition: 5,
            intergroupPositionRotation: '2026-01-01',
        );

        $members = $this->createMock(MemberRepository::class);
        $members->method('findById')->with(1)->willReturn($member);

        $groups = $this->createMock(GroupRepository::class);
        $groups->method('findById')->with(10)->willReturn(new TsmlGroup(id: 10, title: 'Tuesday Group'));

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(5)->willReturn(new TsmlPosition(id: 5, longName: 'Intergroup Chair'));

        $factory = new TsmlMemberViewFactory($members, $groups, $positions);
        $views = $factory->createFromSource([1]);

        $this->assertCount(1, $views);
        $this->assertSame('John D.', $views[0]->getAnonymousName());
        $this->assertSame('Tuesday Group', $views[0]->getHomeGroupName());
        $this->assertSame('Intergroup Chair', $views[0]->getPositionName());
        $this->assertSame('2026-01-01', $views[0]->getRotationDate());
    }

    /**
     * @test
     */
    public function it_leaves_names_blank_when_a_member_has_no_group_or_position(): void
    {
        $member = new TsmlMember(id: 1, anonymousName: 'Solo');

        $members = $this->createMock(MemberRepository::class);
        $members->method('findById')->with(1)->willReturn($member);

        $groups = $this->createMock(GroupRepository::class);
        $groups->expects($this->never())->method('findById');

        $positions = $this->createMock(PositionRepository::class);
        $positions->expects($this->never())->method('findById');

        $factory = new TsmlMemberViewFactory($members, $groups, $positions);
        $views = $factory->createFromSource([1]);

        $this->assertSame('', $views[0]->getHomeGroupName());
        $this->assertSame('', $views[0]->getPositionName());
        $this->assertFalse($views[0]->hasHomeGroup());
        $this->assertFalse($views[0]->hasPosition());
    }

    /**
     * @test
     */
    public function a_deleted_group_or_position_resolves_to_an_empty_name(): void
    {
        $member = new TsmlMember(id: 1, homeGroup: 10, intergroupPosition: 5);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findById')->with(1)->willReturn($member);

        $groups = $this->createMock(GroupRepository::class);
        $groups->method('findById')->with(10)->willReturn(null);

        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findById')->with(5)->willReturn(null);

        $factory = new TsmlMemberViewFactory($members, $groups, $positions);
        $views = $factory->createFromSource([1]);

        $this->assertSame('', $views[0]->getHomeGroupName());
        $this->assertSame('', $views[0]->getPositionName());
        // The IDs are still carried even when the name can't be resolved.
        $this->assertTrue($views[0]->hasHomeGroup());
        $this->assertTrue($views[0]->hasPosition());
    }

    /**
     * @test
     */
    public function it_skips_non_positive_ids_and_missing_members(): void
    {
        $members = $this->createMock(MemberRepository::class);
        $members->method('findById')->willReturnCallback(
            fn (int $id) => $id === 2 ? new TsmlMember(id: 2, anonymousName: 'Real') : null
        );

        $factory = new TsmlMemberViewFactory(
            $members,
            $this->createMock(GroupRepository::class),
            $this->createMock(PositionRepository::class)
        );

        // 0 and -1 skipped before lookup; 99 looked up but missing; 2 found.
        $views = $factory->createFromSource([0, -1, 99, 2]);

        $this->assertCount(1, $views);
        $this->assertSame('Real', $views[0]->getAnonymousName());
    }

    /**
     * @test
     */
    public function it_resolves_a_shared_group_name_only_once_per_call(): void
    {
        $memberA = new TsmlMember(id: 1, homeGroup: 10);
        $memberB = new TsmlMember(id: 2, homeGroup: 10);

        $members = $this->createMock(MemberRepository::class);
        $members->method('findById')->willReturnCallback(
            fn (int $id) => $id === 1 ? $memberA : $memberB
        );

        $groups = $this->createMock(GroupRepository::class);
        // Two members share group 10, but the repository is hit only once.
        $groups->expects($this->once())
            ->method('findById')
            ->with(10)
            ->willReturn(new TsmlGroup(id: 10, title: 'Shared Group'));

        $factory = new TsmlMemberViewFactory(
            $members,
            $groups,
            $this->createMock(PositionRepository::class)
        );

        $views = $factory->createFromSource([1, 2]);

        $this->assertCount(2, $views);
        $this->assertSame('Shared Group', $views[0]->getHomeGroupName());
        $this->assertSame('Shared Group', $views[1]->getHomeGroupName());
    }

    /**
     * @test
     */
    public function an_empty_source_list_yields_no_views(): void
    {
        $factory = new TsmlMemberViewFactory(
            $this->createMock(MemberRepository::class),
            $this->createMock(GroupRepository::class),
            $this->createMock(PositionRepository::class)
        );

        $this->assertSame([], $factory->createFromSource([]));
    }
}
