<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable;

/*
 * Tests for the two attendance table managers (name resolution, upgrade
 * gating and drop). createTable() is not exercised because it require()s a
 * WordPress core file that does not exist in the unit environment.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::class, \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::class);

beforeEach(function () {
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;

    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';
        public function query(string $sql): int
        {
            return 0;
        }
    };
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

test('group table name is prefixed', function () {
    expect(TsmlIntergroupMeetingGroupAttendanceTable::getTableName())->toBe('wp_' . TsmlIntergroupMeetingGroupAttendanceTable::TABLE_NAME);
});

test('officer table name is prefixed', function () {
    expect(TsmlIntergroupMeetingOfficerAttendanceTable::getTableName())->toBe('wp_' . TsmlIntergroupMeetingOfficerAttendanceTable::TABLE_NAME);
});

test('maybe upgrade does nothing when the installed version matches', function () {
    Functions\expect('get_option')
        ->with(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION_OPTION)
        ->andReturn(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION);

    // A matching version must not attempt createTable() (which would
    // require a missing WP core file and fatal).
    TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
})->throwsNoExceptions();

test('group drop table issues a drop and clears the version option', function () {
    Functions\expect('esc_sql')->andReturnUsing(fn ($v) => $v);
    Functions\expect('delete_option')
        ->once()
        ->with(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION_OPTION)
        ->andReturn(true);

    TsmlIntergroupMeetingGroupAttendanceTable::dropTable();
});

test('officer drop table issues a drop and clears the version option', function () {
    Functions\expect('esc_sql')->andReturnUsing(fn ($v) => $v);
    Functions\expect('delete_option')
        ->once()
        ->with(TsmlIntergroupMeetingOfficerAttendanceTable::DB_VERSION_OPTION)
        ->andReturn(true);

    TsmlIntergroupMeetingOfficerAttendanceTable::dropTable();
});
