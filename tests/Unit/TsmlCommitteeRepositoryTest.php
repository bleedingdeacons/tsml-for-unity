<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Committees\TsmlCommitteeFactory;
use TsmlForUnity\Committees\TsmlCommitteeFields;
use TsmlForUnity\Committees\TsmlCommitteeRepository;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Positions\TsmlPositionFields;
use Unity\Committees\Interfaces\CommitteeRepository;
use WP_Term;

/*
 * Tests for TsmlCommitteeRepository.
 *
 * Read-only over the term APIs. Built with a real TsmlCommitteeFactory rather
 * than a mock: the repository is typed to the concrete factory for
 * createFromTerm(), and the hydration it performs is part of what these tests
 * are checking -- a mock would assert the repository calls a collaborator
 * without ever proving a WP_Term becomes the right Committee.
 */

covers(\TsmlForUnity\Committees\TsmlCommitteeRepository::class);

beforeEach(function () {
    $this->repository = new TsmlCommitteeRepository(new TsmlCommitteeFactory());
});

/**
 * Build a WP_Term in the committee taxonomy.
 *
 * @param array<string, mixed> $overrides
 */
function committeeRepositoryTerm(int $id, string $slug, string $name, int $parent = 0): WP_Term
{
    return new WP_Term([
        'term_id'  => $id,
        'name'     => $name,
        'slug'     => $slug,
        'taxonomy' => TsmlCommitteeFields::TAXONOMY,
        'parent'   => $parent,
    ]);
}

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(CommitteeRepository::class);
});

// ── Lookups ──────────────────────────────────────────────────────
test('find by id hydrates the term', function () {
    Functions\expect('get_term')
        ->once()
        ->with(7, TsmlCommitteeFields::TAXONOMY)
        ->andReturn(committeeRepositoryTerm(7, 'telephones', 'Telephones'));

    $committee = $this->repository->findById(7);

    expect($committee)->not->toBeNull()
        ->and($committee->getSlug())->toBe('telephones');
});

test('find by slug hydrates the term', function () {
    Functions\expect('get_term_by')
        ->once()
        ->with('slug', 'telephones', TsmlCommitteeFields::TAXONOMY)
        ->andReturn(committeeRepositoryTerm(7, 'telephones', 'Telephones'));

    $committee = $this->repository->findBySlug('telephones');

    expect($committee)->not->toBeNull()
        ->and($committee->getId())->toBe(7);
});

/*
 * get_term_by() returns false rather than null or a WP_Error when nothing
 * matches, which is why the guard is an instanceof rather than a null check.
 */
test('find by slug returns null when nothing matches', function () {
    Functions\expect('get_term_by')->once()->andReturn(false);

    expect($this->repository->findBySlug('nope'))->toBeNull();
});

test('find by slug rejects an empty slug without querying', function () {
    Functions\expect('get_term_by')->never();

    expect($this->repository->findBySlug(''))->toBeNull();
});

// ── Listings ─────────────────────────────────────────────────────
/*
 * A committee nobody has joined yet is still part of the structure, so
 * hide_empty must be false or a new branch stays invisible until somebody
 * is assigned to it.
 */
test('find all asks for every term including empty ones', function () {
    Functions\expect('get_terms')
        ->once()
        ->with([
            'taxonomy'   => TsmlCommitteeFields::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ])
        ->andReturn([
            committeeRepositoryTerm(1, 'intergroup', 'Intergroup'),
            committeeRepositoryTerm(7, 'telephones', 'Telephones', 1),
        ]);

    $committees = $this->repository->findAll();

    expect($committees)->toHaveCount(2)
        ->and(array_map(
            static fn ($committee) => $committee->getSlug(),
            $committees
        ))->toBe(['intergroup', 'telephones']);
});

test('roots asks only for top level terms', function () {
    Functions\expect('get_terms')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['parent'])->toBe(0);
            return [committeeRepositoryTerm(1, 'intergroup', 'Intergroup')];
        });

    $roots = $this->repository->roots();

    expect($roots)->toHaveCount(1)
        ->and($roots[0]->isRoot())->toBeTrue();
});

test('children of asks for the committees one level down', function () {
    Functions\expect('get_terms')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['parent'])->toBe(1);
            return [committeeRepositoryTerm(7, 'telephones', 'Telephones', 1)];
        });

    $children = $this->repository->childrenOf(1);

    expect($children)->toHaveCount(1)
        ->and($children[0]->getId())->toBe(7);
});

test('a slug resolves to its term id before the tree is walked', function () {
    Functions\expect('get_term_by')
        ->once()
        ->with('slug', 'intergroup', TsmlCommitteeFields::TAXONOMY)
        ->andReturn(committeeRepositoryTerm(1, 'intergroup', 'Intergroup'));

    Functions\expect('get_terms')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['parent'])->toBe(1);
            return [];
        });

    expect($this->repository->childrenOf('intergroup'))->toBe([]);
});

test('an unknown slug answers empty without querying the tree', function () {
    Functions\expect('get_term_by')->once()->andReturn(false);
    Functions\expect('get_terms')->never();

    expect($this->repository->childrenOf('no-such-committee'))->toBe([]);
});

test('a non positive id answers empty without querying the tree', function () {
    Functions\expect('get_terms')->never();

    expect($this->repository->childrenOf(0))->toBe([])
        ->and($this->repository->childrenOf(-3))->toBe([]);
});

test('a term query that fails answers empty', function () {
    Functions\expect('get_terms')->once()->andReturn(new \WP_Error());

    expect($this->repository->findAll())->toBe([]);
});

/*
 * get_terms() hands back ints or strings when a caller sets 'fields' --
 * nothing here does, but the guard keeps a surprise from becoming a fatal
 * inside the factory.
 */
test('entries that are not terms are skipped', function () {
    Functions\expect('get_terms')->once()->andReturn([
        committeeRepositoryTerm(1, 'intergroup', 'Intergroup'),
        42,
        'telephones',
    ]);

    expect($this->repository->findAll())->toHaveCount(1);
});

test('terms from another taxonomy are skipped', function () {
    $foreign = committeeRepositoryTerm(9, 'news', 'News');
    $foreign->taxonomy = 'category';

    Functions\expect('get_terms')->once()->andReturn([
        committeeRepositoryTerm(1, 'intergroup', 'Intergroup'),
        $foreign,
    ]);

    expect($this->repository->findAll())->toHaveCount(1);
});

// ── Walking the hierarchy ────────────────────────────────────────
test('descendants of expands the whole branch', function () {
    Functions\expect('get_term_children')
        ->once()
        ->with(2, TsmlCommitteeFields::TAXONOMY)
        ->andReturn([5, 6]);

    Functions\expect('get_terms')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['include'])->toBe([5, 6]);
            return [
                committeeRepositoryTerm(5, 'pi-employment', 'Employment', 2),
                committeeRepositoryTerm(6, 'pi-health', 'Health', 2),
            ];
        });

    expect($this->repository->descendantsOf(2))->toHaveCount(2);
});

/*
 * The trap this guards: get_terms() treats an empty 'include' as no filter
 * at all, so passing one through would answer "this leaf has no children"
 * with the entire taxonomy.
 */
test('a leaf has no descendants and no second query', function () {
    Functions\expect('get_term_children')->once()->andReturn([]);
    Functions\expect('get_terms')->never();

    expect($this->repository->descendantsOf(5))->toBe([]);
});

test('descendants of answers empty when the children lookup fails', function () {
    Functions\expect('get_term_children')->once()->andReturn(new \WP_Error());
    Functions\expect('get_terms')->never();

    expect($this->repository->descendantsOf(5))->toBe([]);
});

test('ancestors of keeps nearest first', function () {
    Functions\expect('get_ancestors')
        ->once()
        ->with(6, TsmlCommitteeFields::TAXONOMY, 'taxonomy')
        ->andReturn([2, 1]);

    Functions\expect('get_term')
        ->twice()
        ->andReturnUsing(fn (int $id) => $id === 2
            ? committeeRepositoryTerm(2, 'public-information', 'Public Information', 1)
            : committeeRepositoryTerm(1, 'intergroup', 'Intergroup'));

    expect(array_map(
        static fn ($committee) => $committee->getSlug(),
        $this->repository->ancestorsOf(6)
    ))->toBe(['public-information', 'intergroup']);
});

test('a root has no ancestors', function () {
    Functions\expect('get_ancestors')->once()->andReturn([]);

    expect($this->repository->ancestorsOf(1))->toBe([]);
});

test('path to runs root first and ends with the committee', function () {
    Functions\expect('get_ancestors')->once()->andReturn([2, 1]);

    Functions\expect('get_term')->andReturnUsing(fn (int $id) => match ($id) {
        1 => committeeRepositoryTerm(1, 'intergroup', 'Intergroup'),
        2 => committeeRepositoryTerm(2, 'public-information', 'Public Information', 1),
        6 => committeeRepositoryTerm(6, 'pi-health', 'Health', 2),
        default => null,
    });

    expect(array_map(
        static fn ($committee) => $committee->getSlug(),
        $this->repository->pathTo(6)
    ))->toBe(['intergroup', 'public-information', 'pi-health']);
});

test('path to an unknown committee is empty', function () {
    Functions\expect('get_term')->once()->andReturn(null);
    Functions\expect('get_ancestors')->never();

    expect($this->repository->pathTo(404))->toBe([]);
});

// ── Assignments ──────────────────────────────────────────────────
test('for member returns the members committees', function () {
    Functions\expect('get_post_type')
        ->once()
        ->with(31)
        ->andReturn(TsmlMemberFields::POST_TYPE);

    Functions\expect('wp_get_object_terms')
        ->once()
        ->with(31, TsmlCommitteeFields::TAXONOMY, ['orderby' => 'name', 'order' => 'ASC'])
        ->andReturn([committeeRepositoryTerm(7, 'telephones', 'Telephones', 1)]);

    $committees = $this->repository->forMember(31);

    expect($committees)->toHaveCount(1)
        ->and($committees[0]->getSlug())->toBe('telephones');
});

/*
 * Members and positions carry the same taxonomy, so without the post-type
 * check a mixed-up ID would return a plausible but wrong answer.
 */
test('for member refuses an id that is not a member', function () {
    Functions\expect('get_post_type')
        ->once()
        ->andReturn(TsmlPositionFields::POST_TYPE);

    Functions\expect('wp_get_object_terms')->never();

    expect($this->repository->forMember(31))->toBe([]);
});

test('for member refuses a non positive id without querying', function () {
    Functions\expect('get_post_type')->never();
    Functions\expect('wp_get_object_terms')->never();

    expect($this->repository->forMember(0))->toBe([]);
});

test('for position asks about the position post type', function () {
    Functions\expect('get_post_type')
        ->once()
        ->andReturn(TsmlPositionFields::POST_TYPE);

    Functions\expect('wp_get_object_terms')
        ->once()
        ->andReturn([committeeRepositoryTerm(1, 'intergroup', 'Intergroup')]);

    expect($this->repository->forPosition(88))->toHaveCount(1);
});

/*
 * There is deliberately no test for wp_get_object_terms() returning a
 * WP_Error, though the repository guards against it and WordPress really
 * does return one for an unregistered taxonomy.
 *
 * bleedingdeacons/wp-mocks declares the stub as `: array`, and Patchwork
 * redefines a function's body while keeping its signature -- so returning
 * a WP_Error from it is a TypeError inside the stub, not a value the
 * repository ever sees. The guard is right for production and untestable
 * here; don't delete it for being uncovered.
 *
 * get_terms() has no such problem (a_term_query_that_fails_answers_empty
 * covers the equivalent path), because wp-mocks does not stub it at all
 * and Brain Monkey defines it fresh with no declared return type.
 */
// ── Members and positions in a committee ─────────────────────────
test('member ids in includes sub committees by default', function () {
    Functions\expect('get_posts')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['post_type'])->toBe(TsmlMemberFields::POST_TYPE)
                ->and($args['fields'])->toBe('ids')
                ->and($args['tax_query'][0])->toBe([
                'taxonomy'         => TsmlCommitteeFields::TAXONOMY,
                'field'            => 'term_id',
                'terms'            => [2],
                'include_children' => true,
            ]);

            return [11, 12, 13];
        });

    expect($this->repository->memberIdsIn(2))->toBe([11, 12, 13]);
});

test('member ids in can be limited to the committee itself', function () {
    Functions\expect('get_posts')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['tax_query'][0]['include_children'])->toBeFalse();
            return [11];
        });

    expect($this->repository->memberIdsIn(2, false))->toBe([11]);
});

test('member ids in accepts a slug', function () {
    Functions\expect('get_term_by')
        ->once()
        ->andReturn(committeeRepositoryTerm(7, 'telephones', 'Telephones', 1));

    Functions\expect('get_posts')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['tax_query'][0]['terms'])->toBe([7]);
            return ['11', '12'];
        });

    // get_posts() with 'fields' => 'ids' can hand back numeric strings
    // depending on the query path, so the IDs are cast rather than trusted.
    expect($this->repository->memberIdsIn('telephones'))->toBe([11, 12]);
});

test('member ids in an unknown committee answers empty without querying', function () {
    Functions\expect('get_term_by')->once()->andReturn(false);
    Functions\expect('get_posts')->never();

    expect($this->repository->memberIdsIn('no-such-committee'))->toBe([]);
});

test('position ids in asks for the position post type', function () {
    Functions\expect('get_posts')
        ->once()
        ->andReturnUsing(function (array $args) {
            expect($args['post_type'])->toBe(TsmlPositionFields::POST_TYPE);
            return [88];
        });

    expect($this->repository->positionIdsIn(1))->toBe([88]);
});
