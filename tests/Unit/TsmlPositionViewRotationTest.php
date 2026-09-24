<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use TsmlForUnity\Positions\TsmlPositionView;
use TsmlForUnity\Positions\TsmlPositionViewFactory;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;

/*
 * Tests for choosing which member currently holds a position.
 *
 * A position can have several members attached — an outgoing officer and
 * the one who replaced them both carry the same position id — so the
 * factory picks whoever has the latest rotation date, and returns every
 * member sharing that date (a genuine job-share). Get this wrong and the
 * committee page shows the wrong name, or a rotated-off officer.
 *
 * Rotation dates arrive in two formats (Y-m-d and d/m/Y) and are often
 * missing entirely, so the unparseable and absent cases are covered
 * alongside the happy path.
 */

covers(\TsmlForUnity\Positions\TsmlPositionViewFactory::class, \TsmlForUnity\Positions\TsmlPositionView::class);

const POSITION_ID = 5;

function rotationPosition(): Position
{
    $position = test()->createMock(Position::class);
    $position->method('getId')->willReturn(POSITION_ID);
    $position->method('getShortDescription')->willReturn('Treasurer');

    return $position;
}

function rotationMember(int $id, string $rotation, string $email = ''): Member
{
    $member = test()->createMock(Member::class);
    $member->method('getId')->willReturn($id);
    $member->method('getIntergroupPosition')->willReturn(POSITION_ID);
    $member->method('getIntergroupPositionRotation')->willReturn($rotation);
    $member->method('getPersonalEmail')->willReturn($email);
    $member->method('getMobileNumber')->willReturn('');

    return $member;
}

/** @param Member[] $members */
function positionViewFactoryWith(array $members): TsmlPositionViewFactory
{
    $positions = test()->createMock(PositionRepository::class);
    $positions->method('findAll')->willReturn([rotationPosition()]);
    $positions->method('findById')->willReturn(rotationPosition());

    $memberRepo = test()->createMock(MemberRepository::class);
    $memberRepo->method('findAll')->willReturn($members);

    return new TsmlPositionViewFactory($positions, $memberRepo);
}

// ─── choosing the current holder ────────────────────────────────
test('the member with the latest rotation date is chosen', function () {
    $outgoing = rotationMember(1, '2025-01-01');
    $current  = rotationMember(2, '2027-01-01');

    $views = positionViewFactoryWith([$outgoing, $current])->createAll();

    expect($views)->toHaveCount(1)
        ->and($views[0]->getMember())->toBe($current);
});

test('the two rotation date formats are compared correctly', function () {
    // d/m/Y and Y-m-d both normalise to Y-m-d before comparison.
    $earlier = rotationMember(1, '01/01/2025');
    $later   = rotationMember(2, '2027-06-30');

    $views = positionViewFactoryWith([$earlier, $later])->createAll();

    expect($views[0]->getMember())->toBe($later);
});

test('members sharing the latest date are all returned', function () {
    // A genuine job-share: both hold the position from the same date.
    $a = rotationMember(1, '2027-01-01');
    $b = rotationMember(2, '2027-01-01');
    $old = rotationMember(3, '2020-01-01');

    $views = positionViewFactoryWith([$a, $b, $old])->createAll();

    expect($views[0]->getMembers())->toHaveCount(2);
});

test('a member with no rotation date is skipped when others have one', function () {
    $undated = rotationMember(1, '');
    $dated   = rotationMember(2, '2027-01-01');

    $views = positionViewFactoryWith([$undated, $dated])->createAll();

    expect($views[0]->getMember())->toBe($dated);
});

test('an unparseable rotation date is skipped', function () {
    $bad  = rotationMember(1, 'not a date');
    $good = rotationMember(2, '2027-01-01');

    $views = positionViewFactoryWith([$bad, $good])->createAll();

    expect($views[0]->getMember())->toBe($good);
});

test('when no date is usable the first member is taken', function () {
    // Nothing to order by, so the list order decides rather than
    // leaving the position looking vacant.
    $first  = rotationMember(1, '');
    $second = rotationMember(2, 'nonsense');

    $views = positionViewFactoryWith([$first, $second])->createAll();

    expect($views[0]->getMember())->toBe($first);
});

test('a position with no members yields a vacant view', function () {
    $views = positionViewFactoryWith([])->createAll();

    expect($views)->toHaveCount(1)
        ->and($views[0]->getMember())->toBeNull()
        ->and($views[0]->getMembers())->toBe([]);
});

test('a single member is used directly', function () {
    $only = rotationMember(1, '2027-01-01');

    $views = positionViewFactoryWith([$only])->createAll();

    expect($views[0]->getMember())->toBe($only)
        ->and($views[0]->getMembers())->toBe([$only]);
});

test('create from also resolves the latest holder', function () {
    $outgoing = rotationMember(1, '2025-01-01');
    $current  = rotationMember(2, '2027-01-01');

    $view = positionViewFactoryWith([$outgoing, $current])->createFrom(POSITION_ID);

    expect($view)->not->toBeNull()
        ->and($view->getMember())->toBe($current);
});

// ─── view construction ──────────────────────────────────────────
test('a member whose details cannot be read still yields a view', function () {
    // The view reads contact details in a try/catch: one member with a
    // broken record must not take down a whole committee listing.
    $member = $this->createMock(Member::class);
    $member->method('getId')->willReturn(1);
    $member->method('getPersonalEmail')->willThrowException(new Exception('unreadable'));

    $view = new TsmlPositionView(rotationPosition(), $member);

    expect($view->getMember())->toBe($member)
        ->and($view->getRotationDate())->toBeNull();
});

test('a view without a rotation date reports no months remaining', function () {
    $view = new TsmlPositionView(rotationPosition(), rotationMember(1, ''));

    expect($view->getMonthsUntilRotation())->toBeNull();
});

test('a future rotation date reports a positive month count', function () {
    $future = (new \DateTime('today'))->modify('+13 months')->format('Y-m-d');

    $view = new TsmlPositionView(rotationPosition(), rotationMember(1, $future));

    expect($view->getMonthsUntilRotation())->toBeGreaterThan(0);
});

test('a past rotation date reports a negative month count', function () {
    $past = (new \DateTime('today'))->modify('-13 months')->format('Y-m-d');

    $view = new TsmlPositionView(rotationPosition(), rotationMember(1, $past));

    expect($view->getMonthsUntilRotation())->toBeLessThan(0);
});
