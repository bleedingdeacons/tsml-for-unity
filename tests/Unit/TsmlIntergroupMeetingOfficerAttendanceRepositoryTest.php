<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceRepository;
use TsmlForUnity\Tests\Support\FakeWpdb;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceRepository;

/*
 * Tests for TsmlIntergroupMeetingOfficerAttendanceRepository.
 *
 * The officer register mirrors the group register but tracks who held which
 * position at a meeting, and adds updateByMeetingAndOfficer() for correcting
 * a record in place. As with the group repository the assertions are on the
 * SQL produced, including the orderby whitelist that guards a value
 * interpolated straight into the statement.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceRepository::class);

beforeEach(function () {
    Functions\expect('esc_sql')->andReturnUsing(static fn ($v) => $v);

    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $this->wpdb = new FakeWpdb();
    $GLOBALS['wpdb'] = $this->wpdb;

    $this->factory = $this->createMock(TsmlIntergroupMeetingOfficerAttendanceFactory::class);
    $this->repository = new TsmlIntergroupMeetingOfficerAttendanceRepository($this->factory);
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

function officerAttendance(int $id = 0): IntergroupMeetingOfficerAttendance
{
    $record = test()->createMock(IntergroupMeetingOfficerAttendance::class);
    $record->method('getId')->willReturn($id);
    $record->method('getIntergroupMeetingId')->willReturn(42);
    $record->method('getMeetingLabel')->willReturn('July 2026');
    $record->method('getOfficerId')->willReturn(9);
    $record->method('getPositionName')->willReturn('Treasurer');
    $record->method('getOfficerName')->willReturn('Jo');

    return $record;
}

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(IntergroupMeetingOfficerAttendanceRepository::class);
});

// ─── findById ───────────────────────────────────────────────────
test('find by id hydrates the row through the factory', function () {
    $this->wpdb->row = ['id' => '5', 'officer_id' => '9'];
    $hydrated = $this->createMock(TsmlIntergroupMeetingOfficerAttendance::class);
    $this->factory->expects($this->once())
        ->method('hydrateFromRow')
        ->with(['id' => '5', 'officer_id' => '9'])
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
    $sql = '';
    expect($this->repository->findAll())->toBe([]);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->not->toContain('WHERE')
        ->and($sql)->toContain('ORDER BY id ASC');
});

test('find all hydrates every row', function () {
    $this->wpdb->results = [['id' => '1'], ['id' => '2'], ['id' => '3']];
    $this->factory->expects($this->exactly(3))
        ->method('hydrateFromRow')
        ->willReturn($this->createMock(TsmlIntergroupMeetingOfficerAttendance::class));

    expect($this->repository->findAll())->toHaveCount(3);
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
    'officer'            => ['officer_id', 9, 'officer_id = 9'],
    'position name'      => ['position_name', 'Treasurer', "position_name = 'Treasurer'"],
    'officer name'       => ['officer_name', 'Jo', "officer_name = 'Jo'"],
]);

test('find all combines multiple filters with and', function () {
    $this->repository->findAll(['officer_id' => 9, 'position_name' => 'Treasurer']);

    expect($this->wpdb->lastQuery())->toContain("officer_id = 9 AND position_name = 'Treasurer'");
});

test('find all accepts a whitelisted order column and direction', function () {
    $this->repository->findAll(['orderby' => 'officer_name', 'order' => 'desc']);

    expect($this->wpdb->lastQuery())->toContain('ORDER BY officer_name DESC');
});

test('find all falls back to id for an unrecognised order column', function () {
    $this->repository->findAll(['orderby' => 'officer_id; DELETE FROM wp_posts']);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->toContain('ORDER BY id ASC')
        ->and($sql)->not->toContain('DELETE FROM');
});

test('find all applies limit and offset', function () {
    $this->repository->findAll(['number' => 5, 'offset' => 10]);
    expect($this->wpdb->lastQuery())->toContain('LIMIT 5 OFFSET 10');

    $this->repository->findAll(['number' => 2]);
    expect($this->wpdb->lastQuery())->toContain('LIMIT 2 OFFSET 0');

    $this->repository->findAll();
    expect($this->wpdb->lastQuery())->not->toContain('LIMIT');
});

test('find by intergroup meeting filters on the parent meeting', function () {
    $this->repository->findByIntergroupMeeting(99);

    expect($this->wpdb->lastQuery())->toContain('intergroup_meeting_id = 99');
});

// ─── count ──────────────────────────────────────────────────────
test('count returns the scalar from the database', function () {
    $this->wpdb->var = '4';

    expect($this->repository->count())->toBe(4)
        ->and($this->wpdb->lastQuery())->toContain('SELECT COUNT(*)');
});

test('count applies the same filters as find all', function () {
    $this->wpdb->var = '2';

    expect($this->repository->count([
        'intergroup_meeting_id' => 42,
        'meeting_label'         => 'July',
        'officer_id'            => 9,
        'position_name'         => 'Treasurer',
        'officer_name'          => 'Jo',
    ]))->toBe(2);

    $sql = $this->wpdb->lastQuery();
    expect($sql)->toContain('intergroup_meeting_id = 42')
        ->and($sql)->toContain("officer_name = 'Jo'");
});

// ─── save ───────────────────────────────────────────────────────
test('saving a new record inserts it', function () {
    expect($this->repository->save(officerAttendance(0)))->toBeTrue();

    expect($this->wpdb->inserts)->toHaveCount(1)
        ->and($this->wpdb->updates)->toBe([]);

    [, $data] = $this->wpdb->inserts[0];
    expect($data['intergroup_meeting_id'])->toBe(42)
        ->and($data['position_name'])->toBe('Treasurer')
        ->and($data['officer_name'])->toBe('Jo');
});

test('saving an existing record updates it by id', function () {
    expect($this->repository->save(officerAttendance(5)))->toBeTrue();

    expect($this->wpdb->updates)->toHaveCount(1)
        ->and($this->wpdb->inserts)->toBe([])
        ->and($this->wpdb->updates[0][2])->toBe(['id' => 5]);
});

test('a failed write is reported', function () {
    $this->wpdb->insertResult = false;
    expect($this->repository->save(officerAttendance(0)))->toBeFalse();

    $this->wpdb->updateResult = false;
    expect($this->repository->save(officerAttendance(5)))->toBeFalse();
});

// ─── update by meeting and officer ──────────────────────────────
test('update by meeting and officer scopes the update to both', function () {
    $this->wpdb->updateResult = 1;

    expect($this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'))->toBe(1);

    [, $data, $where] = $this->wpdb->updates[0];
    expect($data)->toBe(['position_name' => 'Chair', 'officer_name' => 'Robin'])
        ->and($where)->toBe(['intergroup_meeting_id' => 42, 'officer_id' => 9]);
});

test('update by meeting and officer reports zero when the write fails', function () {
    $this->wpdb->updateResult = false;

    expect($this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'))->toBe(0);
});

test('update by meeting and officer returns the affected row count', function () {
    $this->wpdb->updateResult = 3;

    expect($this->repository->updateByMeetingAndOfficer(42, 9, 'Chair', 'Robin'))->toBe(3);
});

// ─── delete ─────────────────────────────────────────────────────
test('delete removes the row by id', function () {
    expect($this->repository->delete(5))->toBeTrue();

    expect($this->wpdb->deletes[0][1])->toBe(['id' => 5]);
});

test('delete by meeting and officer scopes to both', function () {
    expect($this->repository->deleteByIntergroupMeetingAndOfficer(42, 9))->toBeTrue();

    expect($this->wpdb->deletes[0][1])->toBe(['intergroup_meeting_id' => 42, 'officer_id' => 9]);
});

test('a failed delete is reported', function () {
    $this->wpdb->deleteResult = false;

    expect($this->repository->delete(5))->toBeFalse()
        ->and($this->repository->deleteByIntergroupMeetingAndOfficer(42, 9))->toBeFalse();
});

// ─── existsForMeetingAndOfficer ─────────────────────────────────
test('exists is true when the count is positive', function () {
    $this->wpdb->var = '1';

    expect($this->repository->existsForMeetingAndOfficer(42, 9))->toBeTrue()
        ->and($this->wpdb->lastQuery())->toContain('intergroup_meeting_id = 42 AND officer_id = 9');
});

test('exists is false when nothing matches', function () {
    $this->wpdb->var = '0';

    expect($this->repository->existsForMeetingAndOfficer(42, 9))->toBeFalse();
});
