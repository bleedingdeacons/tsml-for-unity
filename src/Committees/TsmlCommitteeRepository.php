<?php

declare(strict_types=1);

namespace TsmlForUnity\Committees;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Positions\TsmlPositionFields;
use Unity\Committees\Interfaces\Committee;
use Unity\Committees\Interfaces\CommitteeRepository;
use WP_Term;

use function get_ancestors;
use function get_post_type;
use function get_posts;
use function get_term_by;
use function get_term_children;
use function get_terms;
use function is_wp_error;
use function wp_get_object_terms;

/**
 * TSML Committee Repository
 *
 * Reads the committee hierarchy straight from the term APIs. It never touches
 * the ACF fields that write it: both are configured with Save Terms on, so the
 * rows in wp_term_relationships are the real record and ACF's parallel meta
 * copy is bookkeeping. Reading the meta instead would miss every assignment
 * made through Quick Edit, WP-CLI, an import or wp_set_object_terms().
 */
class TsmlCommitteeRepository implements CommitteeRepository
{
    private TsmlCommitteeFactory $factory;

    /**
     * TsmlCommitteeRepository constructor
     *
     * Typed to the concrete factory rather than Unity's CommitteeFactory
     * interface, unlike the sibling repositories. It needs createFromTerm(),
     * which takes a WP_Term and so cannot live on a Unity interface without
     * dragging WordPress into Unity's contracts. The alternative -- hydrating
     * through createFromSource() -- would re-fetch by ID every term get_terms()
     * has already returned in full.
     *
     * @param TsmlCommitteeFactory $factory The committee factory
     */
    public function __construct(TsmlCommitteeFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Committee
    {
        return $this->factory->createFromSource($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Committee
    {
        if ($slug === '') {
            return null;
        }

        $term = get_term_by('slug', $slug, TsmlCommitteeFields::TAXONOMY);

        if (!$term instanceof WP_Term) {
            return null;
        }

        return $this->factory->createFromTerm($term);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        return $this->queryTerms([]);
    }

    /**
     * {@inheritdoc}
     */
    public function roots(): array
    {
        return $this->queryTerms(['parent' => 0]);
    }

    /**
     * {@inheritdoc}
     */
    public function childrenOf(int|string $committee): array
    {
        $id = $this->resolveId($committee);

        if ($id === 0) {
            return [];
        }

        return $this->queryTerms(['parent' => $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function descendantsOf(int|string $committee): array
    {
        $id = $this->resolveId($committee);

        if ($id === 0) {
            return [];
        }

        $descendantIds = get_term_children($id, TsmlCommitteeFields::TAXONOMY);

        // An empty 'include' means "no filter" to get_terms, which would return
        // the entire taxonomy -- the exact opposite of "this leaf has no
        // children". Bail before it can.
        if (is_wp_error($descendantIds) || $descendantIds === []) {
            return [];
        }

        return $this->queryTerms(['include' => $descendantIds]);
    }

    /**
     * {@inheritdoc}
     */
    public function ancestorsOf(int|string $committee): array
    {
        $id = $this->resolveId($committee);

        if ($id === 0) {
            return [];
        }

        $ancestorIds = get_ancestors($id, TsmlCommitteeFields::TAXONOMY, 'taxonomy');

        // Hydrated one at a time rather than through queryTerms(), which sorts
        // by name. get_ancestors() returns nearest-parent-first and that order
        // is the answer, not an accident of it.
        $ancestors = [];
        foreach ($ancestorIds as $ancestorId) {
            $ancestor = $this->findById((int) $ancestorId);
            if ($ancestor !== null) {
                $ancestors[] = $ancestor;
            }
        }

        return $ancestors;
    }

    /**
     * {@inheritdoc}
     */
    public function pathTo(int|string $committee): array
    {
        $id = $this->resolveId($committee);

        if ($id === 0) {
            return [];
        }

        $self = $this->findById($id);

        if ($self === null) {
            return [];
        }

        $path = array_reverse($this->ancestorsOf($id));
        $path[] = $self;

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function forMember(int $memberId): array
    {
        return $this->committeesFor($memberId, TsmlMemberFields::POST_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function forPosition(int $positionId): array
    {
        return $this->committeesFor($positionId, TsmlPositionFields::POST_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function memberIdsIn(int|string $committee, bool $includeDescendants = true): array
    {
        return $this->objectIdsIn($committee, TsmlMemberFields::POST_TYPE, $includeDescendants);
    }

    /**
     * {@inheritdoc}
     */
    public function positionIdsIn(int|string $committee, bool $includeDescendants = true): array
    {
        return $this->objectIdsIn($committee, TsmlPositionFields::POST_TYPE, $includeDescendants);
    }

    /**
     * Resolve a committee reference to a term ID
     *
     * A string is always a slug, never a numeric ID in string clothing -- the
     * two are indistinguishable otherwise, and guessing would make the meaning
     * of an argument depend on the data in it.
     *
     * @param int|string $committee A term ID or a slug
     * @return int The term ID, or 0 when it cannot be resolved
     */
    private function resolveId(int|string $committee): int
    {
        if (is_int($committee)) {
            return $committee > 0 ? $committee : 0;
        }

        return $this->findBySlug($committee)?->getId() ?? 0;
    }

    /**
     * Fetch and hydrate committees
     *
     * hide_empty is false throughout: a committee nobody has been assigned to
     * yet is still part of the structure, and dropping it would make a newly
     * created branch invisible until somebody joined it.
     *
     * @param array<string, mixed> $args Overrides merged over the defaults
     * @return array<int, Committee> Ordered by name
     */
    private function queryTerms(array $args): array
    {
        $terms = get_terms(array_merge([
            'taxonomy'   => TsmlCommitteeFields::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ], $args));

        if (!is_array($terms)) {
            return [];
        }

        return $this->hydrate($terms);
    }

    /**
     * The committees assigned to one post, if it is of the expected type
     *
     * The post-type check keeps forMember() from quietly answering for a
     * position and vice versa: both are posts carrying the same taxonomy, so
     * without it a mixed-up ID returns a plausible, wrong answer.
     *
     * @param int    $postId   The post ID
     * @param string $postType The post type it must be
     * @return array<int, Committee> Ordered by name
     */
    private function committeesFor(int $postId, string $postType): array
    {
        if ($postId <= 0 || get_post_type($postId) !== $postType) {
            return [];
        }

        $terms = wp_get_object_terms($postId, TsmlCommitteeFields::TAXONOMY, [
            'orderby' => 'name',
            'order'   => 'ASC',
        ]);

        if (!is_array($terms)) {
            return [];
        }

        return $this->hydrate($terms);
    }

    /**
     * Turn whatever a term query returned into Committee objects
     *
     * Non-WP_Term entries are skipped: get_terms() and wp_get_object_terms()
     * both return ints or strings when a caller sets 'fields', which nothing
     * here does -- but their signatures allow it, and createFromTerm() needs a
     * WP_Term.
     *
     * @param array<int, mixed> $terms
     * @return array<int, Committee>
     */
    private function hydrate(array $terms): array
    {
        $committees = [];

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $committee = $this->factory->createFromTerm($term);
            if ($committee !== null) {
                $committees[] = $committee;
            }
        }

        return $committees;
    }

    /**
     * The IDs of posts of one type assigned to a committee
     *
     * Descendants are handled by tax_query's own include_children rather than
     * by expanding the tree here: WordPress resolves it against the term
     * hierarchy in the same query, so the whole branch costs no more than the
     * single committee.
     *
     * Ordered by ID for determinism only. Callers wanting a meaningful order
     * should impose one after hydrating the posts -- members have no useful
     * post_title to sort on, their names living in ACF fields.
     *
     * @param int|string $committee          A term ID or slug
     * @param string     $postType           The post type to return
     * @param bool       $includeDescendants Whether sub-committees count
     * @return array<int, int> Post IDs
     */
    private function objectIdsIn(int|string $committee, string $postType, bool $includeDescendants): array
    {
        $id = $this->resolveId($committee);

        if ($id === 0) {
            return [];
        }

        $posts = get_posts([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy'         => TsmlCommitteeFields::TAXONOMY,
                    'field'            => 'term_id',
                    'terms'            => [$id],
                    'include_children' => $includeDescendants,
                ],
            ],
        ]);

        if (!is_array($posts)) {
            return [];
        }

        return array_values(array_map('intval', $posts));
    }
}
