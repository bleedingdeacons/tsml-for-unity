<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Positions\Interfaces\PositionRepository;

/*
 * Tests for TsmlPositionChangeTracker.
 *
 * Mirrors the member change tracker: captureOriginalPosition snapshots at
 * priority 1, checkForChanges diffs and dispatches at priority 20. These
 * pin the routing between unity/position_changing (a real change) and the
 * quiet path (no change), plus the guards that make both early-return.
 */

covers(\TsmlForUnity\Positions\TsmlPositionChangeTracker::class);

uses(ActionExpectations::class);

beforeEach(function () {
    $this->repository = $this->createMock(PositionRepository::class);
    $this->tracker = new TsmlPositionChangeTracker($this->repository);
});

afterEach(function () {
    $reflection = new \ReflectionClass(TsmlPositionChangeTracker::class);
    $reflection->getProperty('originalPosition')->setValue(null, null);
});

function stubPositionPostTypeGuard(int $postId): void
{
    Functions\expect('get_post_type')
        ->with($postId)
        ->andReturn(TsmlPositionFields::POST_TYPE);
}

function stubPositionTitleSyncIsNoop(int $postId, string $existingTitle): void
{
    Functions\expect('get_post')
        ->with($postId)
        ->andReturn((object) ['ID' => $postId, 'post_title' => $existingTitle]);
}

function trackedPosition(string $email = 'chair@example.com'): TsmlPosition
{
    return new TsmlPosition(
        id: 42,
        minimumSobriety: 6,
        termYears: 1,
        email: $email,
        longName: 'Chair',
        shortDescription: 'Chairs',
        summary: 'Runs intergroup',
    );
}

test('editing a field fires position changing', function () {
    $postId = 42;

    $original = trackedPosition('old@example.com');
    $updated  = trackedPosition('new@example.com');

    stubPositionPostTypeGuard($postId);
    // post_title already matches the encoded long name, so the title
    // sync does not call wp_update_post.
    stubPositionTitleSyncIsNoop($postId, 'Chair');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    expectDone('unity/position_before_save')->once()->with($postId, $original);
    expectDone('unity/position_changing')->once()->with($updated, $original);
    expectDone('unity/position_changed')->once()->with($postId, $updated, $original);

    $this->tracker->captureOriginalPosition($postId);
    $this->tracker->checkForChanges($postId);
});

test('saving with no field changes stays quiet', function () {
    $postId = 42;

    $original = trackedPosition();
    $updated  = trackedPosition();

    stubPositionPostTypeGuard($postId);

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    // Only the catch-all "changed" event fires; "changing" stays silent.
    expectDone('unity/position_changed')->once()->with($postId, $updated, $original);
    $this->expectActionNotFired('unity/position_changing', $updated, $original);

    $this->tracker->captureOriginalPosition($postId);
    $this->tracker->checkForChanges($postId);
});

test('capture ignores a non position post type', function () {
    $postId = 99;
    Functions\expect('get_post_type')->with($postId)->andReturn('page');

    // A wrong post type must not reach the repository.
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalPosition($postId);
});

test('check for changes returns early without a captured original', function () {
    $postId = 42;
    stubPositionPostTypeGuard($postId);

    // No capture happened, so findById must not be called by check.
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges($postId);
});

test('each tracked field triggers a change', function (TsmlPosition $original, TsmlPosition $updated) {
    $postId = 42;

    stubPositionPostTypeGuard($postId);
    stubPositionTitleSyncIsNoop($postId, 'Chair');

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    expectDone('unity/position_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalPosition($postId);
    $this->tracker->checkForChanges($postId);
})->with(function () {
    $base = fn (array $o = []) => new TsmlPosition(...array_merge([
        'id' => 42, 'minimumSobriety' => 6, 'termYears' => 1,
        'email' => 'chair@example.com', 'longName' => 'Chair',
        'shortDescription' => 'Chairs', 'summary' => 'Runs',
        'link' => 'https://example.com/c',
    ], $o));

    return [
        'sobriety'    => [$base(), $base(['minimumSobriety' => 12])],
        'term'        => [$base(), $base(['termYears' => 2])],
        'short desc'  => [$base(), $base(['shortDescription' => 'Different'])],
        'summary'     => [$base(), $base(['summary' => 'Different'])],
        'link'        => [$base(), $base(['link' => 'https://example.com/other'])],
    ];
});
