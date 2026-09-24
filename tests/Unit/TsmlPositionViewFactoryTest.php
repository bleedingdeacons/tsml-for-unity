<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionViewFactory;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\PositionRepository;
use Unity\Positions\Interfaces\PositionViewFactory;

/*
 * Tests for TsmlPositionViewFactory
 */

covers(\TsmlForUnity\Positions\TsmlPositionViewFactory::class);

it('implements the factory interface', function () {
    $factory = new TsmlPositionViewFactory(
        $this->createMock(PositionRepository::class),
        $this->createMock(MemberRepository::class)
    );

    expect($factory)->toBeInstanceOf(PositionViewFactory::class);
});

test('create from returns null when the position is missing', function () {
    $positions = $this->createMock(PositionRepository::class);
    $positions->method('findById')->with(99)->willReturn(null);

    $factory = new TsmlPositionViewFactory($positions, $this->createMock(MemberRepository::class));

    expect($factory->createFrom(99))->toBeNull();
});

test('create from returns a vacant view when no member matches', function () {
    $position = new TsmlPosition(id: 5, shortDescription: 'Chair');

    $positions = $this->createMock(PositionRepository::class);
    $positions->method('findById')->with(5)->willReturn($position);

    $members = $this->createMock(MemberRepository::class);
    $members->method('findAll')->willReturn([
        new TsmlMember(id: 1, intergroupPosition: 99),
    ]);

    $factory = new TsmlPositionViewFactory($positions, $members);
    $view = $factory->createFrom(5);

    expect($view)->not->toBeNull()
        ->and($view->isVacant())->toBeTrue()
        ->and($view->getPosition())->toBe($position);
});

test('create from binds the single matching member', function () {
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

    expect($view->isVacant())->toBeFalse()
        ->and($view->getMember())->toBe($matching);
});

test('create from picks the latest rotation when several members match', function () {
    $position = new TsmlPosition(id: 5, shortDescription: 'Chair');
    $older  = new TsmlMember(id: 1, anonymousName: 'Older', intergroupPosition: 5, intergroupPositionRotation: '2024-01-01');
    $newer  = new TsmlMember(id: 2, anonymousName: 'Newer', intergroupPosition: 5, intergroupPositionRotation: '2026-01-01');

    $positions = $this->createMock(PositionRepository::class);
    $positions->method('findById')->with(5)->willReturn($position);

    $members = $this->createMock(MemberRepository::class);
    $members->method('findAll')->willReturn([$older, $newer]);

    $factory = new TsmlPositionViewFactory($positions, $members);
    $view = $factory->createFrom(5);

    expect($view->getMember())->toBe($newer)
        ->and($view->getMembers())->toBe([$newer]);
});

test('create all builds one view per position sorted by title', function () {
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

    expect($views)->toHaveCount(2);
    // Sorted case-insensitively by title: "Aardvark" before "Chair".
    expect($views[0]->getTitle())->toBe('Aardvark')
        ->and($views[1]->getTitle())->toBe('Chair');
    // The Chair view has its member bound; the Aardvark view is vacant.
    expect($views[0]->isVacant())->toBeTrue()
        ->and($views[1]->isVacant())->toBeFalse();
});

test('create all returns an empty array when there are no positions', function () {
    $positions = $this->createMock(PositionRepository::class);
    $positions->method('findAll')->willReturn([]);

    $members = $this->createMock(MemberRepository::class);
    $members->method('findAll')->willReturn([]);

    $factory = new TsmlPositionViewFactory($positions, $members);

    expect($factory->createAll())->toBe([]);
});
