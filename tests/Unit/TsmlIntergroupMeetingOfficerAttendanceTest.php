<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendance;

/**
 * Tests for TsmlIntergroupMeetingOfficerAttendance entity
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendance
 */
class TsmlIntergroupMeetingOfficerAttendanceTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_intergroup_meeting_officer_attendance_interface(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance();

        $this->assertInstanceOf(IntergroupMeetingOfficerAttendance::class, $attendance);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_default_values(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance();

        $this->assertEquals(0, $attendance->getId());
        $this->assertEquals(0, $attendance->getIntergroupMeetingId());
        $this->assertEquals('', $attendance->getMeetingLabel());
        $this->assertEquals(0, $attendance->getOfficerId());
        $this->assertEquals('', $attendance->getPositionName());
        $this->assertEquals('', $attendance->getOfficerName());
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_all_values(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 42,
            intergroupMeetingId: 100,
            meetingLabel: 'Monthly Meeting — January 15, 2025',
            officerId: 55,
            positionName: 'Treasurer',
            officerName: 'John D.'
        );

        $this->assertEquals(42, $attendance->getId());
        $this->assertEquals(100, $attendance->getIntergroupMeetingId());
        $this->assertEquals('Monthly Meeting — January 15, 2025', $attendance->getMeetingLabel());
        $this->assertEquals(55, $attendance->getOfficerId());
        $this->assertEquals('Treasurer', $attendance->getPositionName());
        $this->assertEquals('John D.', $attendance->getOfficerName());
    }

    /**
     * @test
     */
    public function it_stores_position_name_as_plain_text(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 1,
            positionName: 'Secretary'
        );

        $this->assertIsString($attendance->getPositionName());
        $this->assertEquals('Secretary', $attendance->getPositionName());
    }

    /**
     * @test
     */
    public function it_stores_officer_name_as_plain_text(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 1,
            officerName: 'Mary K.'
        );

        $this->assertIsString($attendance->getOfficerName());
        $this->assertEquals('Mary K.', $attendance->getOfficerName());
    }

    /**
     * @test
     */
    public function it_handles_empty_strings_for_text_fields(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 1,
            positionName: '',
            officerName: ''
        );

        $this->assertEmpty($attendance->getPositionName());
        $this->assertEmpty($attendance->getOfficerName());
    }

    /**
     * @test
     */
    public function it_stores_officer_id_as_integer(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 1,
            officerId: 55
        );

        $this->assertIsInt($attendance->getOfficerId());
        $this->assertEquals(55, $attendance->getOfficerId());
    }

    /**
     * @test
     */
    public function it_stores_intergroup_meeting_id_as_integer(): void
    {
        $attendance = new TsmlIntergroupMeetingOfficerAttendance(
            id: 1,
            intergroupMeetingId: 999
        );

        $this->assertIsInt($attendance->getIntergroupMeetingId());
        $this->assertEquals(999, $attendance->getIntergroupMeetingId());
    }
}