<?php

declare(strict_types=1);

namespace TsmlForUnity\Committees;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Committees\Interfaces\Committee;

/**
 * Concrete Committee class
 *
 * Immutable: properties are `readonly` rather than merely private, so a
 * maintainer who adds a setter by mistake gets a runtime error instead of a
 * silent state mutation. There is no `with()` counterpart here as there is on
 * TsmlMember -- committees are read-only all the way down, because the tree is
 * maintained in wp-admin and nothing in this plugin writes a term.
 */
class TsmlCommittee implements Committee
{
    /**
     * Committee constructor
     *
     * @param int    $id          Term ID
     * @param string $slug        Term slug -- the identifier stable across sites
     * @param string $name        Display name
     * @param string $description Description, empty when unset
     * @param int    $parentId    Parent term ID, 0 for a root committee
     */
    public function __construct(
        private readonly int $id = 0,
        private readonly string $slug = '',
        private readonly string $name = '',
        private readonly string $description = '',
        private readonly int $parentId = 0
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function isRoot(): bool
    {
        return $this->parentId === 0;
    }
}
