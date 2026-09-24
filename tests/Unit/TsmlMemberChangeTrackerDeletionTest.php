<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use Exception;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;

/*
 * Tests for the member deletion event.
 *
 * onMemberDeleted() is wired to both before_delete_post and wp_trash_post,
 * and its job is to hand listeners the member *as it was* before removal —
 * Scrutiny's audit tracker relies on that to record what was deleted.
 *
 * The failure path matters as much as the happy one: by the time the hook
 * runs the record may already be partially gone, so a repository blow-up
 * must still produce a unity/member_deleted event rather than letting an
 * exception escape into WordPress's delete routine.
 *
 * Actions are asserted through Brain Monkey's own expectations rather than
 * by stubbing do_action(), which Brain Monkey defines itself.
 */

covers(\TsmlForUnity\Members\TsmlMemberChangeTracker::class);

uses(ActionExpectations::class);

beforeEach(function () {
    $this->repository = $this->createMock(MemberRepository::class);
    $this->tracker = new TsmlMemberChangeTracker($this->repository);
});

test('a post of another type is ignored', function () {
    Functions\expect('get_post_type')->andReturn('page');

    // Bailing before the lookup is the observable behaviour: nothing is
    // read, so nothing can be announced.
    $this->repository->expects($this->never())->method('findById');

    $this->tracker->onMemberDeleted(5);

    // Returned without raising the event.
});

test('deleting a member fires the event with the member as it was', function () {
    Functions\expect('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

    $member = $this->createMock(Member::class);
    $this->repository->expects($this->once())->method('findById')->with(5)->willReturn($member);

    // Listeners need the pre-deletion snapshot, so the member travels
    // with the event.
    expectDone('unity/member_deleted')->once()->with(5, $member);

    $this->tracker->onMemberDeleted(5);
});

test('a member that can no longer be loaded still fires the event', function () {
    Functions\expect('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

    // findById() returning null is not an error — the row may already
    // be gone — so the event still fires, carrying null.
    $this->repository->method('findById')->willReturn(null);

    expectDone('unity/member_deleted')->once()->with(5, null);

    $this->tracker->onMemberDeleted(5);

    // The event fired with a null member.
});

test('a repository failure does not escape and still fires the event', function () {
    Functions\expect('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

    // A partially-removed record can make the lookup throw; the hook
    // must swallow it rather than derail WordPress's delete routine.
    $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

    expectDone('unity/member_deleted')->once()->with(5, null);

    $this->tracker->onMemberDeleted(5);

    // The exception did not escape.
});

test('the event is not raised for a post type that merely resembles a member', function () {
    Functions\expect('get_post_type')->andReturn('intergroup-member-archive');

    $this->expectActionNotFired('unity/member_deleted', 5, null);

    $this->tracker->onMemberDeleted(5);

    // No event for a near-miss post type.
});
