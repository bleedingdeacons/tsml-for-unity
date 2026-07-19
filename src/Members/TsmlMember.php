<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use Unity\Members\Interfaces\Member;
use Unity\Members\ResponderCertification;

/**
 * Member Class
 *
 * Immutable: properties are `readonly` rather than merely private, so a
 * maintainer who adds a setter by mistake gets a runtime error instead of a
 * silent state mutation. Build a modified copy with {@see with()}.
 *
 * Constructor parameters mirror {@see \Unity\Members\Interfaces\MemberFactory::createNew()}
 * exactly — same names, order and defaults. The names are part of the
 * contract: every construction site passes named arguments, because the
 * signature is long enough that a parameter inserted mid-list silently
 * rebinds every positional argument after it. That has happened, and it
 * shipped two unconditional 500s and a GDPR consent eraser.
 */
class TsmlMember implements Member
{
    /**
     * @param int                $id                       Post ID
     * @param string             $anonymousName            Anonymous name
     * @param bool               $showAnonymousName        Show anonymous name flag
     * @param bool               $showMemberProfile        Show member profile flag
     * @param string             $anonymousProfile         Anonymous profile text
     * @param int                $intergroupPosition       Intergroup position ID
     * @param string             $intergroupPositionRotation Rotation date (Y-m-d)
     * @param int                $homeGroup                Home group ID
     * @param bool               $isGSR                    GSR flag
     * @param mixed              $meetingPO                Meeting PO reference
     * @param string             $personalEmail            Personal email address
     * @param string             $mobileNumber             Mobile phone number
     * @param bool               $twelfthStepper           Available for 12th-step calls
     * @param bool               $telephoneResponder       Available as a telephone responder
     * @param ResponderCertification $responderCertification Certification stage; None unless a responder
     * @param string             $area                     Geographic area covered
     * @param array<int, string> $accepts                  Forms of contact accepted
     * @param bool               $gdprAccepted             GDPR acceptance flag
     * @param string             $gdprAcceptedAt           Acceptance timestamp (Y-m-d H:i:s)
     * @param string             $gdprAcceptanceVersion    Policy version accepted
     * @param string             $gdprAcceptanceMethod     How acceptance was captured
     * @param string             $gdprAcceptanceStatement  The exact statement accepted
     * @param string             $updated                  Last updated datetime
     */
    public function __construct(
        private readonly int $id,
        private readonly string $anonymousName = '',
        private readonly bool $showAnonymousName = false,
        private readonly bool $showMemberProfile = false,
        private readonly string $anonymousProfile = '',
        private readonly int $intergroupPosition = 0,
        private readonly string $intergroupPositionRotation = '',
        private readonly int $homeGroup = 0,
        private readonly bool $isGSR = false,
        private readonly mixed $meetingPO = null, // Need to removed
        private readonly string $personalEmail = '',
        private readonly string $mobileNumber = '',
        private readonly bool $twelfthStepper = false,
        private readonly bool $telephoneResponder = false,
        private readonly ResponderCertification $responderCertification = ResponderCertification::None,
        private readonly string $area = '',
        private readonly array $accepts = [],
        private readonly bool $gdprAccepted = false,
        private readonly string $gdprAcceptedAt = '',
        private readonly string $gdprAcceptanceVersion = '',
        private readonly string $gdprAcceptanceMethod = '',
        private readonly string $gdprAcceptanceStatement = '',
        private readonly string $updated = ''
    ) {
    }

    /**
     * Every field, keyed by constructor parameter name.
     *
     * The keys must stay identical to the constructor's parameter names:
     * {@see with()} spreads this array as named arguments.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                         => $this->id,
            'anonymousName'              => $this->anonymousName,
            'showAnonymousName'          => $this->showAnonymousName,
            'showMemberProfile'          => $this->showMemberProfile,
            'anonymousProfile'           => $this->anonymousProfile,
            'intergroupPosition'         => $this->intergroupPosition,
            'intergroupPositionRotation' => $this->intergroupPositionRotation,
            'homeGroup'                  => $this->homeGroup,
            'isGSR'                      => $this->isGSR,
            'meetingPO'                  => $this->meetingPO,
            'personalEmail'              => $this->personalEmail,
            'mobileNumber'               => $this->mobileNumber,
            'twelfthStepper'             => $this->twelfthStepper,
            'telephoneResponder'         => $this->telephoneResponder,
            'responderCertification'     => $this->responderCertification,
            'area'                       => $this->area,
            'accepts'                    => $this->accepts,
            'gdprAccepted'               => $this->gdprAccepted,
            'gdprAcceptedAt'             => $this->gdprAcceptedAt,
            'gdprAcceptanceVersion'      => $this->gdprAcceptanceVersion,
            'gdprAcceptanceMethod'       => $this->gdprAcceptanceMethod,
            'gdprAcceptanceStatement'    => $this->gdprAcceptanceStatement,
            'updated'                    => $this->updated,
        ];
    }

    /**
     * A copy of this member with the named fields replaced.
     *
     * Fields you do not name are carried over unchanged — the inverse of
     * MemberFactory::createNew(), where an omitted parameter silently means
     * "reset to the default" and, because the repository writes every field
     * unconditionally, is persisted as a deletion.
     *
     * `new self()` rather than clone-and-assign: readonly properties cannot be
     * reassigned outside the declaring scope until PHP 8.5's clone-with, and
     * this plugin's floor is 8.1.
     *
     * @param array<string, mixed> $changes Keyed by constructor parameter name
     */
    public function with(array $changes): self
    {
        return new self(...array_merge($this->toArray(), $changes));
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
