<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;

/*
 * Tests for TsmlIntergroupMeetingGroupAttendance entity
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance::class);

it('implements intergroup meeting attendance interface', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance();

    expect($attendance)->toBeInstanceOf(IntergroupMeetingGroupAttendance::class);
});

it('can be instantiated with default values', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance();

    expect($attendance->getId())->toEqual(0)
        ->and($attendance->getIntergroupMeetingId())->toEqual(0)
        ->and($attendance->getMeetingLabel())->toEqual('')
        ->and($attendance->getMemberId())->toEqual(0)
        ->and($attendance->getMeetingGroup())->toEqual('')
        ->and($attendance->getGsrName())->toEqual('')
        ->and($attendance->isGsrProxy())->toBeFalse()
        ->and($attendance->getGsrProxyName())->toEqual('');
});

it('can be instantiated with all values', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 42,
        intergroupMeetingId: 100,
        meetingLabel: 'Monthly Meeting — January 15, 2025',
        memberId: 55,
        meetingGroup: 'Saturday Morning Group',
        gsrName: 'John D.',
        gsrProxy: true,
        gsrProxyName: 'Jane S.'
    );

    expect($attendance->getId())->toEqual(42)
        ->and($attendance->getIntergroupMeetingId())->toEqual(100)
        ->and($attendance->getMeetingLabel())->toEqual('Monthly Meeting — January 15, 2025')
        ->and($attendance->getMemberId())->toEqual(55)
        ->and($attendance->getMeetingGroup())->toEqual('Saturday Morning Group')
        ->and($attendance->getGsrName())->toEqual('John D.')
        ->and($attendance->isGsrProxy())->toBeTrue()
        ->and($attendance->getGsrProxyName())->toEqual('Jane S.');
});

test('proxy flag defaults to false', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        intergroupMeetingId: 10,
        memberId: 20,
        meetingGroup: 'Some Group',
        gsrName: 'Bob R.'
    );

    expect($attendance->isGsrProxy())->toBeFalse()
        ->and($attendance->getGsrProxyName())->toEqual('');
});

test('proxy name is independent of proxy flag', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        gsrProxy: false,
        gsrProxyName: 'Orphaned Name'
    );

    expect($attendance->isGsrProxy())->toBeFalse()
        ->and($attendance->getGsrProxyName())->toEqual('Orphaned Name');
});

it('stores meeting group as plain text', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        meetingGroup: 'Tuesday Night Big Book Study'
    );

    expect($attendance->getMeetingGroup())->toBeString()
        ->and($attendance->getMeetingGroup())->toEqual('Tuesday Night Big Book Study');
});

it('stores gsr name as plain text', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        gsrName: 'Mary K.'
    );

    expect($attendance->getGsrName())->toBeString()
        ->and($attendance->getGsrName())->toEqual('Mary K.');
});

it('handles empty strings for text fields', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        meetingGroup: '',
        gsrName: '',
        gsrProxyName: ''
    );

    expect($attendance->getMeetingGroup())->toBeEmpty()
        ->and($attendance->getGsrName())->toBeEmpty()
        ->and($attendance->getGsrProxyName())->toBeEmpty();
});

it('stores member id as integer', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        memberId: 55
    );

    expect($attendance->getMemberId())->toBeInt()
        ->and($attendance->getMemberId())->toEqual(55);
});

it('stores intergroup meeting id as integer', function () {
    $attendance = new TsmlIntergroupMeetingGroupAttendance(
        id: 1,
        intergroupMeetingId: 999
    );

    expect($attendance->getIntergroupMeetingId())->toBeInt()
        ->and($attendance->getIntergroupMeetingId())->toEqual(999);
});
