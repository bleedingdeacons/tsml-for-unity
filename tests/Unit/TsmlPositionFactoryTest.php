<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Positions\TsmlPositionFactory;
use TsmlForUnity\Positions\TsmlPositionFields;
use Unity\Positions\Interfaces\PositionFactory;

/*
 * Tests for TsmlPositionFactory
 */

covers(\TsmlForUnity\Positions\TsmlPositionFactory::class);

beforeEach(function () {
    $this->factory = new TsmlPositionFactory();
});

it('implements the factory interface', function () {
    expect($this->factory)->toBeInstanceOf(PositionFactory::class);
});

test('create from source returns null when the post is missing', function () {
    Functions\expect('get_post')->with(99)->andReturn(null);

    expect($this->factory->createFromSource(99))->toBeNull();
});

test('create from source returns null for the wrong post type', function () {
    Functions\expect('get_post')->with(99)->andReturn(
        (object) ['post_type' => 'page', 'post_modified_gmt' => '']
    );

    expect($this->factory->createFromSource(99))->toBeNull();
});

test('create from source hydrates a position from acf fields', function () {
    Functions\expect('get_post')->with(42)->andReturn((object) [
        'post_type'         => TsmlPositionFields::POST_TYPE,
        'post_modified_gmt' => '2026-06-01 10:00:00',
    ]);

    Functions\expect('get_fields')->with(42)->andReturn([
        TsmlPositionFields::MINIMUM_SOBRIETY  => '24',
        TsmlPositionFields::TERM_YEARS        => '3',
        TsmlPositionFields::EMAIL_ADDRESS     => 'chair@example.com',
        TsmlPositionFields::LONG_NAME         => 'Intergroup &amp; Chair',
        TsmlPositionFields::SHORT_DESCRIPTION => 'Chairs',
        TsmlPositionFields::SUMMARY           => 'Runs intergroup',
    ]);

    Functions\expect('get_permalink')->with(42)->andReturn('https://example.com/chair');

    $position = $this->factory->createFromSource(42);

    expect($position)->not->toBeNull()
        ->and($position->getId())->toBe(42)
        ->and($position->getMinimumSobriety())->toBe(24)
        ->and($position->getTermYears())->toBe(3)
        ->and($position->getEmail())->toBe('chair@example.com');
    // HTML entities in the long name are decoded.
    expect($position->getLongName())->toBe('Intergroup & Chair')
        ->and($position->getShortDescription())->toBe('Chairs')
        ->and($position->getSummary())->toBe('Runs intergroup')
        ->and($position->getLink())->toBe('https://example.com/chair')
        ->and($position->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('create from source applies defaults when acf returns nothing', function () {
    Functions\expect('get_post')->with(42)->andReturn((object) [
        'post_type'         => TsmlPositionFields::POST_TYPE,
        'post_modified_gmt' => '',
    ]);

    Functions\expect('get_fields')->with(42)->andReturn(false);
    Functions\expect('get_permalink')->with(42)->andReturn(false);

    $position = $this->factory->createFromSource(42);

    expect($position->getMinimumSobriety())->toBe(6)
        ->and($position->getTermYears())->toBe(1)
        ->and($position->getEmail())->toBe('')
        ->and($position->getLongName())->toBe('')
        ->and($position->getLink())->toBe('');
});

test('create new builds a position and resolves the permalink', function () {
    Functions\expect('get_permalink')->with(7)->andReturn('https://example.com/p/7');

    $position = $this->factory->createNew(
        7,
        12,
        2,
        'sec@example.com',
        'Secretary',
        'Takes minutes',
        'Keeps records'
    );

    expect($position->getId())->toBe(7)
        ->and($position->getMinimumSobriety())->toBe(12)
        ->and($position->getTermYears())->toBe(2)
        ->and($position->getEmail())->toBe('sec@example.com')
        ->and($position->getLongName())->toBe('Secretary')
        ->and($position->getLink())->toBe('https://example.com/p/7');
});

test('create new skips the permalink lookup for an unsaved position', function () {
    // id 0 means "not persisted": get_permalink must not be called.
    Functions\expect('get_permalink')->never();

    $position = $this->factory->createNew(0);

    expect($position->getId())->toBe(0)
        ->and($position->getLink())->toBe('');
});
