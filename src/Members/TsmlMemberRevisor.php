<?php

declare(strict_types=1);

namespace TsmlForUnity\Members;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use InvalidArgumentException;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRevisor;

/**
 * TSML Member Revisor Implementation
 *
 * Turns "keep unless named" into the collected changes, then hands them to
 * {@see TsmlMember::with()}.
 *
 * Delegating to with() rather than assembling a 22-argument createNew() call
 * here is the whole point. A hand-written argument list would reintroduce
 * exactly the bug this class exists to prevent: add a field to Member, forget
 * to add it to that list, and every revised member silently loses it. with()
 * spreads TsmlMember::toArray(), whose keys are pinned to the constructor's
 * parameter names by a reflection test, so a new field is carried over
 * automatically and cannot be dropped by omission.
 */
class TsmlMemberRevisor implements MemberRevisor
{
    /**
     * {@inheritDoc}
     *
     * @param array<int, string>|null $accepts
     */
    public function revise(
        Member $base,
        ?string $anonymousName = null,
        ?bool $showAnonymousName = null,
        ?bool $showMemberProfile = null,
        ?string $anonymousProfile = null,
        ?int $intergroupPosition = null,
        ?string $intergroupPositionRotation = null,
        ?int $homeGroup = null,
        ?bool $isGSR = null,
        ?string $personalEmail = null,
        ?string $mobileNumber = null,
        ?bool $twelfthStepper = null,
        ?bool $telephoneResponder = null,
        ?string $area = null,
        ?array $accepts = null,
        ?bool $gdprAccepted = null,
        ?string $gdprAcceptedAt = null,
        ?string $gdprAcceptanceVersion = null,
        ?string $gdprAcceptanceMethod = null,
        ?string $gdprAcceptanceStatement = null
    ): Member {
        if (!$base instanceof TsmlMember) {
            // tsml-for-unity is the only implementation of Member in the
            // suite, and every Member handed out by TsmlMemberRepository is a
            // TsmlMember. Fail loudly rather than reconstructing from getters:
            // that reconstruction would be a hand-written argument list, and
            // therefore drift-prone in exactly the way with() is not.
            throw new InvalidArgumentException(sprintf(
                'TsmlMemberRevisor can only revise a TsmlMember, got %s.',
                get_debug_type($base)
            ));
        }

        // Only non-null arguments become changes; everything else is carried
        // over by with(). Keys must match TsmlMember's constructor parameter
        // names -- TsmlMemberTest pins them against the constructor.
        $changes = [
            'anonymousName'              => $anonymousName,
            'showAnonymousName'          => $showAnonymousName,
            'showMemberProfile'          => $showMemberProfile,
            'anonymousProfile'           => $anonymousProfile,
            'intergroupPosition'         => $intergroupPosition,
            'intergroupPositionRotation' => $intergroupPositionRotation,
            'homeGroup'                  => $homeGroup,
            'isGSR'                      => $isGSR,
            'personalEmail'              => $personalEmail,
            'mobileNumber'               => $mobileNumber,
            'twelfthStepper'             => $twelfthStepper,
            'telephoneResponder'         => $telephoneResponder,
            'area'                       => $area,
            'accepts'                    => $accepts,
            'gdprAccepted'               => $gdprAccepted,
            'gdprAcceptedAt'             => $gdprAcceptedAt,
            'gdprAcceptanceVersion'      => $gdprAcceptanceVersion,
            'gdprAcceptanceMethod'       => $gdprAcceptanceMethod,
            'gdprAcceptanceStatement'    => $gdprAcceptanceStatement,
        ];

        return $base->with(array_filter($changes, static fn ($v): bool => $v !== null));
    }
}
