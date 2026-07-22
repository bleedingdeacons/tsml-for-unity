<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use WP_Mock;

/**
 * Tests for TsmlIntergroupMeetingChangeTracker.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker
 */
class TsmlIntergroupMeetingChangeTrackerTest extends TestCase
{
    use ActionExpectations;

    /** @var IntergroupMeetingRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlIntergroupMeetingChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('add_action')->andReturn(true);

        $this->repository = $this->createMock(IntergroupMeetingRepository::class);
        $this->tracker = new TsmlIntergroupMeetingChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();

        (new \ReflectionClass(TsmlIntergroupMeetingChangeTracker::class))
            ->getProperty('originalMeeting')->setValue(null, null);

        parent::tearDown();
    }

    private function stubPostTypeGuard(int $postId): void
    {
        WP_Mock::userFunction('get_post_type')
            ->with($postId)
            ->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
    }

    private function stubTitleSyncIsNoop(int $postId, string $title): void
    {
        WP_Mock::userFunction('get_post')
            ->with($postId)
            ->andReturn((object) ['ID' => $postId, 'post_title' => $title]);
    }

    private function meeting(array $overrides = []): TsmlIntergroupMeeting
    {
        return new TsmlIntergroupMeeting(...array_merge([
            'id' => 42, 'title' => 'July Intergroup', 'date' => '2026-07-01',
        ], $overrides));
    }

    /**
     * @return array{TsmlIntergroupMeeting, TsmlIntergroupMeeting}
     */
    private function runSave(TsmlIntergroupMeeting $original, TsmlIntergroupMeeting $updated): array
    {
        $this->stubPostTypeGuard(42);
        $this->stubTitleSyncIsNoop(42, 'July Intergroup');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with(42)
            ->willReturnOnConsecutiveCalls($original, $updated);

        return [$original, $updated];
    }

    /**
     * @test
     */
    public function changing_the_title_fires_the_changing_hook(): void
    {
        [$original, $updated] = $this->runSave(
            $this->meeting(['title' => 'July Intergroup']),
            $this->meeting(['title' => 'August Intergroup'])
        );

        // Title changed, so the title-sync path runs; allow the update.
        WP_Mock::userFunction('wp_update_post')->andReturn(42);

        WP_Mock::expectAction('unity/intergroup_meeting_before_save', 42, $original);
        WP_Mock::expectAction('unity/intergroup_meeting_changing', $updated, $original);
        WP_Mock::expectAction('unity/intergroup_meeting_changed', 42, $updated, $original);

        $this->tracker->captureOriginalMeeting(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     * @dataProvider changedFieldProvider
     */
    public function each_tracked_field_triggers_a_change(array $originalArgs, array $updatedArgs): void
    {
        [$original, $updated] = $this->runSave($this->meeting($originalArgs), $this->meeting($updatedArgs));

        WP_Mock::expectAction('unity/intergroup_meeting_changing', $updated, $original);

        $this->tracker->captureOriginalMeeting(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @return array<string, array{array<string,mixed>, array<string,mixed>}>
     */
    public function changedFieldProvider(): array
    {
        return [
            'date'      => [['date' => '2026-07-01'], ['date' => '2026-08-01']],
            'add group' => [['groupAttendees' => [1]], ['groupAttendees' => [1, 2]]],
            'add officer' => [['officersAttending' => [3]], ['officersAttending' => [3, 4]]],
        ];
    }

    /**
     * @test
     */
    public function reordered_attendees_are_not_a_change(): void
    {
        $original = $this->meeting(['groupAttendees' => [1, 2], 'officersAttending' => [3, 4]]);
        $updated  = $this->meeting(['groupAttendees' => [2, 1], 'officersAttending' => [4, 3]]);

        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with(42)
            ->willReturnOnConsecutiveCalls($original, $updated);

        $this->expectActionNotFired('unity/intergroup_meeting_changing', $updated, $original);
        WP_Mock::expectAction('unity/intergroup_meeting_changed', 42, $updated, $original);

        $this->tracker->captureOriginalMeeting(42);
        $this->tracker->checkForChanges(42);
    }

    /**
     * @test
     */
    public function capture_ignores_a_non_meeting_post_type(): void
    {
        WP_Mock::userFunction('get_post_type')->with(99)->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalMeeting(99);
    }

    /**
     * @test
     */
    public function deleting_fires_the_deleted_hook_with_the_meeting(): void
    {
        $meeting = $this->meeting();
        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->once())->method('findById')->with(42)->willReturn($meeting);

        WP_Mock::expectAction('unity/intergroup_meeting_deleted', 42, $meeting);

        $this->tracker->onIntergroupMeetingDeleted(42);
    }

    /**
     * @test
     */
    public function deletion_fires_with_null_when_the_lookup_throws(): void
    {
        $this->stubPostTypeGuard(42);
        $this->repository->expects($this->once())
            ->method('findById')
            ->willThrowException(new \RuntimeException('gone'));

        WP_Mock::expectAction('unity/intergroup_meeting_deleted', 42, null);

        $this->tracker->onIntergroupMeetingDeleted(42);
    }

    /**
     * @test
     */
    public function deleting_ignores_a_non_meeting_post_type(): void
    {
        WP_Mock::userFunction('get_post_type')->with(99)->andReturn('post');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onIntergroupMeetingDeleted(99);
    }
}
