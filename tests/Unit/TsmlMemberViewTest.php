<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Members\TsmlMemberView;
use Unity\Members\Interfaces\MemberView;
use Unity\Members\PreferredContact;
use Unity\Members\ResponderCertification;

/*
 * Tests for TsmlMemberView
 */

covers(\TsmlForUnity\Members\TsmlMemberView::class);

it('implements member view interface', function () {
    expect(new TsmlMemberView())->toBeInstanceOf(MemberView::class);
});

it('applies defaults for an empty view', function () {
    $view = new TsmlMemberView();

    expect($view->getId())->toBe(0)
        ->and($view->getAnonymousName())->toBe('')
        ->and($view->getPersonalEmail())->toBe('')
        ->and($view->getMobileNumber())->toBe('')
        ->and($view->getLandlineNumber())->toBe('')
        ->and($view->getPreferredContact())->toBe(PreferredContact::Mobile)
        ->and($view->getHomeGroupId())->toBe(0)
        ->and($view->getHomeGroupName())->toBe('')
        ->and($view->hasHomeGroup())->toBeFalse()
        ->and($view->isGSR())->toBeFalse()
        ->and($view->getPositionId())->toBe(0)
        ->and($view->getPositionName())->toBe('')
        ->and($view->hasPosition())->toBeFalse()
        ->and($view->getRotationDate())->toBe('')
        ->and($view->isTwelfthStepper())->toBeFalse()
        ->and($view->isTelephoneResponder())->toBeFalse()
        ->and($view->getResponderCertification())->toBe(ResponderCertification::None)
        ->and($view->getArea())->toBe('')
        ->and($view->getAccepts())->toBe([]);
});

it('exposes every field passed to the constructor', function () {
    $view = new TsmlMemberView(
        id: 42,
        anonymousName: 'John D.',
        personalEmail: 'john@example.com',
        mobileNumber: '0700 111',
        landlineNumber: '0117 496 0000',
        preferredContact: PreferredContact::Landline,
        homeGroupId: 10,
        homeGroupName: 'Tuesday Group',
        isGSR: true,
        positionId: 5,
        positionName: 'Chair',
        rotationDate: '2026-01-01',
        twelfthStepper: true,
        telephoneResponder: true,
        responderCertification: ResponderCertification::Certified,
        area: 'North',
        accepts: ['phone', 'email']
    );

    expect($view->getId())->toBe(42)
        ->and($view->getAnonymousName())->toBe('John D.')
        ->and($view->getPersonalEmail())->toBe('john@example.com')
        ->and($view->getMobileNumber())->toBe('0700 111')
        ->and($view->getLandlineNumber())->toBe('0117 496 0000')
        ->and($view->getPreferredContact())->toBe(PreferredContact::Landline)
        ->and($view->getHomeGroupId())->toBe(10)
        ->and($view->getHomeGroupName())->toBe('Tuesday Group')
        ->and($view->hasHomeGroup())->toBeTrue()
        ->and($view->isGSR())->toBeTrue()
        ->and($view->getPositionId())->toBe(5)
        ->and($view->getPositionName())->toBe('Chair')
        ->and($view->hasPosition())->toBeTrue()
        ->and($view->getRotationDate())->toBe('2026-01-01')
        ->and($view->isTwelfthStepper())->toBeTrue()
        ->and($view->isTelephoneResponder())->toBeTrue()
        ->and($view->getResponderCertification())->toBe(ResponderCertification::Certified)
        ->and($view->getArea())->toBe('North')
        ->and($view->getAccepts())->toBe(['phone', 'email']);
});

test('has home group and has position track their ids', function () {
    $withGroup = new TsmlMemberView(homeGroupId: 3);
    $withPosition = new TsmlMemberView(positionId: 7);

    expect($withGroup->hasHomeGroup())->toBeTrue()
        ->and($withGroup->hasPosition())->toBeFalse();

    expect($withPosition->hasHomeGroup())->toBeFalse()
        ->and($withPosition->hasPosition())->toBeTrue();
});
