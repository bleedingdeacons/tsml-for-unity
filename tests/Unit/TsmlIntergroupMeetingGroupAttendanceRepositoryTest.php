<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceRepository;
use TsmlForUnity\Tests\Support\FakeWpdb;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceRepository;

/*
 * Tests for TsmlIntergroupMeetingGroupAttendanceRepository.
 *
 * This repository builds SQL by hand against a custom table, so the tests
 * assert on the statements it produces rather than on a database result.
 * Two things matter most: that every documented filter turns into a bound
 * WHERE clause, and that `orderby` is whitelisted — it is interpolated
 * directly into the statement, so an unrecognised value must fall back to
 * `id` rather than reach the database.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceRepository::class);

beforeEach(function () {
    Functions\expect('esc_sql')->andReturnUsing(static fn ($v) => $v);

    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $this->wpdb = new FakeWpdb();
    $GLOBALS['wpdb'] = $this->wpdb;

    $this->factory = $this->createMock(TsmlIntergroupMeetingGroupAttendanceFactory::class);
    $this->repository = new TsmlIntergroupMeetingGroupAttendanceRepository($this->factory);
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

/** A stand-in attendance record with the getters save() reads. */
function groupAttendance(int $id = 0): IntergroupMeetingGroupAttendance
{
    $record = test()->createMock(IntergroupMeetingGroupAttendance::class);
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

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(IntergroupMeetingGroupAttendanceRepository::class);
});

// ─── findById ───────────────────────────────────────────────────
test('find by id hydrates the row through the factory', function () {
    $this->wpdb->row = ['id' => '5', 'group_id' => '10'];
    $hydrated = $this->createMock(TsmlIntergroupMeetingGroupAttendance::class);
    $this->factory->expects($this->once())
        ->method('hydrateFromRow')
        ->with(['id' => '5', 'group_id' => '10'])
        ->willReturn($hydrated);

    expect($this->repository->findById(5))->toBe($hydrated)
        ->and($this->wpdb->lastQuery())->toContain('WHERE id = 5');
});

test('find by id returns null when there is no row', function () {
    $this->wpdb->row = null;
    $this->factory->expects($this->never())->method('hydrateFromRow');

    expect($this->repository->findById(404))->toBeNull();
});

// ─── findAll ────────────────────────────────────────────────────
test('find all without filters selects everything ordered by id', function () {
    $this->wpdb->results = [];

    expect($this->repository->findAll())->toBe([]);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->not->toContain('WHERE')
        ->and($sql)->toContain('ORDER BY id ASC');
});

test('find all hydrates every row', function () {
    $this->wpdb->results = [['id' => '1'], ['id' => '2']];
    $this->factory->expects($this->exactly(2))
        ->method('hydrateFromRow')
        ->willReturn($this->createMock(TsmlIntergroupMeetingGroupAttendance::class));

    expect($this->repository->findAll())->toHaveCount(2);
});

test('find all returns an empty array when the query yields no rows', function () {
    $this->wpdb->results = [];

    expect($this->repository->findAll(['group_id' => 3]))->toBe([]);
});

test('find all turns each documented filter into a where clause', function (
    string $key,
    mixed $value,
    string $expected
) {
    $this->repository->findAll([$key => $value]);

    expect($this->wpdb->lastQuery())->toContain('WHERE')
        ->and($this->wpdb->lastQuery())->toContain($expected);
})->with([
    'intergroup meeting' => ['intergroup_meeting_id', 42, 'intergroup_meeting_id = 42'],
    'meeting label'      => ['meeting_label', 'July', "meeting_label = 'July'"],
    'group'              => ['group_id', 10, 'group_id = 10'],
    'member'             => ['member_id', 7, 'member_id = 7'],
    'meeting group'      => ['meeting_group', 'Tuesday', "meeting_group = 'Tuesday'"],
    'gsr name'           => ['gsr_name', 'Alex', "gsr_name = 'Alex'"],
]);

test('find all combines multiple filters with and', function () {
    $this->repository->findAll(['group_id' => 10, 'member_id' => 7]);

    expect($this->wpdb->lastQuery())->toContain('group_id = 10 AND member_id = 7');
});

test('find all accepts a whitelisted order column and direction', function () {
    $this->repository->findAll(['orderby' => 'gsr_name', 'order' => 'desc']);

    expect($this->wpdb->lastQuery())->toContain('ORDER BY gsr_name DESC');
});

test('find all falls back to id for an unrecognised order column', function () {
    // orderby is interpolated straight into the SQL, so anything outside
    // the whitelist must be discarded rather than passed through.
    $this->repository->findAll(['orderby' => 'id; DROP TABLE wp_posts']);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->toContain('ORDER BY id ASC')
        ->and($sql)->not->toContain('DROP TABLE');
});

test('find all applies limit and offset only when a positive number is given', function () {
    $this->repository->findAll(['number' => 5, 'offset' => 10]);
    expect($this->wpdb->lastQuery())->toContain('LIMIT 5 OFFSET 10');

    $this->repository->findAll(['number' => -1]);
    expect($this->wpdb->lastQuery())->not->toContain('LIMIT');
});

test('find all defaults the offset to zero', function () {
    $this->repository->findAll(['number' => 3]);

    expect($this->wpdb->lastQuery())->toContain('LIMIT 3 OFFSET 0');
});

test('find by intergroup meeting filters on the parent meeting', function () {
    $this->repository->findByIntergroupMeeting(99);

    expect($this->wpdb->lastQuery())->toContain('intergroup_meeting_id = 99');
});

// ─── count ──────────────────────────────────────────────────────
test('count returns the scalar from the database', function () {
    $this->wpdb->var = '17';

    expect($this->repository->count())->toBe(17)
        ->and($this->wpdb->lastQuery())->toContain('SELECT COUNT(*)');
});

test('count applies the same filters as find all', function () {
    $this->wpdb->var = '3';

    expect($this->repository->count([
        'intergroup_meeting_id' => 42,
        'meeting_label'         => 'July',
        'group_id'              => 10,
        'member_id'             => 7,
        'meeting_group'         => 'Tuesday',
        'gsr_name'              => 'Alex',
    ]))->toBe(3);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->toContain('intergroup_meeting_id = 42')
        ->and($sql)->toContain("gsr_name = 'Alex'");
});

// ─── save ───────────────────────────────────────────────────────
test('saving a new record inserts it', function () {
    expect($this->repository->save(groupAttendance(0)))->toBeTrue();

    expect($this->wpdb->inserts)->toHaveCount(1)
        ->and($this->wpdb->updates)->toBe([]);

    [$table, $data] = $this->wpdb->inserts[0];
    expect($table)->toContain('group_attendance')
        ->and($data['intergroup_meeting_id'])->toBe(42)
        ->and($data['gsr_name'])->toBe('Alex');
    // The proxy flag is stored as a tinyint, not a bool.
    expect($data['gsr_proxy'])->toBe(1);
});

test('saving an existing record updates it by id', function () {
    expect($this->repository->save(groupAttendance(5)))->toBeTrue();

    expect($this->wpdb->updates)->toHaveCount(1)
        ->and($this->wpdb->inserts)->toBe([])
        ->and($this->wpdb->updates[0][2])->toBe(['id' => 5]);
});

test('a failed insert is reported', function () {
    $this->wpdb->insertResult = false;

    expect($this->repository->save(groupAttendance(0)))->toBeFalse();
});

test('a failed update is reported', function () {
    $this->wpdb->updateResult = false;

    expect($this->repository->save(groupAttendance(5)))->toBeFalse();
});

// ─── delete ─────────────────────────────────────────────────────
test('delete removes the row by id', function () {
    expect($this->repository->delete(5))->toBeTrue();

    expect($this->wpdb->deletes[0][1])->toBe(['id' => 5]);
});

test('a failed delete is reported', function () {
    $this->wpdb->deleteResult = false;

    expect($this->repository->delete(5))->toBeFalse();
});

test('delete by meeting and member scopes to both', function () {
    expect($this->repository->deleteByIntergroupMeetingAndMember(42, 7))->toBeTrue();

    expect($this->wpdb->deletes[0][1])->toBe(['intergroup_meeting_id' => 42, 'member_id' => 7]);
});

test('delete by meeting and group scopes to both', function () {
    expect($this->repository->deleteByIntergroupMeetingAndGroup(42, 10))->toBeTrue();

    expect($this->wpdb->deletes[0][1])->toBe(['intergroup_meeting_id' => 42, 'group_id' => 10]);
});

test('a failed scoped delete is reported', function () {
    $this->wpdb->deleteResult = false;

    expect($this->repository->deleteByIntergroupMeetingAndMember(42, 7))->toBeFalse()
        ->and($this->repository->deleteByIntergroupMeetingAndGroup(42, 10))->toBeFalse();
});

// ─── existsForMeetingAndGroup ───────────────────────────────────
test('exists is true when the count is positive', function () {
    $this->wpdb->var = '1';

    expect($this->repository->existsForMeetingAndGroup(42, 10))->toBeTrue()
        ->and($this->wpdb->lastQuery())->toContain('intergroup_meeting_id = 42 AND group_id = 10');
});

test('exists is false when nothing matches', function () {
    $this->wpdb->var = '0';

    expect($this->repository->existsForMeetingAndGroup(42, 10))->toBeFalse();
});
