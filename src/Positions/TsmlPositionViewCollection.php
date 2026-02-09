<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

use Unity\Positions\Interfaces\PositionViewInterface;

/**
 * PositionViewCollection
 * 
 * Handles a collection of position views with filtering and sorting capabilities
 */
class TsmlPositionViewCollection
{
    private array $views;

    /**
     * Constructor
     * 
     * @param array $views Array of PositionViewInterface objects
     */
    public function __construct(array $views = [])
    {
        $this->views = $views;
    }

    /**
     * Get views that have members assigned
     * 
     * @return TsmlPositionViewCollection
     */
    public function getFilledPositions(): TsmlPositionViewCollection
    {
        return $this->filter(function (PositionViewInterface $view) {
            return !$view->isVacant();
        });
    }

    /**
     * Get views that don't have members assigned
     * 
     * @return TsmlPositionViewCollection
     */
    public function getVacantPositions(): TsmlPositionViewCollection
    {
        return $this->filter(function (PositionViewInterface $view) {
            return $view->isVacant();
        });
    }

    /**
     * Get positions that will rotate soon
     * 
     * @param int $days Number of days threshold
     * @return TsmlPositionViewCollection
     */
    public function getPositionsRotatingSoon(int $days = 30): TsmlPositionViewCollection
    {
        return $this->filter(function (PositionViewInterface $view) use ($days) {
            $daysUntil = $view->getDaysUntilRotation();
            return $daysUntil !== null && $daysUntil <= $days && $daysUntil > 0;
        });
    }

    /**
     * Get positions that have already passed their rotation date
     * 
     * @return TsmlPositionViewCollection
     */
    public function getOverduePositions(): TsmlPositionViewCollection
    {
        return $this->filter(function (PositionViewInterface $view) {
            $daysUntil = $view->getDaysUntilRotation();
            return $daysUntil !== null && $daysUntil === 0;
        });
    }

    /**
     * Sort positions by days until rotation
     * 
     * @param bool $ascending Sort order
     * @return TsmlPositionViewCollection
     */
    public function sortByDaysUntilRotation(bool $ascending = true): TsmlPositionViewCollection
    {
        $views = $this->views;

        usort($views, function (PositionViewInterface $a, PositionViewInterface $b) use ($ascending) {
            $daysA = $a->getDaysUntilRotation();
            $daysB = $b->getDaysUntilRotation();

            if ($daysA === null && $daysB === null) {
                return 0;
            }

            if ($daysA === null) {
                return $ascending ? 1 : -1;
            }

            if ($daysB === null) {
                return $ascending ? -1 : 1;
            }

            return $ascending ? ($daysA - $daysB) : ($daysB - $daysA);
        });

        return new self($views);
    }

    /**
     * Sort positions by name
     * 
     * @param bool $ascending Sort order
     * @return TsmlPositionViewCollection
     */
    public function sortByName(bool $ascending = true): TsmlPositionViewCollection
    {
        $views = $this->views;

        usort($views, function (PositionViewInterface $a, PositionViewInterface $b) use ($ascending) {
            $nameA = $a->getPosition()->getLongName();
            $nameB = $b->getPosition()->getLongName();

            $result = strcmp($nameA, $nameB);
            return $ascending ? $result : -$result;
        });

        return new self($views);
    }

    /**
     * Sort positions by title
     * 
     * @param bool $ascending Sort order
     * @return TsmlPositionViewCollection
     */
    public function sortByTitle(bool $ascending = true): TsmlPositionViewCollection
    {
        $views = $this->views;

        usort($views, function (PositionViewInterface $a, PositionViewInterface $b) use ($ascending) {
            $titleA = $a->getTitle() ?? '';
            $titleB = $b->getTitle() ?? '';

            $result = strcmp($titleA, $titleB);
            return $ascending ? $result : -$result;
        });

        return new self($views);
    }

    /**
     * Sort positions by email
     * 
     * @param bool $ascending Sort order
     * @return TsmlPositionViewCollection
     */
    public function sortByEmail(bool $ascending = true): TsmlPositionViewCollection
    {
        $views = $this->views;

        usort($views, function (PositionViewInterface $a, PositionViewInterface $b) use ($ascending) {
            $emailA = $a->getPositionEmail() ?? '';
            $emailB = $b->getPositionEmail() ?? '';

            $result = strcmp($emailA, $emailB);
            return $ascending ? $result : -$result;
        });

        return new self($views);
    }

    /**
     * Apply a filter to the collection
     * 
     * @param callable $callback Filter callback function
     * @return TsmlPositionViewCollection
     */
    public function filter(callable $callback): TsmlPositionViewCollection
    {
        return new self(array_filter($this->views, $callback));
    }

    /**
     * Get all position views in the collection
     * 
     * @return array Array of PositionViewInterface objects
     */
    public function getAll(): array
    {
        return $this->views;
    }

    /**
     * Get number of views in the collection
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->views);
    }
}
