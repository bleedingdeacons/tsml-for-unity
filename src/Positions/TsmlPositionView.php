<?php

declare(strict_types=1);

namespace TsmlForUnity\Positions;

use Unity\Members\Interfaces\MemberInterface;
use Unity\Positions\Interfaces\PositionInterface;
use Unity\Positions\Interfaces\PositionViewInterface;
use DateTime;
use Exception;

/**
 * Position View Class
 * 
 * Combines position and member data
 */
class TsmlPositionView implements PositionViewInterface
{
    private PositionInterface $position;
    private ?MemberInterface $member;
    private ?DateTime $rotationDate;
    private ?string $privateEmail;
    private ?string $privateContact;
    private ?string $title;

    /**
     * Constructor
     * 
     * @param PositionInterface $position The position
     * @param MemberInterface|null $member The member assigned to the position (if any)
     */
    public function __construct(
        PositionInterface $position,
        ?MemberInterface $member = null,
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
                    $this->rotationDate = DateTime::createFromFormat('d/m/Y', $rotationStr);
                    if ($this->rotationDate) {
                        $this->rotationDate->setTime(0, 0);
                    }
                }
            } catch (Exception $ex) {
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
    public function getPrivateDisplayName(): string
    {
        return $this->member ? $this->member->getPrivateName() : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition(): PositionInterface
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function getMember(): ?MemberInterface
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
