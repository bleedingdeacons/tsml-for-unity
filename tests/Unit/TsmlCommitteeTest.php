<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Committees\TsmlCommittee;
use Unity\Committees\Interfaces\Committee;

/*
 * Tests for TsmlCommittee.
 *
 * A value object over a taxonomy term: accessors and the one derived answer,
 * isRoot().
 */

covers(\TsmlForUnity\Committees\TsmlCommittee::class);

it('implements the committee interface', function () {
    expect(new TsmlCommittee())->toBeInstanceOf(Committee::class);
});

it('exposes every field it was built with', function () {
    $committee = new TsmlCommittee(
        id: 12,
        slug: 'public-information-health',
        name: 'Health',
        description: 'Carrying the message into healthcare settings',
        parentId: 7
    );

    expect($committee->getId())->toBe(12)
        ->and($committee->getSlug())->toBe('public-information-health')
        ->and($committee->getName())->toBe('Health')
        ->and($committee->getDescription())->toBe('Carrying the message into healthcare settings')
        ->and($committee->getParentId())->toBe(7);
});

it('defaults to an empty root committee', function () {
    $committee = new TsmlCommittee();

    expect($committee->getId())->toBe(0)
        ->and($committee->getSlug())->toBe('')
        ->and($committee->getName())->toBe('')
        ->and($committee->getDescription())->toBe('')
        ->and($committee->getParentId())->toBe(0);
});

test('a committee without a parent is a root', function () {
    expect((new TsmlCommittee(id: 3, parentId: 0))->isRoot())->toBeTrue();
});

test('a committee with a parent is not a root', function () {
    expect((new TsmlCommittee(id: 3, parentId: 1))->isRoot())->toBeFalse();
});
