<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Members\TsmlMemberViewFactory;
use Unity\Members\PreferredContact;
use TsmlForUnity\Positions\TsmlPosition;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Members\Interfaces\MemberViewFactory;
use Unity\Positions\Interfaces\PositionRepository;

/*
 * Tests for TsmlMemberViewFactory
 */

covers(\TsmlForUnity\Members\TsmlMemberViewFactory::class);

it('implements the factory interface', function () {
    $factory = new TsmlMemberViewFactory(
        $this->createMock(MemberRepository::class),
        $this->createMock(GroupRepository::class),
        $this->createMock(PositionRepository::class)
    );

    expect($factory)->toBeInstanceOf(MemberViewFactory::class);
});

it('resolves group and position names', function () {
    $member = new TsmlMember(
        id: 1,
        anonymousName: 'John D.',
        personalEmail: 'john@example.com',
        mobileNumber: '07700 900123',
        landlineNumber: '0117 496 0000',
        preferredContact: PreferredContact::Landline,
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

    expect($views)->toHaveCount(1)
        ->and($views[0]->getAnonymousName())->toBe('John D.');
    // TsmlMemberView is built positionally, so a field inserted mid-list
    // rebinds every argument after it. Assert across the join.
    expect($views[0]->getMobileNumber())->toBe('07700 900123')
        ->and($views[0]->getLandlineNumber())->toBe('0117 496 0000')
        ->and($views[0]->getPreferredContact())->toBe(PreferredContact::Landline)
        ->and($views[0]->getHomeGroupName())->toBe('Tuesday Group')
        ->and($views[0]->getPositionName())->toBe('Intergroup Chair')
        ->and($views[0]->getRotationDate())->toBe('2026-01-01');
});

it('leaves names blank when a member has no group or position', function () {
    $member = new TsmlMember(id: 1, anonymousName: 'Solo');

    $members = $this->createMock(MemberRepository::class);
    $members->method('findById')->with(1)->willReturn($member);

    $groups = $this->createMock(GroupRepository::class);
    $groups->expects($this->never())->method('findById');

    $positions = $this->createMock(PositionRepository::class);
    $positions->expects($this->never())->method('findById');

    $factory = new TsmlMemberViewFactory($members, $groups, $positions);
    $views = $factory->createFromSource([1]);

    expect($views[0]->getHomeGroupName())->toBe('')
        ->and($views[0]->getPositionName())->toBe('')
        ->and($views[0]->hasHomeGroup())->toBeFalse()
        ->and($views[0]->hasPosition())->toBeFalse();
});

test('a deleted group or position resolves to an empty name', function () {
    $member = new TsmlMember(id: 1, homeGroup: 10, intergroupPosition: 5);

    $members = $this->createMock(MemberRepository::class);
    $members->method('findById')->with(1)->willReturn($member);

    $groups = $this->createMock(GroupRepository::class);
    $groups->method('findById')->with(10)->willReturn(null);

    $positions = $this->createMock(PositionRepository::class);
    $positions->method('findById')->with(5)->willReturn(null);

    $factory = new TsmlMemberViewFactory($members, $groups, $positions);
    $views = $factory->createFromSource([1]);

    expect($views[0]->getHomeGroupName())->toBe('')
        ->and($views[0]->getPositionName())->toBe('');
    // The IDs are still carried even when the name can't be resolved.
    expect($views[0]->hasHomeGroup())->toBeTrue()
        ->and($views[0]->hasPosition())->toBeTrue();
});

it('skips non positive ids and missing members', function () {
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

    expect($views)->toHaveCount(1)
        ->and($views[0]->getAnonymousName())->toBe('Real');
});

it('resolves a shared group name only once per call', function () {
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

    expect($views)->toHaveCount(2)
        ->and($views[0]->getHomeGroupName())->toBe('Shared Group')
        ->and($views[1]->getHomeGroupName())->toBe('Shared Group');
});

test('an empty source list yields no views', function () {
    $factory = new TsmlMemberViewFactory(
        $this->createMock(MemberRepository::class),
        $this->createMock(GroupRepository::class),
        $this->createMock(PositionRepository::class)
    );

    expect($factory->createFromSource([]))->toBe([]);
});
