<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Members\TsmlMember;
use Unity\Members\Interfaces\Member;
use Unity\Members\PreferredContact;

/*
 * Tests for TsmlMember entity
 */

covers(\TsmlForUnity\Members\TsmlMember::class);

it('implements member interface', function () {
    $member = new TsmlMember(id: 1);

    expect($member)->toBeInstanceOf(Member::class);
});

it('can be instantiated with minimal values', function () {
    $member = new TsmlMember(id: 1);

    expect($member->getId())->toEqual(1)
        ->and($member->getAnonymousName())->toEqual('')
        ->and($member->showAnonymousName())->toBeFalse()
        ->and($member->showMemberProfile())->toBeFalse()
        ->and($member->getAnonymousProfile())->toEqual('')
        ->and($member->getIntergroupPosition())->toEqual(0)
        ->and($member->getIntergroupPositionRotation())->toEqual('')
        ->and($member->getHomeGroup())->toEqual(0)
        ->and($member->isGSR())->toBeFalse()
        ->and($member->getMeetingPO())->toBeNull()
        ->and($member->getPersonalEmail())->toEqual('')
        ->and($member->getMobileNumber())->toEqual('')
        ->and($member->getLandlineNumber())->toEqual('')
        ->and($member->getPreferredContact())->toBe(PreferredContact::Mobile)
        ->and($member->isTwelfthStepper())->toBeFalse()
        ->and($member->isTelephoneResponder())->toBeFalse()
        ->and($member->getArea())->toEqual('')
        ->and($member->getAccepts())->toBe([])
        ->and($member->isGdprAccepted())->toBeFalse()
        ->and($member->getGdprAcceptedAt())->toEqual('')
        ->and($member->getGdprAcceptanceVersion())->toEqual('')
        ->and($member->getGdprAcceptanceMethod())->toEqual('')
        ->and($member->getGdprAcceptanceStatement())->toEqual('');
});

it('can be instantiated with all values', function () {
    $member = new TsmlMember(
        id: 42,
        anonymousName: 'John D.',
        showAnonymousName: true,
        showMemberProfile: true,
        anonymousProfile: 'A member since 2020',
        intergroupPosition: 5,
        intergroupPositionRotation: '2024-01',
        homeGroup: 100,
        isGSR: true,
        meetingPO: 200,
        personalEmail: 'john.personal@example.com',
        mobileNumber: '+1234567890',
        landlineNumber: '0117 496 0000',
        preferredContact: PreferredContact::Landline,
        twelfthStepper: true,
        telephoneResponder: true,
        area: 'North London',
        accepts: ['phone', 'in-person'],
        gdprAccepted: true,
        gdprAcceptedAt: '2026-04-27 15:45:00',
        gdprAcceptanceVersion: '2.1',
        gdprAcceptanceMethod: 'web-form',
        gdprAcceptanceStatement: 'I agree to the privacy policy.'
    );

    expect($member->getId())->toEqual(42)
        ->and($member->getAnonymousName())->toEqual('John D.')
        ->and($member->showAnonymousName())->toBeTrue()
        ->and($member->showMemberProfile())->toBeTrue()
        ->and($member->getAnonymousProfile())->toEqual('A member since 2020')
        ->and($member->getIntergroupPosition())->toEqual(5)
        ->and($member->getIntergroupPositionRotation())->toEqual('2024-01')
        ->and($member->getHomeGroup())->toEqual(100)
        ->and($member->isGSR())->toBeTrue()
        ->and($member->getMeetingPO())->toEqual(200)
        ->and($member->getPersonalEmail())->toEqual('john.personal@example.com')
        ->and($member->getMobileNumber())->toEqual('+1234567890')
        ->and($member->getLandlineNumber())->toEqual('0117 496 0000')
        ->and($member->getPreferredContact())->toBe(PreferredContact::Landline)
        ->and($member->isTwelfthStepper())->toBeTrue()
        ->and($member->isTelephoneResponder())->toBeTrue()
        ->and($member->getArea())->toEqual('North London')
        ->and($member->getAccepts())->toBe(['phone', 'in-person'])
        ->and($member->isGdprAccepted())->toBeTrue()
        ->and($member->getGdprAcceptedAt())->toEqual('2026-04-27 15:45:00')
        ->and($member->getGdprAcceptanceVersion())->toEqual('2.1')
        ->and($member->getGdprAcceptanceMethod())->toEqual('web-form')
        ->and($member->getGdprAcceptanceStatement())->toEqual('I agree to the privacy policy.');
});

test('gsr flag can be toggled', function () {
    $gsrMember = new TsmlMember(id: 1, isGSR: true);
    $regularMember = new TsmlMember(id: 2, isGSR: false);

    expect($gsrMember->isGSR())->toBeTrue()
        ->and($regularMember->isGSR())->toBeFalse();
});

test('visibility flags work independently', function () {
    $member1 = new TsmlMember(
        id: 1,
        showAnonymousName: true,
        showMemberProfile: false
    );

    $member2 = new TsmlMember(
        id: 2,
        showAnonymousName: false,
        showMemberProfile: true
    );

    expect($member1->showAnonymousName())->toBeTrue()
        ->and($member1->showMemberProfile())->toBeFalse();

    expect($member2->showAnonymousName())->toBeFalse()
        ->and($member2->showMemberProfile())->toBeTrue();
});

it('handles empty strings for optional fields', function () {
    $member = new TsmlMember(
        id: 1,
        anonymousName: '',
        personalEmail: '',
        mobileNumber: ''
    );

    expect($member->getAnonymousName())->toBeEmpty()
        ->and($member->getPersonalEmail())->toBeEmpty()
        ->and($member->getMobileNumber())->toBeEmpty();
});

/*
 * The entity carries what it is given: the landline rule is settled at
 * the boundaries — the factory on the way in, the repository on the way
 * out, the revisor when a revision touches either field — and not in the
 * constructor, so that with() stays a plain field-level copy.
 */
test('the entity itself holds no landline invariant', function () {
    $member = new TsmlMember(
        id: 1,
        landlineNumber: '',
        preferredContact: PreferredContact::Landline
    );

    expect($member->getPreferredContact())->toBe(PreferredContact::Landline);
});

it('stores intergroup position as integer', function () {
    $member = new TsmlMember(
        id: 1,
        intergroupPosition: 10
    );

    expect($member->getIntergroupPosition())->toBeInt()
        ->and($member->getIntergroupPosition())->toEqual(10);
});

it('stores home group as integer', function () {
    $member = new TsmlMember(
        id: 1,
        homeGroup: 42
    );

    expect($member->getHomeGroup())->toBeInt()
        ->and($member->getHomeGroup())->toEqual(42);
});

test('meeting po accepts mixed types', function () {
    $withInt = new TsmlMember(id: 1, meetingPO: 200);
    $withString = new TsmlMember(id: 2, meetingPO: 'Some PO');
    $withNull = new TsmlMember(id: 3, meetingPO: null);

    expect($withInt->getMeetingPO())->toEqual(200)
        ->and($withString->getMeetingPO())->toEqual('Some PO')
        ->and($withNull->getMeetingPO())->toBeNull();
});

test('twelfth stepper and contact fields are independent', function () {
    $stepper = new TsmlMember(
        id: 1,
        twelfthStepper: true,
        area: 'East London',
        accepts: ['phone', 'email']
    );
    $regular = new TsmlMember(id: 2);

    expect($stepper->isTwelfthStepper())->toBeTrue()
        ->and($stepper->getArea())->toEqual('East London')
        ->and($stepper->getAccepts())->toBe(['phone', 'email']);

    expect($regular->isTwelfthStepper())->toBeFalse()
        ->and($regular->getArea())->toEqual('')
        ->and($regular->getAccepts())->toBe([]);
});

test('telephone responder is independent of twelfth stepper', function () {
    $responderOnly = new TsmlMember(
        id: 1,
        twelfthStepper: false,
        telephoneResponder: true
    );

    $stepperOnly = new TsmlMember(
        id: 2,
        twelfthStepper: true,
        telephoneResponder: false
    );

    $both = new TsmlMember(
        id: 3,
        twelfthStepper: true,
        telephoneResponder: true
    );

    $neither = new TsmlMember(id: 4);

    expect($responderOnly->isTwelfthStepper())->toBeFalse()
        ->and($responderOnly->isTelephoneResponder())->toBeTrue();

    expect($stepperOnly->isTwelfthStepper())->toBeTrue()
        ->and($stepperOnly->isTelephoneResponder())->toBeFalse();

    expect($both->isTwelfthStepper())->toBeTrue()
        ->and($both->isTelephoneResponder())->toBeTrue();

    expect($neither->isTwelfthStepper())->toBeFalse()
        ->and($neither->isTelephoneResponder())->toBeFalse();
});

test('gdpr compliance fields are independent', function () {
    $accepted = new TsmlMember(
        id: 1,
        gdprAccepted: true,
        gdprAcceptedAt: '2026-04-27 15:45:00',
        gdprAcceptanceVersion: '2.1',
        gdprAcceptanceMethod: 'web-form',
        gdprAcceptanceStatement: 'I agree to the privacy policy.'
    );

    $notAccepted = new TsmlMember(id: 2);

    expect($accepted->isGdprAccepted())->toBeTrue()
        ->and($accepted->getGdprAcceptedAt())->toEqual('2026-04-27 15:45:00')
        ->and($accepted->getGdprAcceptanceVersion())->toEqual('2.1')
        ->and($accepted->getGdprAcceptanceMethod())->toEqual('web-form')
        ->and($accepted->getGdprAcceptanceStatement())->toEqual('I agree to the privacy policy.');

    expect($notAccepted->isGdprAccepted())->toBeFalse()
        ->and($notAccepted->getGdprAcceptedAt())->toEqual('')
        ->and($notAccepted->getGdprAcceptanceVersion())->toEqual('')
        ->and($notAccepted->getGdprAcceptanceMethod())->toEqual('')
        ->and($notAccepted->getGdprAcceptanceStatement())->toEqual('');
});

// ── toArray() / with() ─────────────────────────────────────────────
/*
 * toArray()'s keys must stay identical to the constructor's parameter
 * names, because with() spreads the array as named arguments. If they
 * drift, with() throws "Unknown named parameter" — so pin them here.
 */
test('to array keys match the constructor parameter names', function () {
    $member = new TsmlMember(id: 1);

    $constructorParams = array_map(
        static fn (\ReflectionParameter $p): string => $p->getName(),
        (new \ReflectionMethod(TsmlMember::class, '__construct'))->getParameters()
    );

    expect(array_keys($member->toArray()))->toBe($constructorParams, 'toArray() keys must match the constructor parameter names, in order.');
});

test('to array round trips through the constructor', function () {
    $original = fullyPopulatedMember();

    $rebuilt = new TsmlMember(...$original->toArray());

    expect($rebuilt->toArray())->toEqual($original->toArray());
});

test('with replaces only the named field', function () {
    $original = fullyPopulatedMember();

    $updated = $original->with(['mobileNumber' => '07999999999']);

    expect($updated->getMobileNumber())->toEqual('07999999999');

    // Everything else carried over untouched. This is the whole point:
    // createNew() would have reset every field not passed.
    $expected = $original->toArray();
    $expected['mobileNumber'] = '07999999999';
    expect($updated->toArray())->toEqual($expected);
});

/*
 * The failure mode this class exists to prevent: a partial update must
 * not silently erase the GDPR consent record.
 */
test('with preserves gdpr consent when changing an unrelated field', function () {
    $member = fullyPopulatedMember();

    $updated = $member->with(['mobileNumber' => '07999999999']);

    expect($updated->isGdprAccepted())->toBeTrue()
        ->and($updated->getGdprAcceptedAt())->toEqual('2026-04-27 15:45:00')
        ->and($updated->getGdprAcceptanceVersion())->toEqual('2.1')
        ->and($updated->getGdprAcceptanceMethod())->toEqual('web-form')
        ->and($updated->getGdprAcceptanceStatement())->toEqual('I agree to the privacy policy.')
        ->and($updated->isTelephoneResponder())->toBeTrue()
        ->and($updated->getArea())->toEqual('North')
        ->and($updated->getAccepts())->toEqual(['accepts-male']);
});

test('with can replace several fields at once', function () {
    $member = fullyPopulatedMember();

    $updated = $member->with([
        'anonymousName' => 'Jane B.',
        'homeGroup'     => 99,
        'gdprAccepted'  => false,
    ]);

    expect($updated->getAnonymousName())->toEqual('Jane B.')
        ->and($updated->getHomeGroup())->toEqual(99)
        ->and($updated->isGdprAccepted())->toBeFalse()
        ->and($updated->getPersonalEmail())->toEqual('john@example.com');
});

test('with leaves the original untouched', function () {
    $original = fullyPopulatedMember();
    $before   = $original->toArray();

    $original->with(['mobileNumber' => 'changed']);

    expect($original->toArray())->toEqual($before, 'with() must not mutate the receiver.');
});

test('with no changes returns an equal member', function () {
    $original = fullyPopulatedMember();

    expect($original->with([])->toArray())->toEqual($original->toArray());
});

function fullyPopulatedMember(): TsmlMember
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
