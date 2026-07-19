<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\MemberView;
use Unity\Members\ResponderCertification;

/**
 * Concrete TSML Member View class
 */
class TsmlMemberView implements MemberView
{
    private int $id;
    private string $anonymousName;
    private string $personalEmail;
    private string $mobileNumber;
    private int $homeGroupId;
    private string $homeGroupName;
    private bool $isGSR;
    private int $positionId;
    private string $positionName;
    private string $rotationDate;
    private bool $twelfthStepper;
    private bool $telephoneResponder;
    private ResponderCertification $responderCertification;
    private string $area;
    /** @var array<int, string> */
    private array $accepts;

    /**
     * TsmlMemberView constructor
     *
     * @param int                $id              Post ID
     * @param string             $anonymousName   Anonymous name
     * @param string             $personalEmail   Personal email address
     * @param string             $mobileNumber    Mobile phone number
     * @param int                $homeGroupId     Home group post ID (0 if none)
     * @param string             $homeGroupName   Home group name (empty if none)
     * @param bool               $isGSR           GSR flag
     * @param int                $positionId      Position post ID (0 if none)
     * @param string             $positionName    Position name (empty if none)
     * @param string             $rotationDate    Rotation date Y-m-d (empty if none)
     * @param bool               $twelfthStepper  12th-step availability flag
     * @param bool               $telephoneResponder Telephone responder availability flag
     * @param ResponderCertification $responderCertification Certification stage
     * @param string             $area            Geographic area
     * @param array<int, string> $accepts         Forms of contact accepted
     */
    public function __construct(
        int $id = 0,
        string $anonymousName = '',
        string $personalEmail = '',
        string $mobileNumber = '',
        int $homeGroupId = 0,
        string $homeGroupName = '',
        bool $isGSR = false,
        int $positionId = 0,
        string $positionName = '',
        string $rotationDate = '',
        bool $twelfthStepper = false,
        bool $telephoneResponder = false,
        ResponderCertification $responderCertification = ResponderCertification::None,
        string $area = '',
        array $accepts = []
    ) {
        $this->id = $id;
        $this->anonymousName = $anonymousName;
        $this->personalEmail = $personalEmail;
        $this->mobileNumber = $mobileNumber;
        $this->homeGroupId = $homeGroupId;
        $this->homeGroupName = $homeGroupName;
        $this->isGSR = $isGSR;
        $this->positionId = $positionId;
        $this->positionName = $positionName;
        $this->rotationDate = $rotationDate;
        $this->twelfthStepper = $twelfthStepper;
        $this->telephoneResponder = $telephoneResponder;
        $this->responderCertification = $responderCertification;
        $this->area = $area;
        $this->accepts = $accepts;
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
    public function getAnonymousName(): string
    {
        return $this->anonymousName;
    }

    /**
     * {@inheritdoc}
     */
    public function getPersonalEmail(): string
    {
        return $this->personalEmail;
    }

    /**
     * {@inheritdoc}
     */
    public function getMobileNumber(): string
    {
        return $this->mobileNumber;
    }

    /**
     * {@inheritdoc}
     */
    public function getHomeGroupId(): int
    {
        return $this->homeGroupId;
    }

    /**
     * {@inheritdoc}
     */
    public function getHomeGroupName(): string
    {
        return $this->homeGroupName;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHomeGroup(): bool
    {
        return $this->homeGroupId > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isGSR(): bool
    {
        return $this->isGSR;
    }

    /**
     * {@inheritdoc}
     */
    public function getPositionId(): int
    {
        return $this->positionId;
    }

    /**
     * {@inheritdoc}
     */
    public function getPositionName(): string
    {
        return $this->positionName;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPosition(): bool
    {
        return $this->positionId > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getRotationDate(): string
    {
        return $this->rotationDate;
    }

    /**
     * {@inheritdoc}
     */
    public function isTwelfthStepper(): bool
    {
        return $this->twelfthStepper;
    }

    /**
     * {@inheritdoc}
     */
    public function isTelephoneResponder(): bool
    {
        return $this->telephoneResponder;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponderCertification(): ResponderCertification
    {
        return $this->responderCertification;
    }

    /**
     * {@inheritdoc}
     */
    public function getArea(): string
    {
        return $this->area;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<int, string>
     */
    public function getAccepts(): array
    {
        return $this->accepts;
    }
}
