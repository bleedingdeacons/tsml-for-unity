<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use function Brain\Monkey\Functions\expect;
use Exception;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Tests\TestCase;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;

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
 */
#[CoversClass(\TsmlForUnity\Positions\TsmlPositionChangeTracker::class)]
class TsmlPositionChangeTrackerFailureTest extends TestCase
{
    /** @var PositionRepository&MockObject */
    private $repository;

    private TsmlPositionChangeTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();


        $this->repository = $this->createMock(PositionRepository::class);
        $this->tracker = new TsmlPositionChangeTracker($this->repository);
    }

    protected function tearDown(): void
    {

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

    #[Test]
    public function capturing_a_post_of_another_type_is_ignored(): void
    {
        expect('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalPosition(9);

        $this->assertTrue(true, 'returned before reading the position');
    }

    #[Test]
    public function a_capture_failure_is_swallowed(): void
    {
        expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);
        $this->repository->method('findById')->willThrowException(new Exception('boom'));

        $this->tracker->captureOriginalPosition(9);

        $this->assertTrue(true, 'a failed capture must not abort the save');
    }

    #[Test]
    public function checking_a_post_of_another_type_is_ignored(): void
    {
        expect('get_post_type')->andReturn('page');
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'returned before comparing');
    }

    #[Test]
    public function a_check_that_cannot_reload_the_position_stops_quietly(): void
    {
        expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        // Capture succeeds; the reload afterwards comes back empty.
        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls($this->position(), null);

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'no event fired without an updated position');
    }

    #[Test]
    public function a_check_failure_is_swallowed(): void
    {
        expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')
            ->willReturnOnConsecutiveCalls(
                $this->position(),
                $this->throwException(new Exception('boom'))
            );

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertTrue(true, 'a failed check must not abort the save');
    }

    #[Test]
    public function a_renamed_position_has_its_post_title_synced(): void
    {
        expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')->willReturnOnConsecutiveCalls(
            $this->position('Old Name'),
            $this->position('New Name')
        );

        // The stored title still holds the old long name.
        expect('get_post')
            ->andReturn((object) ['ID' => 9, 'post_title' => 'Old Name']);

        $updatedPost = [];
        expect('wp_update_post')->andReturnUsing(
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

    #[Test]
    public function a_matching_post_title_is_left_alone(): void
    {
        expect('get_post_type')->andReturn(TsmlPositionFields::POST_TYPE);

        $this->repository->method('findById')->willReturnOnConsecutiveCalls(
            $this->position('Old Name'),
            $this->position('New Name')
        );

        // post_title already matches the new long name — no write needed.
        expect('get_post')
            ->andReturn((object) ['ID' => 9, 'post_title' => 'New Name']);

        $called = false;
        expect('wp_update_post')->andReturnUsing(function () use (&$called): int {
            $called = true;

            return 9;
        });

        $this->tracker->captureOriginalPosition(9);
        $this->tracker->checkForChanges(9);

        $this->assertFalse($called, 'An already-correct title should not be rewritten.');
    }
}
