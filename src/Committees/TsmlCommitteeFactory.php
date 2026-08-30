<?php

declare(strict_types=1);

namespace TsmlForUnity\Committees;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Committees\Interfaces\Committee;
use Unity\Committees\Interfaces\CommitteeFactory;
use WP_Term;

use function get_term;

/**
 * Concrete Committee Factory class
 */
class TsmlCommitteeFactory implements CommitteeFactory
{
    /**
     * {@inheritdoc}
     */
    public function createFromSource(int $sourceId): ?Committee
    {
        if ($sourceId <= 0) {
            return null;
        }

        $term = get_term($sourceId, TsmlCommitteeFields::TAXONOMY);

        if (!$term instanceof WP_Term) {
            return null;
        }

        return $this->createFromTerm($term);
    }

    /**
     * Create a committee from a term this caller already holds
     *
     * Beyond the CommitteeFactory contract, and load-bearing for
     * TsmlCommitteeRepository: get_terms() hands back whole WP_Term objects, so
     * routing them through createFromSource() would re-fetch every one by ID.
     * Listing the tree would become one query per committee.
     *
     * @param WP_Term $term A term to hydrate
     * @return Committee|null The committee, or null if the term belongs to
     *                        another taxonomy
     */
    public function createFromTerm(WP_Term $term): ?Committee
    {
        if ($term->taxonomy !== TsmlCommitteeFields::TAXONOMY) {
            return null;
        }

        return new TsmlCommittee(
            (int) $term->term_id,
            (string) $term->slug,
            html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            (string) $term->description,
            (int) $term->parent
        );
    }
}
