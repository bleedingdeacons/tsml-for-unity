<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use InvalidArgumentException;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Members\TsmlMemberRevisor;
use Unity\Testing\Doubles\MemberStub;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRevisor;
use Unity\Members\PreferredContact;

/*
 * Unit tests for TsmlMemberRevisor
 *
 * The property under test throughout: a field you do not name is carried
 * over. That is the inverse of MemberFactory::createNew(), where an omitted
 * parameter resets to the default and the repository persists that reset as a
 * deletion.
 */

beforeEach(function () {
    $this->revisor = new TsmlMemberRevisor();
});

it('implements the unity contract', function () {
    expect($this->revisor)->toBeInstanceOf(MemberRevisor::class);
});

it('returns a member', function () {
    $revised = $this->revisor->revise(revisorMember(), mobileNumber: '07999999999');

    expect($revised)->toBeInstanceOf(Member::class);
});

test('revising one field changes only that field', function () {
    $base = revisorMember();

    $revised = $this->revisor->revise($base, mobileNumber: '07999999999');

    expect($revised->getMobileNumber())->toEqual('07999999999');

    $expected = $base->toArray();
    $expected['mobileNumber'] = '07999999999';
    expect($revised->toArray())->toEqual($expected);
});

/*
 * The bug this whole contract exists to make impossible: changing a
 * mobile number must not erase the member's GDPR consent record.
 */
test('revising an unrelated field preserves gdpr consent', function () {
    $revised = $this->revisor->revise(revisorMember(), mobileNumber: '07999999999');

    expect($revised->isGdprAccepted())->toBeTrue()
        ->and($revised->getGdprAcceptedAt())->toEqual('2026-04-27 15:45:00')
        ->and($revised->getGdprAcceptanceVersion())->toEqual('2.1')
        ->and($revised->getGdprAcceptanceMethod())->toEqual('web-form')
        ->and($revised->getGdprAcceptanceStatement())->toEqual('I agree to the privacy policy.')
        ->and($revised->isTwelfthStepper())->toBeTrue()
        ->and($revised->isTelephoneResponder())->toBeTrue()
        ->and($revised->getArea())->toEqual('North')
        ->and($revised->getAccepts())->toEqual(['accepts-male']);
});

test('revising nothing returns an equal member', function () {
    $base = revisorMember();

    expect($this->revisor->revise($base)->toArray())->toEqual($base->toArray());
});

it('can revise several fields at once', function () {
    $revised = $this->revisor->revise(
        revisorMember(),
        anonymousName: 'Jane B.',
        homeGroup: 99,
        isGSR: false
    );

    expect($revised->getAnonymousName())->toEqual('Jane B.')
        ->and($revised->getHomeGroup())->toEqual(99)
        ->and($revised->isGSR())->toBeFalse()
        ->and($revised->getPersonalEmail())->toEqual('john@example.com');
});

/*
 * Falsy values must be distinguishable from "not supplied" — the
 * changes are filtered on `!== null`, not on truthiness. If that ever
 * regressed to array_filter()'s default, revising a flag to false or a
 * string to '' would silently do nothing.
 */
test('falsy values are applied not treated as absent', function () {
    $base = revisorMember();

    $revised = $this->revisor->revise(
        $base,
        isGSR: false,
        anonymousProfile: '',
        homeGroup: 0,
        twelfthStepper: false,
        accepts: [],
        gdprAccepted: false
    );

    expect($revised->isGSR())->toBeFalse('false must be applied, not ignored')
        ->and($revised->getAnonymousProfile())->toEqual('', "'' must be applied")
        ->and($revised->getHomeGroup())->toEqual(0, '0 must be applied')
        ->and($revised->isTwelfthStepper())->toBeFalse()
        ->and($revised->getAccepts())->toEqual([], '[] must be applied')
        ->and($revised->isGdprAccepted())->toBeFalse();
});

it('leaves the base member untouched', function () {
    $base   = revisorMember();
    $before = $base->toArray();

    $this->revisor->revise($base, mobileNumber: 'changed', gdprAccepted: false);

    expect($base->toArray())->toEqual($before);
});

test('id and updated are carried over and cannot be revised', function () {
    $revised = $this->revisor->revise(revisorMember(), anonymousName: 'Jane B.');

    expect($revised->getId())->toEqual(42)
        ->and($revised->getUpdated())->toEqual('2026-04-27 15:45:00');
});

test('meeting po is carried over', function () {
    $base    = revisorMember()->with(['meetingPO' => 'PO-123']);
    $revised = $this->revisor->revise($base, anonymousName: 'Jane B.');

    expect($revised->getMeetingPO())->toEqual('PO-123');
});

/*
 * The revisor delegates to TsmlMember::with(), so it cannot revise a
 * foreign Member implementation. Fail loudly rather than silently
 * reconstructing from getters, which would be drift-prone.
 */
it('rejects a member it did not build', function () {
    $this->revisor->revise(new MemberStub(id: 1), mobileNumber: '07999999999');
})->throws(InvalidArgumentException::class, 'can only revise a TsmlMember');

// ─── the landline and the preference it governs ─────────────────
test('revising the landline alone leaves the preference alone', function () {
    $revised = $this->revisor->revise(revisorMember(), landlineNumber: '01179611111');

    expect($revised->getLandlineNumber())->toBe('01179611111')
        ->and($revised->getPreferredContact())->toBe(PreferredContact::Landline);
});

/*
 * The one case "keep unless named" cannot express: a caller clearing the
 * landline names one field, and leaving the other saying Landline would
 * point the helpline at a number that is no longer there.
 */
test('clearing the landline takes the preference with it', function () {
    $revised = $this->revisor->revise(revisorMember(), landlineNumber: '');

    expect($revised->getLandlineNumber())->toBe('')
        ->and($revised->getPreferredContact())->toBe(PreferredContact::Mobile);
});

/*
 * Only that one direction is automatic. Gaining a landline does not
 * promote it over the mobile — that stays a deliberate choice.
 */
test('adding a landline does not promote it', function () {
    $base = revisorMember();
    $withoutLandline = $this->revisor->revise($base, landlineNumber: '');

    $revised = $this->revisor->revise($withoutLandline, landlineNumber: '01179622222');

    expect($revised->getLandlineNumber())->toBe('01179622222')
        ->and($revised->getPreferredContact())->toBe(PreferredContact::Mobile);
});

test('the preference can be revised on its own', function () {
    $revised = $this->revisor->revise(revisorMember(), preferredContact: PreferredContact::Mobile);

    expect($revised->getPreferredContact())->toBe(PreferredContact::Mobile)
        ->and($revised->getLandlineNumber())->toBe('01179600000');
});

/*
 * Naming both at once still ends up consistent: the landline decides.
 */
test('a preference named alongside an empty landline is refused', function () {
    $revised = $this->revisor->revise(
        revisorMember(),
        landlineNumber: '',
        preferredContact: PreferredContact::Landline
    );

    expect($revised->getPreferredContact())->toBe(PreferredContact::Mobile);
});

function revisorMember(): TsmlMember
{
    return new TsmlMember(
        id: 42,
        anonymousName: 'John D.',
        showAnonymousName: true,
        showMemberProfile: true,
        anonymousProfile: 'A profile',
        intergroupPosition: 7,
        intergroupPositionRotation: '2026-01-01',
        homeGroup: 9,
        isGSR: true,
        meetingPO: null,
        personalEmail: 'john@example.com',
        mobileNumber: '07700900000',
        landlineNumber: '01179600000',
        preferredContact: PreferredContact::Landline,
        twelfthStepper: true,
        telephoneResponder: true,
        area: 'North',
        accepts: ['accepts-male'],
        gdprAccepted: true,
        gdprAcceptedAt: '2026-04-27 15:45:00',
        gdprAcceptanceVersion: '2.1',
        gdprAcceptanceMethod: 'web-form',
        gdprAcceptanceStatement: 'I agree to the privacy policy.',
        updated: '2026-04-27 15:45:00'
    );
}
