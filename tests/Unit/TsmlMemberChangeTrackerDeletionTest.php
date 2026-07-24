<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberChangeTracker;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use WP_Mock;

/**
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
 * Actions are asserted through WP_Mock's own action interception rather
 * than by stubbing do_action(), which WP_Mock defines itself.
 *
 * @covers \TsmlForUnity\Members\TsmlMemberChangeTracker
 */
class TsmlMemberChangeTrackerDeletionTest extends TestCase
{
    use ActionExpectations;

    /** @var MemberRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlMemberChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('add_action')->andReturn(true);

        $this->repository = $this->createMock(MemberRepository::class);
        $this->tracker = new TsmlMemberChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /** @test */
    public function a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');

        // Bailing before the lookup is the observable behaviour: nothing is
        // read, so nothing can be announced.
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onMemberDeleted(5);

        $this->assertTrue(true, 'returned without raising the event');
    }

    /** @test */
    public function deleting_a_member_fires_the_event_with_the_member_as_it_was(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

        $member = $this->createMock(Member::class);
        $this->repository->expects($this->once())->method('findById')->with(5)->willReturn($member);

        // Listeners need the pre-deletion snapshot, so the member travels
        // with the event.
        WP_Mock::expectAction('unity/member_deleted', 5, $member);

        $this->tracker->onMemberDeleted(5);
    }

    /** @test */
    public function a_member_that_can_no_longer_be_loaded_still_fires_the_event(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

        // findById() returning null is not an error — the row may already
        // be gone — so the event still fires, carrying null.
        $this->repository->method('findById')->willReturn(null);

        WP_Mock::expectAction('unity/member_deleted', 5, null);

        $this->tracker->onMemberDeleted(5);

        $this->assertTrue(true, 'the event fired with a null member');
    }

    /** @test */
    public function a_repository_failure_does_not_escape_and_still_fires_the_event(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlMemberFields::POST_TYPE);

        // A partially-removed record can make the lookup throw; the hook
        // must swallow it rather than derail WordPress's delete routine.
        $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

        WP_Mock::expectAction('unity/member_deleted', 5, null);

        $this->tracker->onMemberDeleted(5);

        $this->assertTrue(true, 'the exception did not escape');
    }

    /** @test */
    public function the_event_is_not_raised_for_a_post_type_that_merely_resembles_a_member(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('intergroup-member-archive');

        $this->expectActionNotFired('unity/member_deleted', 5, null);

        $this->tracker->onMemberDeleted(5);

        $this->assertTrue(true, 'no event for a near-miss post type');
    }
}
