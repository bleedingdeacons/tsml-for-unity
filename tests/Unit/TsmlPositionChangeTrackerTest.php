<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionChangeTracker;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\Positions\Interfaces\PositionRepository;
use WP_Mock;

/**
 * Tests for TsmlPositionChangeTracker.
 *
 * Mirrors the member change tracker: captureOriginalPosition snapshots at
 * priority 1, checkForChanges diffs and dispatches at priority 20. These
 * pin the routing between unity/position_changing (a real change) and the
 * quiet path (no change), plus the guards that make both early-return.
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionChangeTracker
 */
class TsmlPositionChangeTrackerTest extends TestCase
{
    use ActionExpectations;

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

        $reflection = new \ReflectionClass(TsmlPositionChangeTracker::class);
        $reflection->getProperty('originalPosition')->setValue(null, null);

        parent::tearDown();
    }

    private function stubPostTypeGuard(int $postId): void
    {
        WP_Mock::userFunction('get_post_type')
            ->with($postId)
            ->andReturn(TsmlPositionFields::POST_TYPE);
    }

    private function stubTitleSyncIsNoop(int $postId, string $existingTitle): void
    {
        WP_Mock::userFunction('get_post')
            ->with($postId)
            ->andReturn((object) ['ID' => $postId, 'post_title' => $existingTitle]);
    }

    private function position(string $email = 'chair@example.com'): TsmlPosition
    {
        return new TsmlPosition(
            id: 42,
            minimumSobriety: 6,
            termYears: 1,
            email: $email,
            longName: 'Chair',
            shortDescription: 'Chairs',
            summary: 'Runs intergroup',
        );
    }

    /**
     * @test
     */
    public function editing_a_field_fires_position_changing(): void
    {
        $postId = 42;

        $original = $this->position('old@example.com');
        $updated  = $this->position('new@example.com');

        $this->stubPostTypeGuard($postId);
        // post_title already matches the encoded long name, so the title
        // sync does not call wp_update_post.
        $this->stubTitleSyncIsNoop($postId, 'Chair');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::expectAction('unity/position_before_save', $postId, $original);
        WP_Mock::expectAction('unity/position_changing', $updated, $original);
        WP_Mock::expectAction('unity/position_changed', $postId, $updated, $original);

        $this->tracker->captureOriginalPosition($postId);
        $this->tracker->checkForChanges($postId);
    }

    /**
     * @test
     */
    public function saving_with_no_field_changes_stays_quiet(): void
    {
        $postId = 42;

        $original = $this->position();
        $updated  = $this->position();

        $this->stubPostTypeGuard($postId);

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        // Only the catch-all "changed" event fires; "changing" stays silent.
        WP_Mock::expectAction('unity/position_changed', $postId, $updated, $original);
        $this->expectActionNotFired('unity/position_changing', $updated, $original);

        $this->tracker->captureOriginalPosition($postId);
        $this->tracker->checkForChanges($postId);
    }

    /**
     * @test
     */
    public function capture_ignores_a_non_position_post_type(): void
    {
        $postId = 99;
        WP_Mock::userFunction('get_post_type')->with($postId)->andReturn('page');

        // A wrong post type must not reach the repository.
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->captureOriginalPosition($postId);

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function check_for_changes_returns_early_without_a_captured_original(): void
    {
        $postId = 42;
        $this->stubPostTypeGuard($postId);

        // No capture happened, so findById must not be called by check.
        $this->repository->expects($this->never())->method('findById');

        $this->tracker->checkForChanges($postId);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @dataProvider changedFieldProvider
     */
    public function each_tracked_field_triggers_a_change(TsmlPosition $original, TsmlPosition $updated): void
    {
        $postId = 42;

        $this->stubPostTypeGuard($postId);
        $this->stubTitleSyncIsNoop($postId, 'Chair');

        $this->repository->expects($this->exactly(2))
            ->method('findById')
            ->with($postId)
            ->willReturnOnConsecutiveCalls($original, $updated);

        WP_Mock::expectAction('unity/position_changing', $updated, $original);

        $this->tracker->captureOriginalPosition($postId);
        $this->tracker->checkForChanges($postId);
    }

    /**
     * @return array<string, array{TsmlPosition, TsmlPosition}>
     */
    public function changedFieldProvider(): array
    {
        $base = fn (array $o = []) => new TsmlPosition(...array_merge([
            'id' => 42, 'minimumSobriety' => 6, 'termYears' => 1,
            'email' => 'chair@example.com', 'longName' => 'Chair',
            'shortDescription' => 'Chairs', 'summary' => 'Runs',
            'link' => 'https://example.com/c',
        ], $o));

        return [
            'sobriety'    => [$base(), $base(['minimumSobriety' => 12])],
            'term'        => [$base(), $base(['termYears' => 2])],
            'short desc'  => [$base(), $base(['shortDescription' => 'Different'])],
            'summary'     => [$base(), $base(['summary' => 'Different'])],
            'link'        => [$base(), $base(['link' => 'https://example.com/other'])],
        ];
    }
}
