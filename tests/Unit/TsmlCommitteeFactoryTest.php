<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Committees\TsmlCommitteeFactory;
use TsmlForUnity\Committees\TsmlCommitteeFields;
use Unity\Committees\Interfaces\Committee;
use Unity\Committees\Interfaces\CommitteeFactory;
use WP_Term;

/*
 * Tests for TsmlCommitteeFactory.
 *
 * Two entry points: createFromSource() fetches a term by ID, createFromTerm()
 * hydrates one the caller already holds. Everything the first does beyond
 * fetching, the second does too, so the guards are tested on both.
 */

covers(\TsmlForUnity\Committees\TsmlCommitteeFactory::class);

beforeEach(function () {
    $this->factory = new TsmlCommitteeFactory();
});

/**
 * Build a WP_Term in the committee taxonomy.
 *
 * @param array<string, mixed> $overrides
 */
function committeeFactoryTerm(array $overrides = []): WP_Term
{
    return new WP_Term(array_merge([
        'term_id'     => 7,
        'name'        => 'Telephones',
        'slug'        => 'telephones',
        'taxonomy'    => TsmlCommitteeFields::TAXONOMY,
        'description' => 'The helpline',
        'parent'      => 1,
    ], $overrides));
}

it('implements the factory interface', function () {
    expect($this->factory)->toBeInstanceOf(CommitteeFactory::class);
});

it('hydrates every field from the term', function () {
    $committee = $this->factory->createFromTerm(committeeFactoryTerm());

    expect($committee)->toBeInstanceOf(Committee::class)
        ->and($committee->getId())->toBe(7)
        ->and($committee->getName())->toBe('Telephones')
        ->and($committee->getSlug())->toBe('telephones')
        ->and($committee->getDescription())->toBe('The helpline')
        ->and($committee->getParentId())->toBe(1)
        ->and($committee->isRoot())->toBeFalse();
});

/*
 * Term names are stored HTML-encoded by WordPress, exactly as
 * TsmlPositionFactory decodes position names.
 */
it('decodes entities in the name', function () {
    $committee = $this->factory->createFromTerm(
        committeeFactoryTerm(['name' => 'Health &amp; Corrections'])
    );

    expect($committee)->not->toBeNull()
        ->and($committee->getName())->toBe('Health & Corrections');
});

it('refuses a term from another taxonomy', function () {
    expect($this->factory->createFromTerm(committeeFactoryTerm(['taxonomy' => 'category'])))->toBeNull();
});

test('create from source fetches the term and hydrates it', function () {
    $term = committeeFactoryTerm();

    Functions\expect('get_term')
        ->once()
        ->with(7, TsmlCommitteeFields::TAXONOMY)
        ->andReturn($term);

    $committee = $this->factory->createFromSource(7);

    expect($committee)->not->toBeNull()
        ->and($committee->getSlug())->toBe('telephones');
});

test('create from source returns null for a missing term', function () {
    Functions\expect('get_term')->once()->andReturn(null);

    expect($this->factory->createFromSource(404))->toBeNull();
});

/*
 * get_term() returns a WP_Error for an unregistered taxonomy. There is no
 * separate is_wp_error() branch to test: a WP_Error is not a WP_Term, so
 * the instanceof check already refuses it, and a second guard would be a
 * condition no input could reach.
 */
test('create from source returns null for an error', function () {
    Functions\expect('get_term')->once()->andReturn(new \WP_Error());

    expect($this->factory->createFromSource(7))->toBeNull();
});

test('create from source rejects a non positive id without querying', function () {
    Functions\expect('get_term')->never();

    expect($this->factory->createFromSource(0))->toBeNull()
        ->and($this->factory->createFromSource(-1))->toBeNull();
});
