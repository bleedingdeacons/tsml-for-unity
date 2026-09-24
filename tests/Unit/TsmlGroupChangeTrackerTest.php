<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Meetings\Interfaces\Meeting;

/*
 * Tests for TsmlGroupChangeTracker.
 *
 * Covers the acf/save_post capture→check pair, the delete and hide hooks,
 * and the field-by-field diff in hasGroupChanged (including meeting-id and
 * contact comparisons).
 */

covers(\TsmlForUnity\Groups\TsmlGroupChangeTracker::class);

uses(ActionExpectations::class);

beforeEach(function () {
    $this->repository = $this->createMock(GroupRepository::class);
    $this->tracker = new TsmlGroupChangeTracker($this->repository);
});

afterEach(function () {
    (new \ReflectionClass(TsmlGroupChangeTracker::class))
        ->getProperty('originalGroup')->setValue(null, null);
});

function stubGroupPostTypeGuard(int $postId): void
{
    Functions\expect('get_post_type')
        ->with($postId)
        ->andReturn(TsmlGroupFields::POST_TYPE);
}

function stubGroupTitleSyncIsNoop(int $postId, string $existingTitle): void
{
    Functions\expect('get_post')
        ->with($postId)
        ->andReturn((object) ['ID' => $postId, 'post_title' => $existingTitle]);
}

function trackedGroup(array $overrides = []): TsmlGroup
{
    return new TsmlGroup(...array_merge([
        'id' => 42, 'title' => 'Tuesday Group', 'email' => 'group@example.com',
    ], $overrides));
}

/**
 * Run capture→check for a pair of groups and return them so the caller
 * can assert on the fired action.
 *
 * @return array{TsmlGroup, TsmlGroup}
 */
function runGroupSave(TsmlGroup $original, TsmlGroup $updated, int $postId = 42): array
{
    stubGroupPostTypeGuard($postId);
    stubGroupTitleSyncIsNoop($postId, 'Tuesday Group');

    test()->repository->expects(test()->exactly(2))
        ->method('findById')
        ->with($postId)
        ->willReturnOnConsecutiveCalls($original, $updated);

    return [$original, $updated];
}

// ─── capture + check ────────────────────────────────────────────
test('editing a field fires group changing', function () {
    [$original, $updated] = runGroupSave(
        trackedGroup(['email' => 'old@example.com']),
        trackedGroup(['email' => 'new@example.com'])
    );

    expectDone('group_before_save')->once()->with(42, $original);
    expectDone('unity/group_changing')->once()->with($updated, $original);
    expectDone('unity/group_changed')->once()->with(42, $updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('saving with no change stays quiet', function () {
    $original = trackedGroup();
    $updated  = trackedGroup();

    stubGroupPostTypeGuard(42);

    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with(42)
        ->willReturnOnConsecutiveCalls($original, $updated);

    expectDone('unity/group_changed')->once()->with(42, $updated, $original);
    $this->expectActionNotFired('unity/group_changing', $updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('capture ignores a non group post type', function () {
    Functions\expect('get_post_type')->with(99)->andReturn('page');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->captureOriginalGroup(99);
});

test('check returns early without a captured original', function () {
    stubGroupPostTypeGuard(42);
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->checkForChanges(42);
});

test('each tracked field triggers a change', function (array $originalArgs, array $updatedArgs) {
    [$original, $updated] = runGroupSave(trackedGroup($originalArgs), trackedGroup($updatedArgs));

    expectDone('unity/group_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
})->with([
    'notes'       => [['groupNotes' => 'a'], ['groupNotes' => 'b']],
    'website'     => [['website' => 'a'], ['website' => 'b']],
    'phone'       => [['phone' => '111'], ['phone' => '222']],
    'venmo'       => [['venmo' => '@a'], ['venmo' => '@b']],
    'paypal'      => [['paypal' => 'a'], ['paypal' => 'b']],
    'square'      => [['square' => '$a'], ['square' => '$b']],
    'districtId'  => [['districtId' => 1], ['districtId' => 2]],
    'lastContact' => [['lastContact' => '2026-01-01'], ['lastContact' => '2026-02-01']],
]);

test('reordered meeting ids are not a change', function () {
    $m1 = groupTrackerMeeting(1);
    $m2 = groupTrackerMeeting(2);

    $original = trackedGroup(['meetings' => [$m1, $m2]]);
    $updated  = trackedGroup(['meetings' => [$m2, $m1]]);

    stubGroupPostTypeGuard(42);
    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with(42)
        ->willReturnOnConsecutiveCalls($original, $updated);

    $this->expectActionNotFired('unity/group_changing', $updated, $original);
    expectDone('unity/group_changed')->once()->with(42, $updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('adding a meeting is a change', function () {
    [$original, $updated] = runGroupSave(
        trackedGroup(['meetings' => [groupTrackerMeeting(1)]]),
        trackedGroup(['meetings' => [groupTrackerMeeting(1), groupTrackerMeeting(2)]])
    );

    expectDone('unity/group_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('a different contact is a change', function () {
    $a = new TsmlContact('Alice', 'alice@example.com', '111');
    $c = new TsmlContact('Carol', 'carol@example.com', '333');

    [$original, $updated] = runGroupSave(
        trackedGroup(['contacts' => [$a]]),
        trackedGroup(['contacts' => [$c]])
    );

    expectDone('unity/group_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('reordered contacts are not a change', function () {
    $a = new TsmlContact('Alice', 'alice@example.com', '111');
    $b = new TsmlContact('Bob', 'bob@example.com', '222');

    $original = trackedGroup(['contacts' => [$a, $b]]);
    $updated  = trackedGroup(['contacts' => [$b, $a]]);

    stubGroupPostTypeGuard(42);
    $this->repository->expects($this->exactly(2))
        ->method('findById')
        ->with(42)
        ->willReturnOnConsecutiveCalls($original, $updated);

    $this->expectActionNotFired('unity/group_changing', $updated, $original);
    expectDone('unity/group_changed')->once()->with(42, $updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

test('a different contact count is a change', function () {
    $a = new TsmlContact('Alice', 'alice@example.com', '111');
    $b = new TsmlContact('Bob', 'bob@example.com', '222');

    [$original, $updated] = runGroupSave(
        trackedGroup(['contacts' => [$a]]),
        trackedGroup(['contacts' => [$a, $b]])
    );

    expectDone('unity/group_changing')->once()->with($updated, $original);

    $this->tracker->captureOriginalGroup(42);
    $this->tracker->checkForChanges(42);
});

// ─── delete + hide hooks ────────────────────────────────────────
test('deleting a group fires group deleted with the captured group', function () {
    $group = trackedGroup();
    stubGroupPostTypeGuard(42);
    $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

    expectDone('unity/group_deleted')->once()->with(42, $group);

    $this->tracker->onGroupDeleted(42);
});

test('deleting ignores a non group post type', function () {
    Functions\expect('get_post_type')->with(99)->andReturn('post');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onGroupDeleted(99);
});

test('deletion still fires with null when the lookup throws', function () {
    stubGroupPostTypeGuard(42);
    $this->repository->expects($this->once())
        ->method('findById')
        ->willThrowException(new \RuntimeException('gone'));

    expectDone('unity/group_deleted')->once()->with(42, null);

    $this->tracker->onGroupDeleted(42);
});

test('setting a group to private fires group hidden', function () {
    $group = trackedGroup();
    $post = groupWpPost(42);
    $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

    expectDone('unity/group_hidden')->once()->with(42, $group);

    $this->tracker->onGroupHidden('private', 'publish', $post);
});

test('a status change that is not a hide does nothing', function () {
    $post = groupWpPost(42);
    $this->repository->expects($this->never())->method('findById');

    // publish → draft is not a hide (target status is not private).
    $this->tracker->onGroupHidden('draft', 'publish', $post);
    // private → private is not a transition into private.
    $this->tracker->onGroupHidden('private', 'private', $post);
});

test('hiding ignores a non group post type', function () {
    $post = groupWpPost(42, 'post');
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onGroupHidden('private', 'publish', $post);
});

// ─── helpers ────────────────────────────────────────────────────
function groupTrackerMeeting(int $id): Meeting
{
    $meeting = test()->createMock(Meeting::class);
    $meeting->method('getId')->willReturn($id);
    return $meeting;
}

function groupWpPost(int $id, string $type = TsmlGroupFields::POST_TYPE): \WP_Post
{
    $post = new \WP_Post();
    $post->ID = $id;
    $post->post_type = $type;
    return $post;
}
