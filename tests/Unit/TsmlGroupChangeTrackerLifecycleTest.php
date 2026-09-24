<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use Exception;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Groups\Interfaces\GroupRepository;
use WP_Post;

/*
 * Tests for the group deletion, hiding and failure paths.
 *
 * Complements TsmlGroupChangeTrackerTest, which covers the save/compare
 * lifecycle. What is exercised here is everything around it: a group being
 * removed, a group being taken private, and what happens when the
 * repository cannot answer.
 *
 * Those failure branches exist because the tracker runs inside WordPress's
 * own delete and status-transition routines — an exception escaping there
 * would break the deletion itself, so the tracker swallows it and still
 * announces the event with null.
 */

covers(\TsmlForUnity\Groups\TsmlGroupChangeTracker::class);

beforeEach(function () {
    $this->repository = $this->createMock(GroupRepository::class);
    $this->tracker = new TsmlGroupChangeTracker($this->repository);
});

afterEach(function () {
    (new \ReflectionClass(TsmlGroupChangeTracker::class))
        ->getProperty('originalGroup')->setValue(null, null);
});

function lifecycleGroup(): TsmlGroup
{
    return new TsmlGroup(id: 42, title: 'Tuesday Group', email: 'group@example.com');
}

function lifecyclePost(string $type = TsmlGroupFields::POST_TYPE, int $id = 42): WP_Post
{
    return new WP_Post(['ID' => $id, 'post_type' => $type, 'post_title' => 'Tuesday Group']);
}

// ─── deletion ───────────────────────────────────────────────────
test('deleting a group fires the event with the group as it was', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

    $group = lifecycleGroup();
    $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

    expectDone('unity/group_deleted')->once()->with(42, $group);

    $this->tracker->onGroupDeleted(42);
});

test('deleting a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onGroupDeleted(42);

    // Returned before raising the event.
});

test('a repository failure during deletion still fires the event', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);
    $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

    expectDone('unity/group_deleted')->once()->with(42, null);

    $this->tracker->onGroupDeleted(42);

    // The exception did not escape.
});

// ─── hiding ─────────────────────────────────────────────────────
test('taking a group private fires the hidden event', function () {
    $group = lifecycleGroup();
    $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

    expectDone('unity/group_hidden')->once()->with(42, $group);

    $this->tracker->onGroupHidden('private', 'publish', lifecyclePost());
});

test('a status change that is not a hide is ignored', function () {
    $this->repository->expects($this->never())->method('findById');

    // Published → draft is not hiding.
    $this->tracker->onGroupHidden('draft', 'publish', lifecyclePost());

    // No event for an unrelated transition.
});

test('a group already private is not hidden again', function () {
    $this->repository->expects($this->never())->method('findById');

    // Re-saving an already-private group must not re-announce it.
    $this->tracker->onGroupHidden('private', 'private', lifecyclePost());

    // No duplicate hide event.
});

test('hiding a post of another type is ignored', function () {
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onGroupHidden('private', 'publish', lifecyclePost('page'));

    // Only groups raise the event.
});

test('a repository failure during hiding still fires the event', function () {
    // The repository may refuse to return a private post; the event
    // still has to fire so listeners can react to the transition.
    $this->repository->method('findById')->willThrowException(new Exception('not visible'));

    expectDone('unity/group_hidden')->once()->with(42, null);

    $this->tracker->onGroupHidden('private', 'publish', lifecyclePost());

    // The exception did not escape.
});

// ─── capture / check failure paths ──────────────────────────────
test('a capture failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);
    $this->repository->method('findById')->willThrowException(new Exception('boom'));

    $this->tracker->captureOriginalGroup(42);

    // A failed capture must not break the save.
})->throwsNoExceptions();

test('a check that cannot reload the group stops quietly', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

    // Capture succeeds, then the reload comes back empty.
    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(lifecycleGroup(), null);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);

    // No event fired without an updated group.
})->throwsNoExceptions();

test('a check failure is swallowed', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

    $this->repository->method('findById')
        ->willReturnOnConsecutiveCalls(lifecycleGroup(), $this->throwException(new Exception('boom')));

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);

    // A failed check must not break the save.
})->throwsNoExceptions();

test('a renamed group has its post title synced', function () {
    Functions\expect('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

    $original = new TsmlGroup(id: 42, title: 'Old Name', email: 'group@example.com');
    $updated  = new TsmlGroup(id: 42, title: 'New Name', email: 'group@example.com');

    $this->repository->method('findById')->willReturnOnConsecutiveCalls($original, $updated);

    // The stored post_title still holds the old name, so the tracker
    // should write the new one back.
    Functions\expect('get_post')
        ->andReturn((object) ['ID' => 42, 'post_title' => 'Old Name']);

    $updatedPost = [];
    Functions\expect('wp_update_post')->andReturnUsing(
        function (array $args) use (&$updatedPost): int {
            $updatedPost = $args;

            return 42;
        }
    );

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);

    expect($updatedPost['ID'] ?? null)->toBe(42)
        ->and($updatedPost['post_title'] ?? null)->toBe('New Name');
});
