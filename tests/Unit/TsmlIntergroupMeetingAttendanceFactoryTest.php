<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceFactory;

/*
 * Tests for the two intergroup-meeting attendance factories.
 *
 * Both read a row from a custom table via the global $wpdb, hydrate a value
 * object from it, and offer a createNew() for unsaved rows.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory::class, \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory::class);

beforeEach(function () {
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

/**
 * A tiny $wpdb double: prepare() interpolates naively, get_row() returns
 * whatever the test queued.
 *
 * @param array<string,mixed>|null $row
 */
function installWpdb(?array $row): void
{
    $GLOBALS['wpdb'] = new class ($row) {
        public string $prefix = 'wp_';
        /** @var array<string,mixed>|null */
        private $row;
        public function __construct($row)
        {
            $this->row = $row;
        }
        public function prepare(string $query, ...$args): string
        {
            return $query;
        }
        /** @return array<string,mixed>|null */
        public function get_row($query, $output = null)
        {
            return $this->row;
        }
    };
}

// ─── group attendance ───────────────────────────────────────────
test('group factory implements the interface', function () {
    expect(new TsmlIntergroupMeetingGroupAttendanceFactory())->toBeInstanceOf(IntergroupMeetingGroupAttendanceFactory::class);
});

test('group create from source hydrates from a row', function () {
    installWpdb([
        'id'                    => '5',
        'intergroup_meeting_id' => '42',
        'meeting_label'         => 'July',
        'group_id'              => '10',
        'member_id'             => '7',
        'meeting_group'         => 'Tuesday Group',
        'gsr_name'              => 'Alice A.',
        'gsr_proxy'             => '1',
        'gsr_proxy_name'        => 'Bob B.',
    ]);

    $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createFromSource(5);

    expect($attendance->getId())->toBe(5)
        ->and($attendance->getIntergroupMeetingId())->toBe(42)
        ->and($attendance->getMeetingLabel())->toBe('July')
        ->and($attendance->getGroupId())->toBe(10)
        ->and($attendance->getMemberId())->toBe(7)
        ->and($attendance->getMeetingGroup())->toBe('Tuesday Group')
        ->and($attendance->getGsrName())->toBe('Alice A.')
        ->and($attendance->isGsrProxy())->toBeTrue()
        ->and($attendance->getGsrProxyName())->toBe('Bob B.');
});

test('group create from source returns an empty object for a missing row', function () {
    installWpdb(null);

    $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createFromSource(99);

    expect($attendance->getId())->toBe(99)
        ->and($attendance->getGroupId())->toBe(0)
        ->and($attendance->isGsrProxy())->toBeFalse();
});

test('group create new builds an unsaved row', function () {
    $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createNew(
        42,
        'July',
        10,
        7,
        'Tuesday Group',
        'Alice A.',
        true,
        'Bob B.'
    );

    expect($attendance->getId())->toBe(0)
        ->and($attendance->getIntergroupMeetingId())->toBe(42)
        ->and($attendance->getGsrName())->toBe('Alice A.')
        ->and($attendance->isGsrProxy())->toBeTrue()
        ->and($attendance->getGsrProxyName())->toBe('Bob B.');
});

// ─── officer attendance ─────────────────────────────────────────
test('officer factory implements the interface', function () {
    expect(new TsmlIntergroupMeetingOfficerAttendanceFactory())->toBeInstanceOf(IntergroupMeetingOfficerAttendanceFactory::class);
});

test('officer create from source hydrates from a row', function () {
    installWpdb([
        'id'                    => '3',
        'intergroup_meeting_id' => '42',
        'meeting_label'         => 'July',
        'officer_id'            => '9',
        'position_name'         => 'Chair',
        'officer_name'          => 'Carol C.',
    ]);

    $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createFromSource(3);

    expect($attendance->getId())->toBe(3)
        ->and($attendance->getIntergroupMeetingId())->toBe(42)
        ->and($attendance->getMeetingLabel())->toBe('July')
        ->and($attendance->getOfficerId())->toBe(9)
        ->and($attendance->getPositionName())->toBe('Chair')
        ->and($attendance->getOfficerName())->toBe('Carol C.');
});

test('officer create from source returns an empty object for a missing row', function () {
    installWpdb(null);

    $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createFromSource(88);

    expect($attendance->getId())->toBe(88)
        ->and($attendance->getOfficerId())->toBe(0);
});

test('officer create new builds an unsaved row', function () {
    $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createNew(
        42,
        'July',
        9,
        'Chair',
        'Carol C.'
    );

    expect($attendance->getId())->toBe(0)
        ->and($attendance->getIntergroupMeetingId())->toBe(42)
        ->and($attendance->getPositionName())->toBe('Chair')
        ->and($attendance->getOfficerName())->toBe('Carol C.');
});
