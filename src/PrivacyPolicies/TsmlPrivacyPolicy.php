<?php

declare(strict_types=1);

namespace TsmlForUnity\PrivacyPolicies;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\PrivacyPolicies\Interfaces\PrivacyPolicy;

/**
 * TSML Privacy Policy
 *
 * Concrete PrivacyPolicy implementation. Pure value object — no I/O,
 * no WordPress calls — so it's trivial to construct in tests and the
 * repository owns the persistence boundary.
 */
class TsmlPrivacyPolicy implements PrivacyPolicy
{
    private int $id;
    private string $title;
    private string $policy;
    private string $version;
    private bool $active;
    private string $updated;

    /**
     * Constructor
     *
     * @param int    $id      Post ID (0 for an unsaved policy)
     * @param string $title   Policy title (post_title)
     * @param string $policy  Policy body (HTML from the WYSIWYG field)
     * @param string $version Free-form version identifier
     * @param bool   $active  Whether this policy is currently active
     * @param string $updated Last updated datetime string (Y-m-d H:i:s, UTC)
     */
    public function __construct(
        int $id,
        string $title = '',
        string $policy = '',
        string $version = '',
        bool $active = false,
        string $updated = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->policy = $policy;
        $this->version = $version;
        $this->active = $active;
        $this->updated = $updated;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPolicy(): string
    {
        return $this->policy;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getUpdated(): string
    {
        return $this->updated;
    }
}
