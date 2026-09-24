<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupViewFactory;
use TsmlForUnity\Members\TsmlMember;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Groups\Interfaces\GroupViewFactory;
use Unity\Members\Interfaces\MemberRepository;

/*
 * Tests for TsmlGroupViewFactory
 */

covers(\TsmlForUnity\Groups\TsmlGroupViewFactory::class);

it('implements the factory interface', function () {
    $factory = new TsmlGroupViewFactory(
        $this->createMock(GroupRepository::class),
        $this->createMock(MemberRepository::class)
    );

    expect($factory)->toBeInstanceOf(GroupViewFactory::class);
});

test('create from returns null for a missing group', function () {
    $groups = $this->createMock(GroupRepository::class);
    $groups->method('findById')->with(99)->willReturn(null);

    $factory = new TsmlGroupViewFactory($groups, $this->createMock(MemberRepository::class));

    expect($factory->createFrom(99))->toBeNull();
});

test('create from attaches only members whose home group matches', function () {
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

    expect($view)->not->toBeNull()
        ->and($view->getId())->toBe(10)
        ->and($view->getTitle())->toBe('Tuesday Group')
        ->and($view->getEmail())->toBe('group@example.com')
        ->and($view->getLink())->toBe('https://example.com/group')
        ->and($view->getMembers())->toBe([$inGroup]);
});

test('create from yields no members when none match', function () {
    $group = new TsmlGroup(id: 10, title: 'Lonely Group');

    $groups = $this->createMock(GroupRepository::class);
    $groups->method('findById')->with(10)->willReturn($group);

    $members = $this->createMock(MemberRepository::class);
    $members->method('findAll')->willReturn([
        new TsmlMember(id: 1, homeGroup: 20),
    ]);

    $factory = new TsmlGroupViewFactory($groups, $members);
    $view = $factory->createFrom(10);

    expect($view->getMembers())->toBe([]);
});
