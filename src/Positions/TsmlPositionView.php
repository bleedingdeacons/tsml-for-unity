<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\Member;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionView;
use DateTime;
use Exception;

/**
 * Position View Class
 *
 * Combines position and member data
 */
class TsmlPositionView implements PositionView
{
    private Position $position;
    private ?Member $member;
    private ?DateTime $rotationDate;
    private ?string $privateEmail;
    private ?string $privateContact;
    private ?string $title;

    /**
     * Constructor
     *
     * @param Position $position The position
     * @param Member|null $member The member assigned to the position (if any)
     */
    public function __construct(
        Position $position,
        ?Member $member = null,
    ) {
        $this->position = $position;
        $this->member = $member;
        $this->rotationDate = null;
        $this->title = $position->getShortDescription();
        $this->privateEmail = null;
        $this->privateContact = null;

        if ($this->member !== null) {
            try {
                $this->privateEmail = $this->member->getPersonalEmail();
                $this->privateContact = $this->member->getMobileNumber();
                $rotationStr = $this->member->getIntergroupPositionRotation();
                if (!empty($rotationStr)) {
                    $parsed = DateTime::createFromFormat('Y-m-d', $rotationStr)
                        ?: DateTime::createFromFormat('d/m/Y', $rotationStr);
                    if ($parsed !== false) {
                        $parsed->setTime(0, 0);
                        $this->rotationDate = $parsed;
                    }
                }
            } catch (Exception $ex) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Error in creating position_view: ' . $ex->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrivateEmail(): ?string
    {
        return $this->privateEmail;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrivateContact(): ?string
    {
        return $this->privateContact;
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition(): Position
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getMember(): ?Member
    {
        return $this->member;
    }

    /**
     * {@inheritdoc}
     */
    public function isVacant(): bool
    {
        return $this->member === null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMonthsUntilRotation(): ?int
    {
        if ($this->rotationDate === null) {
            return null;
        }

        try {
            $today = new DateTime('today');
            $interval = $today->diff($this->rotationDate);
            $years = (int) $interval->format('%y');
            $months = (int) $interval->format('%m');
            $value = ($years * 12) + $months;
            if ($interval->invert === 1) {
                $value = -$value;
            }
            return $value;
        } catch (Exception $ex) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Error in getMonthsUntilRotation: ' . $ex->getMessage());
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDaysUntilRotation(): ?int
    {
        if ($this->rotationDate === null) {
            return null;
        }

        $today = new DateTime('today');
        $interval = $today->diff($this->rotationDate);

        if ($interval->invert === 1) {
            return 0;
        }

        return (int) $interval->days;
    }

    /**
     * {@inheritdoc}
     */
    public function getRotationDate(): ?DateTime
    {
        return $this->rotationDate;
    }

    /**
     * {@inheritdoc}
     */
    public function getPositionEmail(): ?string
    {
        return $this->position->getEmail();
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicDisplayName(): ?string
    {
        if ($this->isVacant()) {
            return '';
        }

        if ($this->member->showAnonymousName()) {
            return $this->member->getAnonymousName();
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): ?string
    {
        return $this->position->getShortDescription();
    }
}