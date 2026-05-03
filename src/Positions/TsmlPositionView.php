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
    /** @var array<Member> */
    private array $members;
    private ?DateTime $rotationDate;
    private ?string $personalEmail;
    private ?string $mobileNumber;
    private ?string $title;

    /**
     * Constructor
     *
     * @param Position      $position The position
     * @param Member|null   $member   The primary member assigned to the position (if any)
     * @param array<Member> $members  All members sharing the latest rotation date (defaults to [$member] when omitted)
     */
    public function __construct(
        Position $position,
        ?Member $member = null,
        array $members = [],
    ) {
        $this->position = $position;
        $this->member = $member;
        $this->members = !empty($members) ? $members : ($member !== null ? [$member] : []);
        $this->rotationDate = null;
        $this->title = $position->getShortDescription();
        $this->personalEmail = null;
        $this->mobileNumber = null;

        if ($this->member !== null) {
            try {
                $this->personalEmail = $this->member->getPersonalEmail();
                $this->mobileNumber = $this->member->getMobileNumber();
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
                \TsmlForUnity\Plugin::logError('Error in creating position_view: ' . $ex->getMessage(), ['exception' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
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
    public function getPersonalEmail(): ?string
    {
        return $this->personalEmail;
    }

    /**
     * {@inheritdoc}
     */
    public function getMobileNumber(): ?string
    {
        return $this->mobileNumber;
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
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * {@inheritdoc}
     */
    public function getOfficerDisplayName(): string
    {
        if (empty($this->members)) {
            return '';
        }

        $names = array_map(function (Member $m): string {
            return $m->getAnonymousName();
        }, $this->members);

        return implode(', ', $names);
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
            \TsmlForUnity\Plugin::logError('Error in getMonthsUntilRotation: ' . $ex->getMessage(), ['exception' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
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

    /**
     * Check if a position is the Archivist role (permanent tenure, no rotation)
     *
     * @return bool
     */
    function isArchivist(): bool
    {
        $description = $this->getDescription() ?? '';
        return strcasecmp(trim($description), 'Archivist') === 0;
    }
}