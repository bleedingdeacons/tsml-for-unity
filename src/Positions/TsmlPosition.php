<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Positions\Interfaces\Position;

/**
 * Concrete Position class
 */
class TsmlPosition implements Position
{
    private int $id;
    private int $minimumSobriety;
    private int $termYears;
    private string $email;
    private string $longName;
    private string $shortDescription;
    private string $summary;
    private string $link;
    private string $updated;

    /**
     * Position constructor
     * 
     * @param int    $id                Post ID
     * @param int    $minimumSobriety   Minimum sobriety requirement in months
     * @param int    $termYears         Term length in years
     * @param string $email             Email address
     * @param string $longName          Long name/title
     * @param string $shortDescription  Short description
     * @param string $summary           Summary
     * @param string $link              Link URL
     * @param string $updated           Last updated datetime string
     */
    public function __construct(
        int $id = 0,
        int $minimumSobriety = 6,
        int $termYears = 1,
        string $email = '',
        string $longName = '',
        string $shortDescription = '',
        string $summary = '',
        string $link = '',
        string $updated = ''
    ) {
        $this->id = $id;
        $this->minimumSobriety = $minimumSobriety;
        $this->termYears = $termYears;
        $this->email = $email;
        $this->longName = $longName;
        $this->shortDescription = $shortDescription;
        $this->summary = $summary;
        $this->link = $link;
        $this->updated = $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinimumSobriety(): int
    {
        return $this->minimumSobriety;
    }

    /**
     * {@inheritdoc}
     */
    public function getTermYears(): int
    {
        return $this->termYears;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getLongName(): string
    {
        return $this->longName;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return $this->id > 0
            && !empty($this->email)
            && !empty($this->longName)
            && !empty($this->shortDescription)
            && !empty($this->summary)
            && $this->minimumSobriety >= 6
            && $this->termYears >= 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdated(): string
    {
        return $this->updated;
    }
}
