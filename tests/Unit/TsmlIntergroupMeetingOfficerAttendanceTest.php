<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;

/*
 * Tests for TsmlIntergroupMeetingOfficerAttendance entity
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance::class);

it('implements intergroup meeting officer attendance interface', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance();

    expect($attendance)->toBeInstanceOf(IntergroupMeetingOfficerAttendance::class);
});

it('can be instantiated with default values', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance();

    expect($attendance->getId())->toEqual(0)
        ->and($attendance->getIntergroupMeetingId())->toEqual(0)
        ->and($attendance->getMeetingLabel())->toEqual('')
        ->and($attendance->getOfficerId())->toEqual(0)
        ->and($attendance->getPositionName())->toEqual('')
        ->and($attendance->getOfficerName())->toEqual('');
});

it('can be instantiated with all values', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 42,
        intergroupMeetingId: 100,
        meetingLabel: 'Monthly Meeting — January 15, 2025',
        officerId: 55,
        positionName: 'Treasurer',
        officerName: 'John D.'
    );

    expect($attendance->getId())->toEqual(42)
        ->and($attendance->getIntergroupMeetingId())->toEqual(100)
        ->and($attendance->getMeetingLabel())->toEqual('Monthly Meeting — January 15, 2025')
        ->and($attendance->getOfficerId())->toEqual(55)
        ->and($attendance->getPositionName())->toEqual('Treasurer')
        ->and($attendance->getOfficerName())->toEqual('John D.');
});

it('stores position name as plain text', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 1,
        positionName: 'Secretary'
    );

    expect($attendance->getPositionName())->toBeString()
        ->and($attendance->getPositionName())->toEqual('Secretary');
});

it('stores officer name as plain text', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 1,
        officerName: 'Mary K.'
    );

    expect($attendance->getOfficerName())->toBeString()
        ->and($attendance->getOfficerName())->toEqual('Mary K.');
});

it('handles empty strings for text fields', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 1,
        positionName: '',
        officerName: ''
    );

    expect($attendance->getPositionName())->toBeEmpty()
        ->and($attendance->getOfficerName())->toBeEmpty();
});

it('stores officer id as integer', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 1,
        officerId: 55
    );

    expect($attendance->getOfficerId())->toBeInt()
        ->and($attendance->getOfficerId())->toEqual(55);
});

it('stores intergroup meeting id as integer', function () {
    $attendance = new TsmlIntergroupMeetingOfficerAttendance(
        id: 1,
        intergroupMeetingId: 999
    );

    expect($attendance->getIntergroupMeetingId())->toBeInt()
        ->and($attendance->getIntergroupMeetingId())->toEqual(999);
});
