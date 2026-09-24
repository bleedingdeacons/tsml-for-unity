<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use Exception;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFields;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;

/*
 * Failure and title-sync paths for the position change tracker.
 *
 * Complements TsmlPositionChangeTrackerTest, which covers the ordinary
 * capture → compare → announce flow. The branches here are the ones that
 * run when something has gone wrong mid-save: the tracker sits inside
 * ACF's save lifecycle, so a repository failure has to be contained rather
 * than allowed to abort the user's save.
 *
 * Also pinned is the post_title sync, which keeps the WordPress post title
 * in step with the position's long name — an admin list showing stale
 * titles is the visible symptom when it regresses.
 */

covers(\TsmlForUnity\Positions\TsmlPositionChangeTracker::class);

beforeEach(function () {
    $this->repository = $this->createMock(PositionRepository::class);
    $this->tracker = new TsmlPositionChangeTracker($this->repository);
});

afterEach(function () {
    (new \ReflectionClass(TsmlPositionChangeTracker::class))
        ->getProperty('originalPosition')->setValue(null, null);
});

function failingPosition(string $longName = 'Treasurer'): Position
{
    $position = test()->createMock(Position::class);
    $position->method('getId')->willReturn(9);
    $position->method('getLongName')->willReturn($longName);

    return $position;
}

test('capturing a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalPosition(9);

    // Returned before reading the position.
});

test('a capture failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);
    $this->repository->method('findById')->willThrowException(new Exception('boom'));

    $this->tracker->captureOriginalPosition(9);

    // A failed capture must not abort the save.
})->throwsNoExceptions();

test('checking a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(9);

    // Returned before comparing.
});

test('a check that cannot reload the position stops quietly', function () {
    Functions\expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

    // Capture succeeds; the reload afterwards comes back empty.
    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(failingPosition(), null);

    $this->tracker->captureOriginalPosition(9);
    $this->tracker->checkForChanges(9);

    // No event fired without an updated position.
})->throwsNoExceptions();

test('a check failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(
            failingPosition(),
            $this->throwException(new Exception('boom'))
        );

    $this->tracker->captureOriginalPosition(9);
    $this->tracker->checkForChanges(9);

    // A failed check must not abort the save.
})->throwsNoExceptions();

test('a renamed position has its post title synced', function () {
    Functions\expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

    $this->repository->method('findById')->willReturnOnConsecutiveCalls(
        failingPosition('Old Name'),
        failingPosition('New Name')
    );

    // The stored title still holds the old long name.
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => 9, 'post_title' => 'Old Name']);

    $updatedPost = [];
    Functions\expect('wp_update_post')->andReturnUsing(
        function (array $args) use (&$updatedPost): int {
            $updatedPost = $args;

            return 9;
        }
    );

    $this->tracker->captureOriginalPosition(9);
    $this->tracker->checkForChanges(9);

    expect($updatedPost['ID'] ?? null)->toBe(9)
        ->and($updatedPost['post_title'] ?? null)->toBe('New Name');
});

test('a matching post title is left alone', function () {
    Functions\expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

    $this->repository->method('findById')->willReturnOnConsecutiveCalls(
        failingPosition('Old Name'),
        failingPosition('New Name')
    );

    // post_title already matches the new long name — no write needed.
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => 9, 'post_title' => 'New Name']);

    $called = false;
    Functions\expect('wp_update_post')->andReturnUsing(function () use (&$called): int {
        $called = true;

        return 9;
    });

    $this->tracker->captureOriginalPosition(9);
    $this->tracker->checkForChanges(9);

    expect($called)->toBeFalse('An already-correct title should not be rewritten.');
});
