<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionView;
use TsmlForUnity\Positions\TsmlPositionViewCollection;

/*
 * Tests for TsmlPositionViewCollection
 */

covers(\TsmlForUnity\Positions\TsmlPositionViewCollection::class);

test('an empty collection counts zero', function () {
    $collection = new TsmlPositionViewCollection();

    expect($collection->count())->toBe(0)
        ->and($collection->getAll())->toBe([]);
});

it('separates filled from vacant positions', function () {
    $filled = view('Chair', 'chair@example.com', member: collectionMember('John D.'));
    $vacant = view('Treasurer', 'treasurer@example.com');

    $collection = new TsmlPositionViewCollection([$filled, $vacant]);

    expect(array_values($collection->getFilledPositions()->getAll()))->toBe([$filled])
        ->and(array_values($collection->getVacantPositions()->getAll()))->toBe([$vacant]);
});

test('rotating soon selects positions within the window', function () {
    $soon = view('Chair', 'c@example.com', member: memberRotatingIn(10));
    $far  = view('Sec', 's@example.com', member: memberRotatingIn(400));
    $overdue = view('Treas', 't@example.com', member: memberRotatingIn(-5));

    $collection = new TsmlPositionViewCollection([$soon, $far, $overdue]);

    $rotatingSoon = array_values($collection->getPositionsRotatingSoon(30)->getAll());
    expect($rotatingSoon)->toBe([$soon]);
});

test('overdue selects positions past their rotation date', function () {
    $overdue = view('Treas', 't@example.com', member: memberRotatingIn(-5));
    $soon    = view('Chair', 'c@example.com', member: memberRotatingIn(10));

    $collection = new TsmlPositionViewCollection([$overdue, $soon]);

    expect(array_values($collection->getOverduePositions()->getAll()))->toBe([$overdue]);
});

test('sort by days until rotation puts nearest first and nulls last', function () {
    $far     = view('Far', 'f@example.com', member: memberRotatingIn(400));
    $soon    = view('Soon', 's@example.com', member: memberRotatingIn(10));
    $noDate  = view('None', 'n@example.com', member: collectionMember('No Date'));

    $collection = new TsmlPositionViewCollection([$far, $noDate, $soon]);

    $sorted = $collection->sortByDaysUntilRotation()->getAll();

    expect($sorted)->toBe([$soon, $far, $noDate]);
});

test('sort by days descending reverses the order', function () {
    $far  = view('Far', 'f@example.com', member: memberRotatingIn(400));
    $soon = view('Soon', 's@example.com', member: memberRotatingIn(10));

    $collection = new TsmlPositionViewCollection([$soon, $far]);

    expect($collection->sortByDaysUntilRotation(false)->getAll())->toBe([$far, $soon]);
});

test('sort by name orders by position long name', function () {
    $zebra = view('Zebra', 'z@example.com', longName: 'Zebra');
    $alpha = view('Alpha', 'a@example.com', longName: 'Alpha');

    $collection = new TsmlPositionViewCollection([$zebra, $alpha]);

    expect($collection->sortByName()->getAll())->toBe([$alpha, $zebra])
        ->and($collection->sortByName(false)->getAll())->toBe([$zebra, $alpha]);
});

test('sort by title orders by short description', function () {
    $b = view('B title', 'b@example.com');
    $a = view('A title', 'a@example.com');

    $collection = new TsmlPositionViewCollection([$b, $a]);

    expect($collection->sortByTitle()->getAll())->toBe([$a, $b]);
});

test('sort by email orders by position email', function () {
    $b = view('Chair', 'b@example.com');
    $a = view('Sec', 'a@example.com');

    $collection = new TsmlPositionViewCollection([$b, $a]);

    expect($collection->sortByEmail()->getAll())->toBe([$a, $b])
        ->and($collection->sortByEmail(false)->getAll())->toBe([$b, $a]);
});

test('filter applies an arbitrary predicate', function () {
    $filled = view('Chair', 'c@example.com', member: collectionMember('John'));
    $vacant = view('Sec', 's@example.com');

    $collection = new TsmlPositionViewCollection([$filled, $vacant]);

    $result = $collection->filter(fn ($view) => !$view->isVacant());

    expect($result->count())->toBe(1)
        ->and(array_values($result->getAll()))->toBe([$filled]);
});

function view(
    string $title,
    string $email,
    ?TsmlMember $member = null,
    string $longName = ''
): TsmlPositionView {
    $position = new TsmlPosition(
        id: 1,
        email: $email,
        longName: $longName !== '' ? $longName : $title,
        shortDescription: $title,
        summary: 'summary',
    );

    return new TsmlPositionView($position, $member);
}

function collectionMember(string $name): TsmlMember
{
    return new TsmlMember(id: 1, anonymousName: $name);
}

function memberRotatingIn(int $days): TsmlMember
{
    $date = (new \DateTime('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');

    return new TsmlMember(id: 1, intergroupPositionRotation: $date);
}
