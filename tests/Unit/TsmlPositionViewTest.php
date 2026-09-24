<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionView;
use Unity\Positions\Interfaces\PositionView;

/*
 * Tests for TsmlPositionView
 */

covers(\TsmlForUnity\Positions\TsmlPositionView::class);

it('implements position view interface', function () {
    $view = new TsmlPositionView(viewPosition());

    expect($view)->toBeInstanceOf(PositionView::class);
});

test('a view with no member is vacant', function () {
    $view = new TsmlPositionView(viewPosition());

    expect($view->isVacant())->toBeTrue()
        ->and($view->getMember())->toBeNull()
        ->and($view->getMembers())->toBe([])
        ->and($view->getOfficerDisplayName())->toBe('')
        ->and($view->getPublicDisplayName())->toBe('')
        ->and($view->getPersonalEmail())->toBeNull()
        ->and($view->getMobileNumber())->toBeNull()
        ->and($view->getRotationDate())->toBeNull()
        ->and($view->getMonthsUntilRotation())->toBeNull()
        ->and($view->getDaysUntilRotation())->toBeNull();
});

it('derives title email and description from the position', function () {
    $view = new TsmlPositionView(viewPosition());

    expect($view->getTitle())->toBe('Chairs the meeting')
        ->and($view->getDescription())->toBe('Chairs the meeting')
        ->and($view->getPositionEmail())->toBe('chair@example.com');
});

test('a view with a member pulls contact details from it', function () {
    $member = new TsmlMember(
        id: 1,
        anonymousName: 'John D.',
        showAnonymousName: true,
        personalEmail: 'john@example.com',
        mobileNumber: '0700 111',
    );

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->isVacant())->toBeFalse()
        ->and($view->getMember())->toBe($member)
        ->and($view->getMembers())->toBe([$member])
        ->and($view->getPersonalEmail())->toBe('john@example.com')
        ->and($view->getMobileNumber())->toBe('0700 111')
        ->and($view->getOfficerDisplayName())->toBe('John D.')
        ->and($view->getPublicDisplayName())->toBe('John D.');
});

test('public display name is hidden when the member opts out', function () {
    $member = new TsmlMember(id: 1, anonymousName: 'John D.', showAnonymousName: false);

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->getPublicDisplayName())->toBe('');
});

test('officer display name joins all members', function () {
    $a = new TsmlMember(id: 1, anonymousName: 'John D.');
    $b = new TsmlMember(id: 2, anonymousName: 'Jane B.');

    $view = new TsmlPositionView(viewPosition(), $a, [$a, $b]);

    expect($view->getOfficerDisplayName())->toBe('John D., Jane B.')
        ->and($view->getMembers())->toBe([$a, $b]);
});

it('parses an iso rotation date in the future', function () {
    $future = (new \DateTime('today'))->modify('+40 days')->format('Y-m-d');
    $member = new TsmlMember(id: 1, intergroupPositionRotation: $future);

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->getRotationDate())->toBeInstanceOf(\DateTime::class)
        ->and($view->getRotationDate()->format('Y-m-d'))->toBe($future)
        ->and($view->getDaysUntilRotation())->toBe(40)
        ->and($view->getMonthsUntilRotation())->toBeGreaterThan(0);
});

it('parses a uk format rotation date', function () {
    $member = new TsmlMember(id: 1, intergroupPositionRotation: '25/12/2099');

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->getRotationDate()->format('Y-m-d'))->toBe('2099-12-25');
});

test('a past rotation date reports zero days but negative months', function () {
    $past = (new \DateTime('today'))->modify('-40 days')->format('Y-m-d');
    $member = new TsmlMember(id: 1, intergroupPositionRotation: $past);

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->getDaysUntilRotation())->toBe(0)
        ->and($view->getMonthsUntilRotation())->toBeLessThan(0);
});

test('an unparseable rotation date yields no rotation', function () {
    $member = new TsmlMember(id: 1, intergroupPositionRotation: 'not-a-date');

    $view = new TsmlPositionView(viewPosition(), $member);

    expect($view->getRotationDate())->toBeNull()
        ->and($view->getDaysUntilRotation())->toBeNull()
        ->and($view->getMonthsUntilRotation())->toBeNull();
});

test('is archivist matches the role case insensitively', function () {
    $archivist = new TsmlPositionView(new TsmlPosition(shortDescription: 'archivist'));
    $chair     = new TsmlPositionView(viewPosition());

    expect($archivist->isArchivist())->toBeTrue()
        ->and($chair->isArchivist())->toBeFalse();
});

function viewPosition(): TsmlPosition
{
    return new TsmlPosition(
        id: 5,
        email: 'chair@example.com',
        longName: 'Intergroup Chair',
        shortDescription: 'Chairs the meeting',
        summary: 'Runs intergroup',
    );
}
