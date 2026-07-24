<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Groups\Interfaces\GroupRepository;
use WP_Mock;
use WP_Post;

/**
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
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupChangeTracker
 */
class TsmlGroupChangeTrackerLifecycleTest extends TestCase
{
    /** @var GroupRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlGroupChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('add_action')->andReturn(true);

        $this->repository = $this->createMock(GroupRepository::class);
        $this->tracker = new TsmlGroupChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();

        (new \ReflectionClass(TsmlGroupChangeTracker::class))
            ->getProperty('originalGroup')->setValue(null, null);

        parent::tearDown();
    }

    private function group(): TsmlGroup
    {
        return new TsmlGroup(id: 42, title: 'Tuesday Group', email: 'group@example.com');
    }

    private function post(string $type = TsmlGroupFields::POST_TYPE, int $id = 42): WP_Post
    {
        return new WP_Post(['ID' => $id, 'post_type' => $type, 'post_title' => 'Tuesday Group']);
    }

    // ─── deletion ───────────────────────────────────────────────────

    /** @test */
    public function deleting_a_group_fires_the_event_with_the_group_as_it_was(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

        $group = $this->group();
        $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

        WP_Mock::expectAction('unity/group_deleted', 42, $group);

        $this->tracker->onGroupDeleted(42);
    }

    /** @test */
    public function deleting_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onGroupDeleted(42);

        $this->assertTrue(true, 'returned before raising the event');
    }

    /** @test */
    public function a_repository_failure_during_deletion_still_fires_the_event(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

        WP_Mock::expectAction('unity/group_deleted', 42, null);

        $this->tracker->onGroupDeleted(42);

        $this->assertTrue(true, 'the exception did not escape');
    }

    // ─── hiding ─────────────────────────────────────────────────────

    /** @test */
    public function taking_a_group_private_fires_the_hidden_event(): void
    {
        $group = $this->group();
        $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

        WP_Mock::expectAction('unity/group_hidden', 42, $group);

        $this->tracker->onGroupHidden('private', 'publish', $this->post());
    }

    /** @test */
    public function a_status_change_that_is_not_a_hide_is_ignored(): void
    {
        $this->repository->expects($this->never())->method('findById');

        // Published → draft is not hiding.
        $this->tracker->onGroupHidden('draft', 'publish', $this->post());

        $this->assertTrue(true, 'no event for an unrelated transition');
    }

    /** @test */
    public function a_group_already_private_is_not_hidden_again(): void
    {
        $this->repository->expects($this->never())->method('findById');

        // Re-saving an already-private group must not re-announce it.
        $this->tracker->onGroupHidden('private', 'private', $this->post());

        $this->assertTrue(true, 'no duplicate hide event');
    }

    /** @test */
    public function hiding_a_post_of_another_type_is_ignored(): void
    {
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onGroupHidden('private', 'publish', $this->post('page'));

        $this->assertTrue(true, 'only groups raise the event');
    }

    /** @test */
    public function a_repository_failure_during_hiding_still_fires_the_event(): void
    {
        // The repository may refuse to return a private post; the event
        // still has to fire so listeners can react to the transition.
        $this->repository->method('findById')->willThrowException(new Exception('not visible'));

        WP_Mock::expectAction('unity/group_hidden', 42, null);

        $this->tracker->onGroupHidden('private', 'publish', $this->post());

        $this->assertTrue(true, 'the exception did not escape');
    }

    // ─── capture / check failure paths ──────────────────────────────

    /** @test */
    public function a_capture_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalGroup(42);

        $this->assertTrue(true, 'a failed capture must not break the save');
    }

    /** @test */
    public function a_check_that_cannot_reload_the_group_stops_quietly(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

        // Capture succeeds, then the reload comes back empty.
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($this->group(), null);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);

        $this->assertTrue(true, 'no event fired without an updated group');
    }

    /** @test */
    public function a_check_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($this->group(), $this->throwException(new Exception('boom')));

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);

        $this->assertTrue(true, 'a failed check must not break the save');
    }

    /** @test */
    public function a_renamed_group_has_its_post_title_synced(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlGroupFields::POST_TYPE);

        $original = new TsmlGroup(id: 42, title: 'Old Name', email: 'group@example.com');
        $updated  = new TsmlGroup(id: 42, title: 'New Name', email: 'group@example.com');

        $this->repository->method('findById')->willReturnOnConsecutiveCalls($original, $updated);

        // The stored post_title still holds the old name, so the tracker
        // should write the new one back.
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => 42, 'post_title' => 'Old Name']);

        $updatedPost = [];
        WP_Mock::userFunction('wp_update_post')->andReturnUsing(
            function (array $args) use (&$updatedPost): int {
                $updatedPost = $args;

                return 42;
            }
        );

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);

        $this->assertSame(42, $updatedPost['ID'] ?? null);
        $this->assertSame('New Name', $updatedPost['post_title'] ?? null);
    }
}
