<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable;
use TsmlForUnity\Tests\Support\FakeWpdb;

/*
 * Tests for the custom attendance tables' install/upgrade lifecycle.
 *
 * Both registers live in their own tables, created through dbDelta() and
 * gated on a stored schema version. The behaviour worth pinning is the
 * gate: maybeUpgrade() must run the DDL when the recorded version differs
 * and stay out of the way when it matches, because it is called on every
 * load and an unguarded dbDelta() on each request would be expensive.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::class, \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::class);

beforeEach(function () {
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $this->wpdb = new FakeWpdb();
    $GLOBALS['wpdb'] = $this->wpdb;

    $GLOBALS['tsml_test_dbdelta'] = [];
    $this->storedOptions = [];

    Functions\expect('esc_sql')->andReturnUsing(static fn ($v) => $v);
    Functions\expect('get_option')
        ->andReturnUsing(fn (string $name, $default = false) => $this->storedOptions[$name] ?? $default);
    Functions\expect('update_option')
        ->andReturnUsing(function (string $name, $value): bool {
            $this->storedOptions[$name] = $value;

            return true;
        });
    Functions\expect('delete_option')
        ->andReturnUsing(function (string $name): bool {
            unset($this->storedOptions[$name]);

            return true;
        });
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

/** The SQL dbDelta was last handed. */
function attendanceLastDdl(): string
{
    $calls = $GLOBALS['tsml_test_dbdelta'] ?? [];

    return $calls === [] ? '' : (string) end($calls);
}

// ══ group attendance table ════════════════════════════════════════
test('the group table name is prefixed', function () {
    expect(TsmlIntergroupMeetingGroupAttendanceTable::getTableName())->toStartWith('wp_');
});

test('creating the group table issues ddl and records the version', function () {
    TsmlIntergroupMeetingGroupAttendanceTable::createTable();

    $ddl = attendanceLastDdl();
    expect($ddl)->toContain('CREATE TABLE')
        ->and($ddl)->toContain('intergroup_meeting_id')
        ->and($ddl)->toContain('group_id');
    // A group may only appear once per meeting.
    expect($ddl)->toContain('UNIQUE KEY');

    expect($this->storedOptions)->not->toBeEmpty('The schema version should be stored.');
});

test('dropping the group table removes the table and its version', function () {
    TsmlIntergroupMeetingGroupAttendanceTable::createTable();
    TsmlIntergroupMeetingGroupAttendanceTable::dropTable();

    expect($this->wpdb->lastQuery())->toContain('DROP TABLE IF EXISTS')
        ->and($this->storedOptions)->toBe([], 'The stored version should be cleared.');
});

test('the group table is created when no version is recorded', function () {
    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

    expect(attendanceLastDdl())->toContain('CREATE TABLE');
});

test('the group table upgrade is skipped when the version matches', function () {
    // First call installs and records the version.
    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
    $GLOBALS['tsml_test_dbdelta'] = [];

    // Second call sees a matching version and should do nothing.
    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

    expect($GLOBALS['tsml_test_dbdelta'])->toBe([], 'dbDelta should not run again.');
});

test('a stale group table version triggers an upgrade', function () {
    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
    // Pretend an older release installed the table.
    foreach (array_keys($this->storedOptions) as $key) {
        $this->storedOptions[$key] = '0.0.1';
    }
    $GLOBALS['tsml_test_dbdelta'] = [];

    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

    expect(attendanceLastDdl())->toContain('CREATE TABLE');
});

// ══ officer attendance table ══════════════════════════════════════
test('the officer table name is prefixed', function () {
    expect(TsmlIntergroupMeetingOfficerAttendanceTable::getTableName())->toStartWith('wp_');
});

test('creating the officer table issues ddl and records the version', function () {
    TsmlIntergroupMeetingOfficerAttendanceTable::createTable();

    $ddl = attendanceLastDdl();
    expect($ddl)->toContain('CREATE TABLE')
        ->and($ddl)->toContain('officer_id')
        ->and($ddl)->toContain('position_name')
        ->and($ddl)->toContain('UNIQUE KEY uq_meeting_officer');

    expect($this->storedOptions)->not->toBeEmpty();
});

test('dropping the officer table removes the table and its version', function () {
    TsmlIntergroupMeetingOfficerAttendanceTable::createTable();
    TsmlIntergroupMeetingOfficerAttendanceTable::dropTable();

    expect($this->wpdb->lastQuery())->toContain('DROP TABLE IF EXISTS')
        ->and($this->storedOptions)->toBe([]);
});

test('the officer table is created when no version is recorded', function () {
    TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();

    expect(attendanceLastDdl())->toContain('CREATE TABLE');
});

test('the officer table upgrade is skipped when the version matches', function () {
    TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();
    $GLOBALS['tsml_test_dbdelta'] = [];

    TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();

    expect($GLOBALS['tsml_test_dbdelta'])->toBe([]);
});
