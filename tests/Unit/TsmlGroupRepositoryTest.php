<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use Brain\Monkey\Functions;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Groups\TsmlGroupRepository;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Meetings\Interfaces\Meeting;

/*
 * Tests for TsmlGroupRepository's write paths.
 *
 * The central contract here is the MEETING field: it stores meeting IDs,
 * but Group exposes Meeting objects via getMeetings(). Both save() (insert)
 * and update() have to bridge that gap. These tests pin the translation
 * down in both paths, because a regression there is silent — update_field()
 * accepts whatever it is handed.
 */

covers(\TsmlForUnity\Groups\TsmlGroupRepository::class);

beforeEach(function () {
    $this->factory = $this->createMock(GroupFactory::class);
    $this->repository = new TsmlGroupRepository($this->factory);
});

/**
 * Helper: a Meeting that knows only its ID — the sole property the
 * repository reads when building the MEETING field.
 *
 * @return Meeting&MockObject
 */
function meetingWithId(int $id)
{
    $meeting = test()->createMock(Meeting::class);
    $meeting->method('getId')->willReturn($id);

    return $meeting;
}

/**
 * Helper: a Group carrying the given Meeting objects.
 *
 * isValid() is stubbed independently of the ID: the repository is typed
 * against the Group interface, where identity and validity are separate
 * parts of the contract, so both combinations stay reachable here
 * regardless of what any one implementation ties together.
 *
 * @param Meeting[] $meetings
 * @return Group&MockObject
 */
function groupWith(int $id, array $meetings, bool $valid = true, string $title = 'Tuesday Big Book')
{
    $group = test()->createMock(Group::class);
    $group->method('getId')->willReturn($id);
    $group->method('getTitle')->willReturn($title);
    $group->method('getEmail')->willReturn('group@example.test');
    $group->method('isValid')->willReturn($valid);
    $group->method('getMeetings')->willReturn($meetings);

    return $group;
}

/**
 * Helper: capture the value written to a given ACF field.
 *
 * update_field() is variadic across several fields per save; this
 * narrows to one field and records what it received.
 */
function captureGroupUpdateField(string $field, &$captured): void
{
    Functions\expect('update_field')
        ->withArgs(function ($key) use ($field) {
            return $key === $field;
        })
        ->andReturnUsing(function ($key, $value) use (&$captured) {
            $captured = $value;
            return true;
        });

    // The other fields in the same save are not under test.
    Functions\expect('update_field')->andReturn(true);
}

// ─── save() insert path writes meeting IDs ──────────────────────
/*
 * Reaching the insert branch needs getId() === 0 — otherwise save()
 * delegates to update() — *and* isValid() === true.
 */
test('save insert writes meeting ids not meeting objects', function () {
    $newPostId = 4242;

    // id = 0 => insert path.
    $group = groupWith(0, [
        meetingWithId(200),
        meetingWithId(201),
        meetingWithId(202),
    ]);

    Functions\expect('wp_insert_post')->once()->andReturn($newPostId);

    $written = null;
    captureGroupUpdateField(TsmlGroupFields::MEETING, $written);

    $result = $this->repository->save($group);

    expect($result)->toBeTrue()
        ->and($written)->toBe([200, 201, 202]);
});

test('save insert writes empty array when group has no meetings', function () {
    Functions\expect('wp_insert_post')->once()->andReturn(4242);

    $written = null;
    captureGroupUpdateField(TsmlGroupFields::MEETING, $written);

    expect($this->repository->save(groupWith(0, [])))->toBeTrue()
        ->and($written)->toBe([]);
});

test('save returns false for invalid group without inserting', function () {
    // No wp_insert_post expectation: a call would fail the test.
    $group = groupWith(0, [meetingWithId(200)], false);

    expect($this->repository->save($group))->toBeFalse();
});

test('save returns false when wp insert post fails', function () {
    $group = groupWith(0, [meetingWithId(200)]);

    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_insert_post')->once()->andReturn($error);

    // No update_field expectation: the failure returns before any writes.

    expect($this->repository->save($group))->toBeFalse();
});

// ─── save() insert path accepts a real, unsaved TsmlGroup ───────
/*
 * The insert tests above stub isValid() independently of the ID, so
 * they stayed green while no real TsmlGroup could reach the insert
 * branch at all: validity demanded id > 0, the branch demanded id 0.
 * These two pin the concrete class to the reachable combination.
 */
test('save inserts a new unsaved tsml group', function () {
    $group = new TsmlGroup(0, 'Brand New Group');

    expect($group->isValid())->toBeTrue('A titled, unsaved group must be valid');

    Functions\expect('wp_insert_post')->once()->andReturn(4242);

    $written = null;
    captureGroupUpdateField(TsmlGroupFields::TITLE, $written);

    expect($this->repository->save($group))->toBeTrue()
        ->and($written)->toBe('Brand New Group');
});

test('save rejects a new tsml group with no title', function () {
    // No wp_insert_post expectation: a titleless group must not insert.
    expect($this->repository->save(new TsmlGroup(0, '')))->toBeFalse();
});

// ─── update() path writes meeting IDs ───────────────────────────
test('update writes meeting ids not meeting objects', function () {
    $postId = 4242;

    $group = groupWith($postId, [
        meetingWithId(300),
        meetingWithId(301),
    ]);

    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $written = null;
    captureGroupUpdateField(TsmlGroupFields::MEETING, $written);

    $result = $this->repository->update($group);

    expect($result)->toBeTrue()
        ->and($written)->toBe([300, 301]);
});

test('save with existing id delegates to update and writes meeting ids', function () {
    // save() with id > 0 must delegate to update() — observed via
    // wp_update_post being used rather than wp_insert_post.
    $postId = 4242;

    $group = groupWith($postId, [meetingWithId(300)]);

    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $written = null;
    captureGroupUpdateField(TsmlGroupFields::MEETING, $written);

    expect($this->repository->save($group))->toBeTrue()
        ->and($written)->toBe([300]);
});

test('update returns false for zero post id without writing', function () {
    // Zero ID never reaches wp_update_post or update_field.
    $group = groupWith(0, [meetingWithId(300)]);

    expect($this->repository->update($group))->toBeFalse();
});

test('update returns false when wp update post fails', function () {
    $postId = 4242;

    $group = groupWith($postId, [meetingWithId(300)]);

    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_update_post')->once()->andReturn($error);

    expect($this->repository->update($group))->toBeFalse();
});
