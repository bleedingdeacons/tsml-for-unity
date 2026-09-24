<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Positions\TsmlPosition;
use Unity\Positions\Interfaces\Position;

/*
 * Tests for TsmlPosition entity
 */

covers(\TsmlForUnity\Positions\TsmlPosition::class);

it('implements position interface', function () {
    expect(new TsmlPosition())->toBeInstanceOf(Position::class);
});

it('applies sensible defaults', function () {
    $position = new TsmlPosition();

    expect($position->getId())->toBe(0)
        ->and($position->getMinimumSobriety())->toBe(6)
        ->and($position->getTermYears())->toBe(1)
        ->and($position->getEmail())->toBe('')
        ->and($position->getLongName())->toBe('')
        ->and($position->getShortDescription())->toBe('')
        ->and($position->getSummary())->toBe('')
        ->and($position->getLink())->toBe('')
        ->and($position->getUpdated())->toBe('');
});

it('exposes every field passed to the constructor', function () {
    $position = new TsmlPosition(
        id: 8,
        minimumSobriety: 24,
        termYears: 3,
        email: 'chair@example.com',
        longName: 'Intergroup Chair',
        shortDescription: 'Chairs the meeting',
        summary: 'Runs intergroup',
        link: 'https://example.com/chair',
        updated: '2026-06-01 10:00:00'
    );

    expect($position->getId())->toBe(8)
        ->and($position->getMinimumSobriety())->toBe(24)
        ->and($position->getTermYears())->toBe(3)
        ->and($position->getEmail())->toBe('chair@example.com')
        ->and($position->getLongName())->toBe('Intergroup Chair')
        ->and($position->getShortDescription())->toBe('Chairs the meeting')
        ->and($position->getSummary())->toBe('Runs intergroup')
        ->and($position->getLink())->toBe('https://example.com/chair')
        ->and($position->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('a fully populated position is valid even before it is saved', function () {
    expect(validPosition(['id' => 0])->isValid())->toBeTrue()
        ->and(validPosition(['id' => 5])->isValid())->toBeTrue();
});

test('is valid fails when any requirement is missing', function (array $overrides) {
    expect(validPosition($overrides)->isValid())->toBeFalse();
})->with([
    'no email'             => [['email' => '']],
    'no long name'         => [['longName' => '']],
    'no short description' => [['shortDescription' => '']],
    'no summary'           => [['summary' => '']],
    'sobriety below six'   => [['minimumSobriety' => 5]],
    'term below one year'  => [['termYears' => 0]],
]);

/**
 * @param array<string, mixed> $overrides
 */
function validPosition(array $overrides = []): TsmlPosition
{
    $defaults = [
        'id'               => 1,
        'minimumSobriety'  => 6,
        'termYears'        => 1,
        'email'            => 'chair@example.com',
        'longName'         => 'Intergroup Chair',
        'shortDescription' => 'Chairs the meeting',
        'summary'          => 'Runs intergroup',
    ];

    return new TsmlPosition(...array_merge($defaults, $overrides));
}
