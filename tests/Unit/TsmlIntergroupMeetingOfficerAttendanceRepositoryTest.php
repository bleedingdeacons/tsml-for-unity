<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceRepository;
use TsmlForUnity\Tests\Support\FakeWpdb;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceRepository;
use WP_Mock;

/**
 * Tests for TsmlIntergroupMeetingOfficerAttendanceRepository.
 *
 * The officer register mirrors the group register but tracks who held which
 * position at a meeting, and adds updateByMeetingAndOfficer() for correcting
 * a record in place. As with the group repository the assertions are on the
 * SQL produced, including the orderby whitelist that guards a value
 * interpolated straight into the statement.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceRepository
 */
class TsmlIntergroupMeetingOfficerAttendanceRepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;
    private $previousWpdb;

    /** @var TsmlIntergroupMeetingOfficerAttendanceFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlIntergroupMeetingOfficerAttendanceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        WP_Mock::userFunction('esc_sql')->andReturnUsing(static fn ($v) => $v);

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->factory = $this->createMock(TsmlIntergroupMeetingOfficerAttendanceFactory::class);
        $this->repository = new TsmlIntergroupMeetingOfficerAttendanceRepository($this->factory);
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function attendance(int $id = 0): IntergroupMeetingOfficerAttendance
    {
        $record = $this->createMock(IntergroupMeetingOfficerAttendance::class);
        $record->method('getId')->willReturn($id);
        $record->method('getIntergroupMeetingId')->willReturn(42);
        $record->method('getMeetingLabel')->willReturn('July 2026');
        $record->method('getOfficerId')->willReturn(9);
        $record->method('getPositionName')->willReturn('Treasurer');
        $record->method('getOfficerName')->willReturn('Jo');

        return $record;
    }

    /** @test */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingOfficerAttendanceRepository::class, $this->repository);
    }

    // ─── findById ───────────────────────────────────────────────────

    /** @test */
    public function find_by_id_hydrates_the_row_through_the_factory(): void
    {
        $this->wpdb->row = ['id' => '5', 'officer_id' => '9'];
        $hydrated = $this->createMock(TsmlIntergroupMeetingOfficerAttendance::class);
        $this->factory->expects($this->once())
            ->method('hydrateFromRow')
            ->with(['id' => '5', 'officer_id' => '9'])
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
        $sql = '';
        $this->assertSame([], $this->repository->findAll());

        $sql = $this->wpdb->lastQuery();
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY id ASC', $sql);
    }

    /** @test */
    public function find_all_hydrates_every_row(): void
    {
        $this->wpdb->results = [['id' => '1'], ['id' => '2'], ['id' => '3']];
        $this->factory->expects($this->exactly(3))
            ->method('hydrateFromRow')
            ->willReturn($this->createMock(TsmlIntergroupMeetingOfficerAttendance::class));

        $this->assertCount(3, $this->repository->findAll());
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
            'officer'            => ['officer_id', 9, 'officer_id = 9'],
            'position name'      => ['position_name', 'Treasurer', "position_name = 'Treasurer'"],
            'officer name'       => ['officer_name', 'Jo', "officer_name = 'Jo'"],
        ];
    }

    /** @test */
    public function find_all_combines_multiple_filters_with_and(): void
    {
        $this->repository->findAll(['officer_id' => 9, 'position_name' => 'Treasurer']);

        $this->assertStringContainsString("officer_id = 9 AND position_name = 'Treasurer'", $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_all_accepts_a_whitelisted_order_column_and_direction(): void
    {
        $this->repository->findAll(['orderby' => 'officer_name', 'order' => 'desc']);

        $this->assertStringContainsString('ORDER BY officer_name DESC', $this->wpdb->lastQuery());
    }

    /** @test */
    public function find_all_falls_back_to_id_for_an_unrecognised_order_column(): void
    {
        $this->repository->findAll(['orderby' => 'officer_id; DELETE FROM wp_posts']);

        $sql = $this->wpdb->lastQuery();
        $this->assertStringContainsString('ORDER BY id ASC', $sql);
        $this->assertStringNotContainsString('DELETE FROM', $sql);
    }

    /** @test */
    public function find_all_applies_limit_and_offset(): void
    {
        $this->repository->findAll(['number' => 5, 'offset' => 10]);
        $this->assertStringContainsString('LIMIT 5 OFFSET 10', $this->wpdb->lastQuery());

        $this->repository->findAll(['number' => 2]);
        $this->assertStringContainsString('LIMIT 2 OFFSET 0', $this->wpdb->lastQuery());

        $this->repository->findAll();
        $this->assertStringNotContainsString('LIMIT', $this->wpdb->lastQuery());
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
        $this->wpdb->var = '4';

        $this->assertSame(4, $this->repository->count());
        $this->assertStringContainsString('SELECT COUNT(*)', $this->wpdb->lastQuery());
    }

    /** @test */
    public function count_applies_the_same_filters_as_find_all(): void
    {
        $this->wpdb->var = '2';

        $this->assertSame(2, $this->repository->count([
            'intergroup_meeting_id' => 42,
            'meeting_label'         => 'July',
            'officer_id'            => 9,
            'position_name'         => 'Treasurer',
            'officer_name'          => 'Jo',
        ]));

        $sql = $this->wpdb->lastQuery();
        $this->assertStringContainsString('intergroup_meeting_id = 42', $sql);
        $this->assertStringContainsString("officer_name = 'Jo'", $sql);
    }

    // ─── save ───────────────────────────────────────────────────────

    /** @test */
    public function saving_a_new_record_inserts_it(): void
    {
        $this->assertTrue($this->repository->save($this->attendance(0)));

        $this->assertCount(1, $this->wpdb->inserts);
        $this->assertSame([], $this->wpdb->updates);

        [, $data] = $this->wpdb->inserts[0];
        $this->assertSame(42, $data['intergroup_meeting_id']);
        $this->assertSame('Treasurer', $data['position_name']);
        $this->assertSame('Jo', $data['officer_name']);
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
    public function a_failed_write_is_reported(): void
    {
        $this->wpdb->insertResult = false;
        $this->assertFalse($this->repository->save($this->attendance(0)));

        $this->wpdb->updateResult = false;
        $this->assertFalse($this->repository->save($this->attendance(5)));
    }

    // ─── update by meeting and officer ──────────────────────────────

    /** @test */
    public function update_by_meeting_and_officer_scopes_the_update_to_both(): void
    {
        $this->wpdb->updateResult = 1;

        $this->assertSame(1, $this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'));

        [, $data, $where] = $this->wpdb->updates[0];
        $this->assertSame(['position_name' => 'Chair', 'officer_name' => 'Robin'], $data);
        $this->assertSame(['intergroup_meeting_id' => 42, 'officer_id' => 9], $where);
    }

    /** @test */
    public function update_by_meeting_and_officer_reports_zero_when_the_write_fails(): void
    {
        $this->wpdb->updateResult = false;

        $this->assertSame(0, $this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'));
    }

    /** @test */
    public function update_by_meeting_and_officer_returns_the_affected_row_count(): void
    {
        $this->wpdb->updateResult = 3;

        $this->assertSame(3, $this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'));
    }

    // ─── delete ─────────────────────────────────────────────────────

    /** @test */
    public function delete_removes_the_row_by_id(): void
    {
        $this->assertTrue($this->repository->delete(5));

        $this->assertSame(['id' => 5], $this->wpdb->deletes[0][1]);
    }

    /** @test */
    public function delete_by_meeting_and_officer_scopes_to_both(): void
    {
        $this->assertTrue($this->repository->deleteByIntergroupMeetingAndOfficer(42, 9));

        $this->assertSame(
            ['intergroup_meeting_id' => 42, 'officer_id' => 9],
            $this->wpdb->deletes[0][1]
        );
    }

    /** @test */
    public function a_failed_delete_is_reported(): void
    {
        $this->wpdb->deleteResult = false;

        $this->assertFalse($this->repository->delete(5));
        $this->assertFalse($this->repository->deleteByIntergroupMeetingAndOfficer(42, 9));
    }

    // ─── existsForMeetingAndOfficer ─────────────────────────────────

    /** @test */
    public function exists_is_true_when_the_count_is_positive(): void
    {
        $this->wpdb->var = '1';

        $this->assertTrue($this->repository->existsForMeetingAndOfficer(42, 9));
        $this->assertStringContainsString('intergroup_meeting_id = 42 AND officer_id = 9', $this->wpdb->lastQuery());
    }

    /** @test */
    public function exists_is_false_when_nothing_matches(): void
    {
        $this->wpdb->var = '0';

        $this->assertFalse($this->repository->existsForMeetingAndOfficer(42, 9));
    }
}
