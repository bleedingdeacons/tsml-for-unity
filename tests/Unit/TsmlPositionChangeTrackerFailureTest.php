<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFields;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;
use WP_Mock;

/**
 * Failure and title-sync paths for the position change tracker.
 *
 * Complements TsmlPositionChangeTrackerTest, which covers the ordinary
 * capture → compare → announce flow. The branches here are the ones that
 * run when something has gone wrong mid-save: the tracker sits inside
 * ACF's save lifecycle, so a repository failure has to be contained rather
 * than allowed to abort the user's save.
 *
 * Also pinned is the post_title sync, which keeps the WordPress post title
 * in step with the position's long name — an admin list showing stale
 * titles is the visible symptom when it regresses.
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionChangeTracker
 */
class TsmlPositionChangeTrackerFailureTest extends TestCase
{
    /** @var PositionRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $repository;

    private TsmlPositionChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('add_action')->andReturn(true);

        $this->repository = $this->createMock(PositionRepository::class);
        $this->tracker = new TsmlPositionChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();

        (new \ReflectionClass(TsmlPositionChangeTracker::class))
            ->getProperty('originalPosition')->setValue(null, null);

        parent::tearDown();
    }

    private function position(string $longName = 'Treasurer'): Position
    {
        $position = $this->createMock(Position::class);
        $position->method('getId')->willReturn(9);
        $position->method('getLongName')->willReturn($longName);

        return $position;
    }

    /** @test */
    public function capturing_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalPosition(9);

        $this->assertTrue(true, 'returned before reading the position');
    }

    /** @test */
    public function a_capture_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalPosition(9);

        $this->assertTrue(true, 'a failed capture must not abort the save');
    }

    /** @test */
    public function checking_a_post_of_another_type_is_ignored(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'returned before comparing');
    }

    /** @test */
    public function a_check_that_cannot_reload_the_position_stops_quietly(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        // Capture succeeds; the reload afterwards comes back empty.
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($this->position(), null);

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'no event fired without an updated position');
    }

    /** @test */
    public function a_check_failure_is_swallowed(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls(
                $this->position(),
                $this->throwException(new Exception('boom'))
            );

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'a failed check must not abort the save');
    }

    /** @test */
    public function a_renamed_position_has_its_post_title_synced(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')->willReturnOnConsecutiveCalls(
            $this->position('Old Name'),
            $this->position('New Name')
        );

        // The stored title still holds the old long name.
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => 9, 'post_title' => 'Old Name']);

        $updatedPost = [];
        WP_Mock::userFunction('wp_update_post')->andReturnUsing(
            function (array $args) use (&$updatedPost): int {
                $updatedPost = $args;

                return 9;
            }
        );

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertSame(9, $updatedPost['ID'] ?? null);
        $this->assertSame('New Name', $updatedPost['post_title'] ?? null);
    }

    /** @test */
    public function a_matching_post_title_is_left_alone(): void
    {
        WP_Mock::userFunction('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')->willReturnOnConsecutiveCalls(
            $this->position('Old Name'),
            $this->position('New Name')
        );

        // post_title already matches the new long name — no write needed.
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['ID' => 9, 'post_title' => 'New Name']);

        $called = false;
        WP_Mock::userFunction('wp_update_post')->andReturnUsing(function () use (&$called): int {
            $called = true;

            return 9;
        });

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertFalse($called, 'An already-correct title should not be rewritten.');
    }
}
