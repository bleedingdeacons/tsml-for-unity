<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceRepository;
use TsmlForUnity\Tests\Support\FakeWpdb;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceRepository;
use WP_Mock;

/**
 * Tests for TsmlIntergroupMeetingGroupAttendanceRepository.
 *
 * This repository builds SQL by hand against a custom table, so the tests
 * assert on the statements it produces rather than on a database result.
 * Two things matter most: that every documented filter turns into a bound
 * WHERE clause, and that `orderby` is whitelisted — it is interpolated
 * directly into the statement, so an unrecognised value must fall back to
 * `id` rather than reach the database.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceRepository
 */
class TsmlIntergroupMeetingGroupAttendanceRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;
    private $previousWpdb;

    /** @var TsmlIntergroupMeetingGroupAttendanceFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlIntergroupMeetingGroupAttendanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        WP_Mock::userFunction('esc_sql')->andReturnUsing(static fn ($v) => $v);

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->factory = $this->createMock(TsmlIntergroupMeetingGroupAttendanceFactory::class);
        $this->repository = new TsmlIntergroupMeetingGroupAttendanceRepository($this->factory);
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /** A stand-in attendance record with the getters save() reads. */
    private function attendance(int $id = 0): IntergroupMeetingGroupAttendance
    {
        $record = $this->createMock(IntergroupMeetingGroupAttendance::class);
        $record->method('getId')->willReturn($id);
        $record->method('getIntergroupMeetingId')->willReturn(42);
        $record->method('getMeetingLabel')->willReturn('July 2026');
        $record->method('getGroupId')->willReturn(10);
        $record->method('getMemberId')->willReturn(7);
        $record->method('getMeetingGroup')->willReturn('Tuesday Group');
        $record->method('getGsrName')->willReturn('Alex');
        $record->method('isGsrProxy')->willReturn(true);
        $record->method('getGsrProxyName')->willReturn('Sam');

        return $record;
    }

    /** @test */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingGroupAttendanceRepository::class, $this->repository);
    }

    // ─── findById ───────────────────────────────────────────────────

    /** @test */
    public function find_by_id_hydrates_the_row_through_the_factory(): void
    {
        $this->wpdb->row = ['id' => '5', 'group_id' => '10'];
        $hydrated = $this->createMock(TsmlIntergroupMeetingGroupAttendance::class);
        $this->factory->expects($this->once())
            ->method('hydrateFromRow')
            ->with(['id' => '5', 'group_id' => '10'])
            ->willReturn($hydrated);

        $this->assertSame($hydrated, $this->repository->findById(5));
        $this->assertStringContainsString('WHERE id = 5', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_by_id_returns_null_when_there_is_no_row(): void
    {
        $this->wpdb->row = null;
        $this->factory->expects($this->never())->method('hydrateFromRow');

        $this->assertNull($this->repository->findById(404));
    }

    // ─── findAll ────────────────────────────────────────────────────

    /** @test */
    public function find_all_without_filters_selects_everything_ordered_by_id(): void
    {
        $this->wpdb->results = [];

        $this->assertSame([], $this->repository->findAll());

        $sql = $this->wpdb->lastQuery();
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY id ASC', $sql);
    }

    /** @test */
    public function find_all_hydrates_every_row(): void
    {
        $this->wpdb->results = [['id' => '1'], ['id' => '2']];
        $this->factory->expects($this->exactly(2))
            ->method('hydrateFromRow')
            ->willReturn($this->createMock(TsmlIntergroupMeetingGroupAttendance::class));

        $this->assertCount(2, $this->repository->findAll());
    }

    /** @test */
    public function find_all_returns_an_empty_array_when_the_query_yields_no_rows(): void
    {
        $this->wpdb->results = [];

        $this->assertSame([], $this->repository->findAll(['group_id' => 3]));
    }

    /**
     * @test
     * @dataProvider filterProvider
     */
    public function find_all_turns_each_documented_filter_into_a_where_clause(
        string $key,
        mixed $value,
        string $expected
    ): void {
        $this->repository->findAll([$key => $value]);

        $this->assertStringContainsString('WHERE', $this->wpdb->lastQuery());
        $this->assertStringContainsString($expected, $this->wpdb->lastQuery());
    }

    /** @return array<string, array{0: string, 1: mixed, 2: string}> */
    public static function filterProvider(): array
    {
        return [
            'intergroup meeting' => ['intergroup_meeting_id', 42, 'intergroup_meeting_id = 42'],
            'meeting label'      => ['meeting_label', 'July', "meeting_label = 'July'"],
            'group'              => ['group_id', 10, 'group_id = 10'],
            'member'             => ['member_id', 7, 'member_id = 7'],
            'meeting group'      => ['meeting_group', 'Tuesday', "meeting_group = 'Tuesday'"],
            'gsr name'           => ['gsr_name', 'Alex', "gsr_name = 'Alex'"],
        ];
    }

    /** @test */
    public function find_all_combines_multiple_filters_with_and(): void
    {
        $this->repository->findAll(['group_id' => 10, 'member_id' => 7]);

        $this->assertStringContainsString('group_id = 10 AND member_id = 7', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_all_accepts_a_whitelisted_order_column_and_direction(): void
    {
        $this->repository->findAll(['orderby' => 'gsr_name', 'order' => 'desc']);

        $this->assertStringContainsString('ORDER BY gsr_name DESC', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_all_falls_back_to_id_for_an_unrecognised_order_column(): void
    {
        // orderby is interpolated straight into the SQL, so anything outside
        // the whitelist must be discarded rather than passed through.
        $this->repository->findAll(['orderby' => 'id; DROP TABLE wp_posts']);

        $sql = $this->wpdb->lastQuery();
        $this->assertStringContainsString('ORDER BY id ASC', $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql);
    }

    /** @test */
    public function find_all_applies_limit_and_offset_only_when_a_positive_number_is_given(): void
    {
        $this->repository->findAll(['number' => 5, 'offset' => 10]);
        $this->assertStringContainsString('LIMIT 5 OFFSET 10', $this->wpdb->lastQuery());

        $this->repository->findAll(['number' => -1]);
        $this->assertStringNotContainsString('LIMIT', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_all_defaults_the_offset_to_zero(): void
    {
        $this->repository->findAll(['number' => 3]);

        $this->assertStringContainsString('LIMIT 3 OFFSET 0', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_by_intergroup_meeting_filters_on_the_parent_meeting(): void
    {
        $this->repository->findByIntergroupMeeting(99);

        $this->assertStringContainsString('intergroup_meeting_id = 99', $this->wpdb->lastQuery());
    }

    // ─── count ──────────────────────────────────────────────────────

    /** @test */
    public function count_returns_the_scalar_from_the_database(): void
    {
        $this->wpdb->var = '17';

        $this->assertSame(17, $this->repository->count());
        $this->assertStringContainsString('SELECT COUNT(*)', $this->wpdb->lastQuery());
    }

    /** @test */
    public function count_applies_the_same_filters_as_find_all(): void
    {
        $this->wpdb->var = '3';

        $this->assertSame(3, $this->repository->count([
            'intergroup_meeting_id' => 42,
            'meeting_label'         => 'July',
            'group_id'              => 10,
            'member_id'             => 7,
            'meeting_group'         => 'Tuesday',
            'gsr_name'              => 'Alex',
        ]));

        $sql = $this->wpdb->lastQuery();
        $this->assertStringContainsString('intergroup_meeting_id = 42', $sql);
        $this->assertStringContainsString("gsr_name = 'Alex'", $sql);
    }

    // ─── save ───────────────────────────────────────────────────────

    /** @test */
    public function saving_a_new_record_inserts_it(): void
    {
        $this->assertTrue($this->repository->save($this->attendance(0)));

        $this->assertCount(1, $this->wpdb->inserts);
        $this->assertSame([], $this->wpdb->updates);

        [$table, $data] = $this->wpdb->inserts[0];
        $this->assertStringContainsString('group_attendance', $table);
        $this->assertSame(42, $data['intergroup_meeting_id']);
        $this->assertSame('Alex', $data['gsr_name']);
        // The proxy flag is stored as a tinyint, not a bool.
        $this->assertSame(1, $data['gsr_proxy']);
    }

    /** @test */
    public function saving_an_existing_record_updates_it_by_id(): void
    {
        $this->assertTrue($this->repository->save($this->attendance(5)));

        $this->assertCount(1, $this->wpdb->updates);
        $this->assertSame([], $this->wpdb->inserts);
        $this->assertSame(['id' => 5], $this->wpdb->updates[0][2]);
    }

    /** @test */
    public function a_failed_insert_is_reported(): void
    {
        $this->wpdb->insertResult = false;

        $this->assertFalse($this->repository->save($this->attendance(0)));
    }

    /** @test */
    public function a_failed_update_is_reported(): void
    {
        $this->wpdb->updateResult = false;

        $this->assertFalse($this->repository->save($this->attendance(5)));
    }

    // ─── delete ─────────────────────────────────────────────────────

    /** @test */
    public function delete_removes_the_row_by_id(): void
    {
        $this->assertTrue($this->repository->delete(5));

        $this->assertSame(['id' => 5], $this->wpdb->deletes[0][1]);
    }

    /** @test */
    public function a_failed_delete_is_reported(): void
    {
        $this->wpdb->deleteResult = false;

        $this->assertFalse($this->repository->delete(5));
    }

    /** @test */
    public function delete_by_meeting_and_member_scopes_to_both(): void
    {
        $this->assertTrue($this->repository->deleteByIntergroupMeetingAndMember(42, 7));

        $this->assertSame(
            ['intergroup_meeting_id' => 42, 'member_id' => 7],
            $this->wpdb->deletes[0][1]
        );
    }

    /** @test */
    public function delete_by_meeting_and_group_scopes_to_both(): void
    {
        $this->assertTrue($this->repository->deleteByIntergroupMeetingAndGroup(42, 10));

        $this->assertSame(
            ['intergroup_meeting_id' => 42, 'group_id' => 10],
            $this->wpdb->deletes[0][1]
        );
    }

    /** @test */
    public function a_failed_scoped_delete_is_reported(): void
    {
        $this->wpdb->deleteResult = false;

        $this->assertFalse($this->repository->deleteByIntergroupMeetingAndMember(42, 7));
        $this->assertFalse($this->repository->deleteByIntergroupMeetingAndGroup(42, 10));
    }

    // ─── existsForMeetingAndGroup ───────────────────────────────────

    /** @test */
    public function exists_is_true_when_the_count_is_positive(): void
    {
        $this->wpdb->var = '1';

        $this->assertTrue($this->repository->existsForMeetingAndGroup(42, 10));
        $this->assertStringContainsString('intergroup_meeting_id = 42 AND group_id = 10', $this->wpdb->lastQuery());
    }

    /** @test */
    public function exists_is_false_when_nothing_matches(): void
    {
        $this->wpdb->var = '0';

        $this->assertFalse($this->repository->existsForMeetingAndGroup(42, 10));
    }
}
