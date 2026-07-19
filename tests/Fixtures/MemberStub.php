<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Fixtures;

use Unity\Members\Interfaces\Member;
use Unity\Members\ResponderCertification;

/**
 * An inert Member value object for tests.
 *
 * Unity ships no concrete Member -- only the interface -- so tests that need a
 * plain, predictable Member (rather than a mock with expectations) build one
 * here. Implementing the real interface means a change to Unity's contract
 * surfaces as an unimplemented-method error rather than silent drift.
 */
class MemberStub implements Member
{
    public function __construct(
        private int $id,
        private string $anonymousName = '',
        private bool $showAnonymousName = false,
        private bool $showMemberProfile = false,
        private string $anonymousProfile = '',
        private int $intergroupPosition = 0,
        private string $intergroupPositionRotation = '',
        private int $homeGroup = 0,
        private bool $isGSR = false,
        private mixed $meetingPO = null,
        private string $personalEmail = '',
        private string $mobileNumber = '',
        private bool $twelfthStepper = false,
        private bool $telephoneResponder = false,
        private ResponderCertification $responderCertification = ResponderCertification::None,
        private string $area = '',
        private array $accepts = [],
        private bool $gdprAccepted = false,
        private string $gdprAcceptedAt = '',
        private string $gdprAcceptanceVersion = '',
        private string $gdprAcceptanceMethod = '',
        private string $gdprAcceptanceStatement = '',
        private string $updated = ''
    ) {
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

    public function getResponderCertification(): ResponderCertification
    {
        return $this->responderCertification;
    }

    public function getArea(): string
    {
        return $this->area;
    }

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
