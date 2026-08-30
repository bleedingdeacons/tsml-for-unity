<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Committees\TsmlCommitteeFactory;
use TsmlForUnity\Committees\TsmlCommitteeFields;
use TsmlForUnity\Committees\TsmlCommitteeRepository;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Tests\TestCase;
use Unity\Committees\Interfaces\CommitteeRepository;
use WP_Term;

/**
 * Tests for TsmlCommitteeRepository.
 *
 * Read-only over the term APIs. Built with a real TsmlCommitteeFactory rather
 * than a mock: the repository is typed to the concrete factory for
 * createFromTerm(), and the hydration it performs is part of what these tests
 * are checking -- a mock would assert the repository calls a collaborator
 * without ever proving a WP_Term becomes the right Committee.
 *
 * @covers \TsmlForUnity\Committees\TsmlCommitteeRepository
 */
class TsmlCommitteeRepositoryTest extends TestCase
{
    private TsmlCommitteeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new TsmlCommitteeRepository(new TsmlCommitteeFactory());
    }

    /**
     * Build a WP_Term in the committee taxonomy.
     *
     * @param array<string, mixed> $overrides
     */
    private function term(int $id, string $slug, string $name, int $parent = 0): WP_Term
    {
        return new WP_Term([
            'term_id'  => $id,
            'name'     => $name,
            'slug'     => $slug,
            'taxonomy' => TsmlCommitteeFields::TAXONOMY,
            'parent'   => $parent,
        ]);
    }

    /**
     * @test
     */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(CommitteeRepository::class, $this->repository);
    }

    // ── Lookups ──────────────────────────────────────────────────────

    /**
     * @test
     */
    public function find_by_id_hydrates_the_term(): void
    {
        Functions\expect('get_term')
            ->once()
            ->with(7, TsmlCommitteeFields::TAXONOMY)
            ->andReturn($this->term(7, 'telephones', 'Telephones'));

        $committee = $this->repository->findById(7);

        $this->assertNotNull($committee);
        $this->assertSame('telephones', $committee->getSlug());
    }

    /**
     * @test
     */
    public function find_by_slug_hydrates_the_term(): void
    {
        Functions\expect('get_term_by')
            ->once()
            ->with('slug', 'telephones', TsmlCommitteeFields::TAXONOMY)
            ->andReturn($this->term(7, 'telephones', 'Telephones'));

        $committee = $this->repository->findBySlug('telephones');

        $this->assertNotNull($committee);
        $this->assertSame(7, $committee->getId());
    }

    /**
     * get_term_by() returns false rather than null or a WP_Error when nothing
     * matches, which is why the guard is an instanceof rather than a null check.
     *
     * @test
     */
    public function find_by_slug_returns_null_when_nothing_matches(): void
    {
        Functions\expect('get_term_by')->once()->andReturn(false);

        $this->assertNull($this->repository->findBySlug('nope'));
    }

    /**
     * @test
     */
    public function find_by_slug_rejects_an_empty_slug_without_querying(): void
    {
        Functions\expect('get_term_by')->never();

        $this->assertNull($this->repository->findBySlug(''));
    }

    // ── Listings ─────────────────────────────────────────────────────

    /**
     * A committee nobody has joined yet is still part of the structure, so
     * hide_empty must be false or a new branch stays invisible until somebody
     * is assigned to it.
     *
     * @test
     */
    public function find_all_asks_for_every_term_including_empty_ones(): void
    {
        Functions\expect('get_terms')
            ->once()
            ->with([
                'taxonomy'   => TsmlCommitteeFields::TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ])
            ->andReturn([
                $this->term(1, 'intergroup', 'Intergroup'),
                $this->term(7, 'telephones', 'Telephones', 1),
            ]);

        $committees = $this->repository->findAll();

        $this->assertCount(2, $committees);
        $this->assertSame(['intergroup', 'telephones'], array_map(
            static fn ($committee) => $committee->getSlug(),
            $committees
        ));
    }

    /**
     * @test
     */
    public function roots_asks_only_for_top_level_terms(): void
    {
        Functions\expect('get_terms')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame(0, $args['parent']);
                return [$this->term(1, 'intergroup', 'Intergroup')];
            });

        $roots = $this->repository->roots();

        $this->assertCount(1, $roots);
        $this->assertTrue($roots[0]->isRoot());
    }

    /**
     * @test
     */
    public function children_of_asks_for_the_committees_one_level_down(): void
    {
        Functions\expect('get_terms')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame(1, $args['parent']);
                return [$this->term(7, 'telephones', 'Telephones', 1)];
            });

        $children = $this->repository->childrenOf(1);

        $this->assertCount(1, $children);
        $this->assertSame(7, $children[0]->getId());
    }

    /**
     * @test
     */
    public function a_slug_resolves_to_its_term_id_before_the_tree_is_walked(): void
    {
        Functions\expect('get_term_by')
            ->once()
            ->with('slug', 'intergroup', TsmlCommitteeFields::TAXONOMY)
            ->andReturn($this->term(1, 'intergroup', 'Intergroup'));

        Functions\expect('get_terms')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame(1, $args['parent']);
                return [];
            });

        $this->assertSame([], $this->repository->childrenOf('intergroup'));
    }

    /**
     * @test
     */
    public function an_unknown_slug_answers_empty_without_querying_the_tree(): void
    {
        Functions\expect('get_term_by')->once()->andReturn(false);
        Functions\expect('get_terms')->never();

        $this->assertSame([], $this->repository->childrenOf('no-such-committee'));
    }

    /**
     * @test
     */
    public function a_non_positive_id_answers_empty_without_querying_the_tree(): void
    {
        Functions\expect('get_terms')->never();

        $this->assertSame([], $this->repository->childrenOf(0));
        $this->assertSame([], $this->repository->childrenOf(-3));
    }

    /**
     * @test
     */
    public function a_term_query_that_fails_answers_empty(): void
    {
        Functions\expect('get_terms')->once()->andReturn(new \WP_Error());

        $this->assertSame([], $this->repository->findAll());
    }

    /**
     * get_terms() hands back ints or strings when a caller sets 'fields' --
     * nothing here does, but the guard keeps a surprise from becoming a fatal
     * inside the factory.
     *
     * @test
     */
    public function entries_that_are_not_terms_are_skipped(): void
    {
        Functions\expect('get_terms')->once()->andReturn([
            $this->term(1, 'intergroup', 'Intergroup'),
            42,
            'telephones',
        ]);

        $this->assertCount(1, $this->repository->findAll());
    }

    /**
     * @test
     */
    public function terms_from_another_taxonomy_are_skipped(): void
    {
        $foreign = $this->term(9, 'news', 'News');
        $foreign->taxonomy = 'category';

        Functions\expect('get_terms')->once()->andReturn([
            $this->term(1, 'intergroup', 'Intergroup'),
            $foreign,
        ]);

        $this->assertCount(1, $this->repository->findAll());
    }

    // ── Walking the hierarchy ────────────────────────────────────────

    /**
     * @test
     */
    public function descendants_of_expands_the_whole_branch(): void
    {
        Functions\expect('get_term_children')
            ->once()
            ->with(2, TsmlCommitteeFields::TAXONOMY)
            ->andReturn([5, 6]);

        Functions\expect('get_terms')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame([5, 6], $args['include']);
                return [
                    $this->term(5, 'pi-employment', 'Employment', 2),
                    $this->term(6, 'pi-health', 'Health', 2),
                ];
            });

        $this->assertCount(2, $this->repository->descendantsOf(2));
    }

    /**
     * The trap this guards: get_terms() treats an empty 'include' as no filter
     * at all, so passing one through would answer "this leaf has no children"
     * with the entire taxonomy.
     *
     * @test
     */
    public function a_leaf_has_no_descendants_and_no_second_query(): void
    {
        Functions\expect('get_term_children')->once()->andReturn([]);
        Functions\expect('get_terms')->never();

        $this->assertSame([], $this->repository->descendantsOf(5));
    }

    /**
     * @test
     */
    public function descendants_of_answers_empty_when_the_children_lookup_fails(): void
    {
        Functions\expect('get_term_children')->once()->andReturn(new \WP_Error());
        Functions\expect('get_terms')->never();

        $this->assertSame([], $this->repository->descendantsOf(5));
    }

    /**
     * @test
     */
    public function ancestors_of_keeps_nearest_first(): void
    {
        Functions\expect('get_ancestors')
            ->once()
            ->with(6, TsmlCommitteeFields::TAXONOMY, 'taxonomy')
            ->andReturn([2, 1]);

        Functions\expect('get_term')
            ->twice()
            ->andReturnUsing(fn (int $id) => $id === 2
                ? $this->term(2, 'public-information', 'Public Information', 1)
                : $this->term(1, 'intergroup', 'Intergroup'));

        $this->assertSame(
            ['public-information', 'intergroup'],
            array_map(
                static fn ($committee) => $committee->getSlug(),
                $this->repository->ancestorsOf(6)
            )
        );
    }

    /**
     * @test
     */
    public function a_root_has_no_ancestors(): void
    {
        Functions\expect('get_ancestors')->once()->andReturn([]);

        $this->assertSame([], $this->repository->ancestorsOf(1));
    }

    /**
     * @test
     */
    public function path_to_runs_root_first_and_ends_with_the_committee(): void
    {
        Functions\expect('get_ancestors')->once()->andReturn([2, 1]);

        Functions\expect('get_term')->andReturnUsing(fn (int $id) => match ($id) {
            1 => $this->term(1, 'intergroup', 'Intergroup'),
            2 => $this->term(2, 'public-information', 'Public Information', 1),
            6 => $this->term(6, 'pi-health', 'Health', 2),
            default => null,
        });

        $this->assertSame(
            ['intergroup', 'public-information', 'pi-health'],
            array_map(
                static fn ($committee) => $committee->getSlug(),
                $this->repository->pathTo(6)
            )
        );
    }

    /**
     * @test
     */
    public function path_to_an_unknown_committee_is_empty(): void
    {
        Functions\expect('get_term')->once()->andReturn(null);
        Functions\expect('get_ancestors')->never();

        $this->assertSame([], $this->repository->pathTo(404));
    }

    // ── Assignments ──────────────────────────────────────────────────

    /**
     * @test
     */
    public function for_member_returns_the_members_committees(): void
    {
        Functions\expect('get_post_type')
            ->once()
            ->with(31)
            ->andReturn(TsmlMemberFields::POST_TYPE);

        Functions\expect('wp_get_object_terms')
            ->once()
            ->with(31, TsmlCommitteeFields::TAXONOMY, ['orderby' => 'name', 'order' => 'ASC'])
            ->andReturn([$this->term(7, 'telephones', 'Telephones', 1)]);

        $committees = $this->repository->forMember(31);

        $this->assertCount(1, $committees);
        $this->assertSame('telephones', $committees[0]->getSlug());
    }

    /**
     * Members and positions carry the same taxonomy, so without the post-type
     * check a mixed-up ID would return a plausible but wrong answer.
     *
     * @test
     */
    public function for_member_refuses_an_id_that_is_not_a_member(): void
    {
        Functions\expect('get_post_type')
            ->once()
            ->andReturn(TsmlPositionFields::POST_TYPE);

        Functions\expect('wp_get_object_terms')->never();

        $this->assertSame([], $this->repository->forMember(31));
    }

    /**
     * @test
     */
    public function for_member_refuses_a_non_positive_id_without_querying(): void
    {
        Functions\expect('get_post_type')->never();
        Functions\expect('wp_get_object_terms')->never();

        $this->assertSame([], $this->repository->forMember(0));
    }

    /**
     * @test
     */
    public function for_position_asks_about_the_position_post_type(): void
    {
        Functions\expect('get_post_type')
            ->once()
            ->andReturn(TsmlPositionFields::POST_TYPE);

        Functions\expect('wp_get_object_terms')
            ->once()
            ->andReturn([$this->term(1, 'intergroup', 'Intergroup')]);

        $this->assertCount(1, $this->repository->forPosition(88));
    }

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

    /**
     * @test
     */
    public function member_ids_in_includes_sub_committees_by_default(): void
    {
        Functions\expect('get_posts')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame(TsmlMemberFields::POST_TYPE, $args['post_type']);
                $this->assertSame('ids', $args['fields']);
                $this->assertSame([
                    'taxonomy'         => TsmlCommitteeFields::TAXONOMY,
                    'field'            => 'term_id',
                    'terms'            => [2],
                    'include_children' => true,
                ], $args['tax_query'][0]);

                return [11, 12, 13];
            });

        $this->assertSame([11, 12, 13], $this->repository->memberIdsIn(2));
    }

    /**
     * @test
     */
    public function member_ids_in_can_be_limited_to_the_committee_itself(): void
    {
        Functions\expect('get_posts')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertFalse($args['tax_query'][0]['include_children']);
                return [11];
            });

        $this->assertSame([11], $this->repository->memberIdsIn(2, false));
    }

    /**
     * @test
     */
    public function member_ids_in_accepts_a_slug(): void
    {
        Functions\expect('get_term_by')
            ->once()
            ->andReturn($this->term(7, 'telephones', 'Telephones', 1));

        Functions\expect('get_posts')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame([7], $args['tax_query'][0]['terms']);
                return ['11', '12'];
            });

        // get_posts() with 'fields' => 'ids' can hand back numeric strings
        // depending on the query path, so the IDs are cast rather than trusted.
        $this->assertSame([11, 12], $this->repository->memberIdsIn('telephones'));
    }

    /**
     * @test
     */
    public function member_ids_in_an_unknown_committee_answers_empty_without_querying(): void
    {
        Functions\expect('get_term_by')->once()->andReturn(false);
        Functions\expect('get_posts')->never();

        $this->assertSame([], $this->repository->memberIdsIn('no-such-committee'));
    }

    /**
     * @test
     */
    public function position_ids_in_asks_for_the_position_post_type(): void
    {
        Functions\expect('get_posts')
            ->once()
            ->andReturnUsing(function (array $args) {
                $this->assertSame(TsmlPositionFields::POST_TYPE, $args['post_type']);
                return [88];
            });

        $this->assertSame([88], $this->repository->positionIdsIn(1));
    }
}
