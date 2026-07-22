<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroup;
use TsmlForUnity\Groups\TsmlGroupChangeTracker;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Meetings\Interfaces\Meeting;
use WP_Mock;

/**
 * Tests for TsmlGroupChangeTracker.
 *
 * Covers the acf/save_post capture→check pair, the delete and hide hooks,
 * and the field-by-field diff in hasGroupChanged (including meeting-id and
 * contact comparisons).
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupChangeTracker
 */
class TsmlGroupChangeTrackerTest extends TestCase
{
    use ActionExpectations;

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

    private function stubPostTypeGuard(int $postId): void
    {
        WP_Mock::userFunction('get_post_type')
            ->with($postId)
            ->andReturn(TsmlGroupFields::POST_TYPE);
    }

    private function stubTitleSyncIsNoop(int $postId, string $existingTitle): void
    {
        WP_Mock::userFunction('get_post')
            ->with($postId)
            ->andReturn((object) ['ID' => $postId, 'post_title' => $existingTitle]);
    }

    private function group(array $overrides = []): TsmlGroup
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
    private function runSave(TsmlGroup $original, TsmlGroup $updated, int $postId = 42): array
    {
        $this->stubPostTypeGuard($postId);
        $this->stubTitleSyncIsNoop($postId, 'Tuesday Group');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        return [$original, $updated];
    }

    // ─── capture + check ────────────────────────────────────────────

    /**
     * @test
     */
    public function editing_a_field_fires_group_changing(): void
    {
        [$original, $updated] = $this->runSave(
            $this->group(['email' => 'old@example.com']),
            $this->group(['email' => 'new@example.com'])
        );

        WP_Mock::expectAction('group_before_save', 42, $original);
        WP_Mock::expectAction('unity/group_changing', $updated, $original);
        WP_Mock::expectAction('unity/group_changed', 42, $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function saving_with_no_change_stays_quiet(): void
    {
        $original = $this->group();
        $updated  = $this->group();

        $this->stubPostTypeGuard(42);

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with(42)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::expectAction('unity/group_changed', 42, $updated, $original);
        $this->expectActionNotFired('unity/group_changing', $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function capture_ignores_a_non_group_post_type(): void
    {
        WP_Mock::userFunction('get_post_type')->with(99)->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalGroup(99);
    }

    /**
     * @test
     */
    public function check_returns_early_without_a_captured_original(): void
    {
        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     * @dataProvider changedFieldProvider
     */
    public function each_tracked_field_triggers_a_change(array $originalArgs, array $updatedArgs): void
    {
        [$original, $updated] = $this->runSave($this->group($originalArgs), $this->group($updatedArgs));

        WP_Mock::expectAction('unity/group_changing', $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @return array<string, array{array<string,mixed>, array<string,mixed>}>
     */
    public function changedFieldProvider(): array
    {
        return [
            'notes'       => [['groupNotes' => 'a'], ['groupNotes' => 'b']],
            'website'     => [['website' => 'a'], ['website' => 'b']],
            'phone'       => [['phone' => '111'], ['phone' => '222']],
            'venmo'       => [['venmo' => '@a'], ['venmo' => '@b']],
            'paypal'      => [['paypal' => 'a'], ['paypal' => 'b']],
            'square'      => [['square' => '$a'], ['square' => '$b']],
            'districtId'  => [['districtId' => 1], ['districtId' => 2]],
            'lastContact' => [['lastContact' => '2026-01-01'], ['lastContact' => '2026-02-01']],
        ];
    }

    /**
     * @test
     */
    public function reordered_meeting_ids_are_not_a_change(): void
    {
        $m1 = $this->meeting(1);
        $m2 = $this->meeting(2);

        $original = $this->group(['meetings' => [$m1, $m2]]);
        $updated  = $this->group(['meetings' => [$m2, $m1]]);

        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with(42)
            ->willReturnOnConsecutiveCalls($original, $updated);

        $this->expectActionNotFired('unity/group_changing', $updated, $original);
        WP_Mock::expectAction('unity/group_changed', 42, $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function adding_a_meeting_is_a_change(): void
    {
        [$original, $updated] = $this->runSave(
            $this->group(['meetings' => [$this->meeting(1)]]),
            $this->group(['meetings' => [$this->meeting(1), $this->meeting(2)]])
        );

        WP_Mock::expectAction('unity/group_changing', $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function a_different_contact_is_a_change(): void
    {
        $a = new TsmlContact('Alice', 'alice@example.com', '111');
        $c = new TsmlContact('Carol', 'carol@example.com', '333');

        [$original, $updated] = $this->runSave(
            $this->group(['contacts' => [$a]]),
            $this->group(['contacts' => [$c]])
        );

        WP_Mock::expectAction('unity/group_changing', $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function reordered_contacts_are_not_a_change(): void
    {
        $a = new TsmlContact('Alice', 'alice@example.com', '111');
        $b = new TsmlContact('Bob', 'bob@example.com', '222');

        $original = $this->group(['contacts' => [$a, $b]]);
        $updated  = $this->group(['contacts' => [$b, $a]]);

        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with(42)
            ->willReturnOnConsecutiveCalls($original, $updated);

        $this->expectActionNotFired('unity/group_changing', $updated, $original);
        WP_Mock::expectAction('unity/group_changed', 42, $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function a_different_contact_count_is_a_change(): void
    {
        $a = new TsmlContact('Alice', 'alice@example.com', '111');
        $b = new TsmlContact('Bob', 'bob@example.com', '222');

        [$original, $updated] = $this->runSave(
            $this->group(['contacts' => [$a]]),
            $this->group(['contacts' => [$a, $b]])
        );

        WP_Mock::expectAction('unity/group_changing', $updated, $original);

        $this->tracker->captureOriginalGroup(42);
        $this->tracker->checkForChanges(42);
    }

    // ─── delete + hide hooks ────────────────────────────────────────

    /**
     * @test
     */
    public function deleting_a_group_fires_group_deleted_with_the_captured_group(): void
    {
        $group = $this->group();
        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

        WP_Mock::expectAction('unity/group_deleted', 42, $group);

        $this->tracker->onGroupDeleted(42);
    }

    /**
     * @test
     */
    public function deleting_ignores_a_non_group_post_type(): void
    {
        WP_Mock::userFunction('get_post_type')->with(99)->andReturn('post');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onGroupDeleted(99);
    }

    /**
     * @test
     */
    public function deletion_still_fires_with_null_when_the_lookup_throws(): void
    {
        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->once())
            ->method('findById')
            ->willThrowException(new \RuntimeException('gone'));

        WP_Mock::expectAction('unity/group_deleted', 42, null);

        $this->tracker->onGroupDeleted(42);
    }

    /**
     * @test
     */
    public function setting_a_group_to_private_fires_group_hidden(): void
    {
        $group = $this->group();
        $post = $this->wpPost(42);
        $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($group);

        WP_Mock::expectAction('unity/group_hidden', 42, $group);

        $this->tracker->onGroupHidden('private', 'publish', $post);
    }

    /**
     * @test
     */
    public function a_status_change_that_is_not_a_hide_does_nothing(): void
    {
        $post = $this->wpPost(42);
        $this->repository->expects($this->never())->method('findById');

        // publish → draft is not a hide (target status is not private).
        $this->tracker->onGroupHidden('draft', 'publish', $post);
        // private → private is not a transition into private.
        $this->tracker->onGroupHidden('private', 'private', $post);
    }

    /**
     * @test
     */
    public function hiding_ignores_a_non_group_post_type(): void
    {
        $post = $this->wpPost(42, 'post');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onGroupHidden('private', 'publish', $post);
    }

    // ─── helpers ────────────────────────────────────────────────────

    private function meeting(int $id): Meeting
    {
        $meeting = $this->createMock(Meeting::class);
        $meeting->method('getId')->willReturn($id);
        return $meeting;
    }

    private function wpPost(int $id, string $type = TsmlGroupFields::POST_TYPE): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_type = $type;
        return $post;
    }
}
