<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\Member;

/**
 * Member Class
 */
class TsmlMember implements Member
{
    private int $id;
    private string $anonymousName;
    private bool $showAnonymousName;
    private bool $showMemberProfile;
    private string $anonymousProfile;
    private int $intergroupPosition;
    private string $intergroupPositionRotation;
    private int $homeGroup;
    private bool $isGSR;
    private mixed $meetingPO;
    private string $personalEmail;
    private string $mobileNumber;
    private bool $twelfthStepper;
    private bool $telephoneResponder;
    private string $area;
    /** @var array<int, string> */
    private array $accepts;
    private bool $gdprAccepted;
    private string $gdprAcceptedAt;
    private string $gdprAcceptanceVersion;
    private string $gdprAcceptanceMethod;
    private string $gdprAcceptanceStatement;
    private string $updated;

    /**
     * Member constructor
     *
     * @param int $id Post ID
     * @param string $anonymousName Anonymous name
     * @param bool $showAnonymousName Show anonymous name flag
     * @param bool $showMemberProfile Show member profile flag
     * @param string $anonymousProfile Anonymous profile text
     * @param int $intergroupPosition Intergroup position ID
     * @param string $intergroupPositionRotation Intergroup position rotation info
     * @param int $homeGroup Home group ID
     * @param bool $isGSR GSR flag
     * @param mixed $meetingPO Meeting PO reference
     * @param string $personalEmail Personal email address
     * @param string $mobileNumber Mobile phone number
     * @param bool $twelfthStepper Whether the member is available for 12th-step calls
     * @param bool $telephoneResponder Whether the member is available as a telephone responder
     * @param string $area Geographic area covered for 12th-step calls
     * @param array<int, string> $accepts Forms of contact accepted for 12th-step calls
     * @param bool $gdprAccepted GDPR acceptance flag
     * @param string $gdprAcceptedAt GDPR acceptance timestamp (Y-m-d H:i:s)
     * @param string $gdprAcceptanceVersion Privacy policy version accepted
     * @param string $gdprAcceptanceMethod How acceptance was captured
     * @param string $gdprAcceptanceStatement The exact statement that was accepted
     * @param string $updated Last updated datetime string
     */
    public function __construct(
        int $id,
        string $anonymousName = '',
        bool $showAnonymousName = false,
        bool $showMemberProfile = false,
        string $anonymousProfile = '',
        int $intergroupPosition = 0,
        string $intergroupPositionRotation = '',
        int $homeGroup = 0,
        bool $isGSR = false,
        mixed $meetingPO = null, // Need to removed
        string $personalEmail = '',
        string $mobileNumber = '',
        bool $twelfthStepper = false,
        bool $telephoneResponder = false,
        string $area = '',
        array $accepts = [],
        bool $gdprAccepted = false,
        string $gdprAcceptedAt = '',
        string $gdprAcceptanceVersion = '',
        string $gdprAcceptanceMethod = '',
        string $gdprAcceptanceStatement = '',
        string $updated = ''
    ) {
        $this->id = $id;
        $this->anonymousName = $anonymousName;
        $this->showAnonymousName = $showAnonymousName;
        $this->showMemberProfile = $showMemberProfile;
        $this->anonymousProfile = $anonymousProfile;
        $this->intergroupPosition = $intergroupPosition;
        $this->intergroupPositionRotation = $intergroupPositionRotation;
        $this->homeGroup = $homeGroup;
        $this->isGSR = $isGSR;
        $this->meetingPO = $meetingPO;
        $this->personalEmail = $personalEmail;
        $this->mobileNumber = $mobileNumber;
        $this->twelfthStepper = $twelfthStepper;
        $this->telephoneResponder = $telephoneResponder;
        $this->area = $area;
        $this->accepts = $accepts;
        $this->gdprAccepted = $gdprAccepted;
        $this->gdprAcceptedAt = $gdprAcceptedAt;
        $this->gdprAcceptanceVersion = $gdprAcceptanceVersion;
        $this->gdprAcceptanceMethod = $gdprAcceptanceMethod;
        $this->gdprAcceptanceStatement = $gdprAcceptanceStatement;
        $this->updated = $updated;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAnonymousName(): string
    {
        return $this->anonymousName;
    }

    public function showAnonymousName(): bool
    {
        return $this->showAnonymousName;
    }

    public function showMemberProfile(): bool
    {
        return $this->showMemberProfile;
    }

    public function getAnonymousProfile(): string
    {
        return $this->anonymousProfile;
    }

    public function getIntergroupPosition(): int
    {
        return $this->intergroupPosition;
    }

    public function getIntergroupPositionRotation(): string
    {
        return $this->intergroupPositionRotation;
    }

    public function getHomeGroup(): int
    {
        return $this->homeGroup;
    }

    public function isGSR(): bool
    {
        return $this->isGSR;
    }

    public function getMeetingPO(): mixed
    {
        return $this->meetingPO;
    }

    public function getPersonalEmail(): string
    {
        return $this->personalEmail;
    }

    public function getMobileNumber(): string
    {
        return $this->mobileNumber;
    }

    public function isTwelfthStepper(): bool
    {
        return $this->twelfthStepper;
    }

    public function isTelephoneResponder(): bool
    {
        return $this->telephoneResponder;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    /**
     * @return array<int, string>
     */
    public function getAccepts(): array
    {
        return $this->accepts;
    }

    public function isGdprAccepted(): bool
    {
        return $this->gdprAccepted;
    }

    public function getGdprAcceptedAt(): string
    {
        return $this->gdprAcceptedAt;
    }

    public function getGdprAcceptanceVersion(): string
    {
        return $this->gdprAcceptanceVersion;
    }

    public function getGdprAcceptanceMethod(): string
    {
        return $this->gdprAcceptanceMethod;
    }

    public function getGdprAcceptanceStatement(): string
    {
        return $this->gdprAcceptanceStatement;
    }

    public function getUpdated(): string
    {
        return $this->updated;
    }
}