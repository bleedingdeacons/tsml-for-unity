<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Groups\TsmlGroupRepository;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Meetings\Interfaces\Meeting;
use WP_Mock;

/**
 * Tests for TsmlGroupRepository's write paths.
 *
 * The central contract here is the MEETING field: it stores meeting IDs,
 * but Group exposes Meeting objects via getMeetings(). Both save() (insert)
 * and update() have to bridge that gap. These tests pin the translation
 * down in both paths, because a regression there is silent — update_field()
 * accepts whatever it is handed.
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupRepository
 */
class TsmlGroupRepositoryTest extends TestCase
{
    /** @var GroupFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlGroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(GroupFactory::class);
        $this->repository = new TsmlGroupRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Helper: a Meeting that knows only its ID — the sole property the
     * repository reads when building the MEETING field.
     *
     * @return Meeting&\PHPUnit\Framework\MockObject\MockObject
     */
    private function meetingWithId(int $id)
    {
        $meeting = $this->createMock(Meeting::class);
        $meeting->method('getId')->willReturn($id);

        return $meeting;
    }

    /**
     * Helper: a Group carrying the given Meeting objects.
     *
     * isValid() is set independently of the ID rather than mirroring
     * TsmlGroup's own rule ($id > 0 && title). The repository is typed
     * against the Group interface, where the two are separate parts of
     * the contract, and coupling them here would make the insert path
     * untestable — see the note on save_insert_writes_meeting_ids().
     *
     * @param Meeting[] $meetings
     * @return Group&\PHPUnit\Framework\MockObject\MockObject
     */
    private function groupWith(int $id, array $meetings, bool $valid = true, string $title = 'Tuesday Big Book')
    {
        $group = $this->createMock(Group::class);
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
    private function captureUpdateField(string $field, &$captured): void
    {
        WP_Mock::userFunction('update_field')
            ->withArgs(function ($key) use ($field) {
                return $key === $field;
            })
            ->andReturnUsing(function ($key, $value) use (&$captured) {
                $captured = $value;
                return true;
            });

        // The other fields in the same save are not under test.
        WP_Mock::userFunction('update_field')->andReturn(true);
    }

    // ─── save() insert path writes meeting IDs ──────────────────────

    /**
     * Reaching the insert branch needs getId() === 0 (or save() delegates
     * to update()) *and* isValid() === true. TsmlGroup cannot satisfy both
     * at once — its isValid() requires id > 0 — so this covers the Group
     * contract the repository is typed against, not TsmlGroup specifically.
     *
     * @test
     */
    public function save_insert_writes_meeting_ids_not_meeting_objects(): void
    {
        $newPostId = 4242;

        // id = 0 => insert path.
        $group = $this->groupWith(0, [
            $this->meetingWithId(200),
            $this->meetingWithId(201),
            $this->meetingWithId(202),
        ]);

        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($newPostId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlGroupFields::MEETING, $written);

        $result = $this->repository->save($group);

        $this->assertTrue($result);
        $this->assertSame([200, 201, 202], $written);
    }

    /**
     * @test
     */
    public function save_insert_writes_empty_array_when_group_has_no_meetings(): void
    {
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn(4242);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlGroupFields::MEETING, $written);

        $this->assertTrue($this->repository->save($this->groupWith(0, [])));
        $this->assertSame([], $written);
    }

    /**
     * @test
     */
    public function save_returns_false_for_invalid_group_without_inserting(): void
    {
        // No wp_insert_post expectation: a call would fail the test.
        // This is the branch a real TsmlGroup with id = 0 always takes,
        // because its isValid() requires id > 0.
        $group = $this->groupWith(0, [$this->meetingWithId(200)], false);

        $this->assertFalse($this->repository->save($group));
    }

    /**
     * @test
     */
    public function save_returns_false_when_wp_insert_post_fails(): void
    {
        $group = $this->groupWith(0, [$this->meetingWithId(200)]);

        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field expectation: the failure returns before any writes.

        $this->assertFalse($this->repository->save($group));
    }

    // ─── update() path writes meeting IDs ───────────────────────────

    /**
     * @test
     */
    public function update_writes_meeting_ids_not_meeting_objects(): void
    {
        $postId = 4242;

        $group = $this->groupWith($postId, [
            $this->meetingWithId(300),
            $this->meetingWithId(301),
        ]);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlGroupFields::MEETING, $written);

        $result = $this->repository->update($group);

        $this->assertTrue($result);
        $this->assertSame([300, 301], $written);
    }

    /**
     * @test
     */
    public function save_with_existing_id_delegates_to_update_and_writes_meeting_ids(): void
    {
        // save() with id > 0 must delegate to update() — observed via
        // wp_update_post being used rather than wp_insert_post.
        $postId = 4242;

        $group = $this->groupWith($postId, [$this->meetingWithId(300)]);

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlGroupFields::MEETING, $written);

        $this->assertTrue($this->repository->save($group));
        $this->assertSame([300], $written);
    }

    /**
     * @test
     */
    public function update_returns_false_for_zero_post_id_without_writing(): void
    {
        // Zero ID never reaches wp_update_post or update_field.
        $group = $this->groupWith(0, [$this->meetingWithId(300)]);

        $this->assertFalse($this->repository->update($group));
    }

    /**
     * @test
     */
    public function update_returns_false_when_wp_update_post_fails(): void
    {
        $postId = 4242;

        $group = $this->groupWith($postId, [$this->meetingWithId(300)]);

        $error = new \stdClass();
        WP_Mock::userFunction('wp_update_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        $this->assertFalse($this->repository->update($group));
    }
}
