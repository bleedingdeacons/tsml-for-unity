<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use WP_Mock;

/**
 * Guard and failure paths for the intergroup meeting change tracker.
 *
 * Complements TsmlIntergroupMeetingChangeTrackerTest, which covers the
 * ordinary save flow. These are the branches that run when the tracker is
 * handed something it should ignore, or when the repository cannot answer
 * — it hooks ACF's save lifecycle and WordPress's delete routine, so a
 * failure has to be contained rather than propagated.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingChangeTracker
 */
class TsmlIntergroupMeetingChangeTrackerFailureTest extends TestCase
{
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

    private function meeting(): IntergroupMeeting
    {
        return $this->createMock(IntergroupMeeting::class);
    }

    /** @test */
    public function capturing_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalMeeting(3);

        $this->assertTrue(true, 'returned before reading the meeting');
    }

    /** @test */
    public function a_capture_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalMeeting(3);

        $this->assertTrue(true, 'a failed capture must not abort the save');
    }

    /** @test */
    public function checking_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(3);

        $this->assertTrue(true, 'returned before comparing');
    }

    /** @test */
    public function a_check_without_a_captured_original_stops_quietly(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
        // No captureOriginalMeeting() call, so there is nothing to compare.
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(3);

        $this->assertTrue(true, 'no comparison without a snapshot');
    }

    /** @test */
    public function a_check_that_cannot_reload_the_meeting_stops_quietly(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);

        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($this->meeting(), null);

        $this->tracker->captureOriginalMeeting(3);
        $this->tracker->checkForChanges(3);

        $this->assertTrue(true, 'no event fired without an updated meeting');
    }

    /** @test */
    public function a_check_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);

        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls(
                $this->meeting(),
                $this->throwException(new Exception('boom'))
            );

        $this->tracker->captureOriginalMeeting(3);
        $this->tracker->checkForChanges(3);

        $this->assertTrue(true, 'a failed check must not abort the save');
    }

    /** @test */
    public function deleting_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->onIntergroupMeetingDeleted(3);

        $this->assertTrue(true, 'only intergroup meetings raise the event');
    }

    /** @test */
    public function a_repository_failure_during_deletion_is_contained(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlIntergroupMeetingFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('row vanished'));

        $this->tracker->onIntergroupMeetingDeleted(3);

        $this->assertTrue(true, 'the exception did not escape the delete routine');
    }
}
