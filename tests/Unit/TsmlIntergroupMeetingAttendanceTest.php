<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendance;

/**
 * Tests for TsmlIntergroupMeetingGroupAttendance entity
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendance
 */
class TsmlIntergroupMeetingGroupAttendanceTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_intergroup_meeting_attendance_interface(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance();

        $this->assertInstanceOf(IntergroupMeetingGroupAttendance::class, $attendance);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_default_values(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance();

        $this->assertEquals(0, $attendance->getId());
        $this->assertEquals(0, $attendance->getIntergroupMeetingId());
        $this->assertEquals(0, $attendance->getMemberId());
        $this->assertEquals('', $attendance->getMeetingGroup());
        $this->assertEquals('', $attendance->getGsrName());
        $this->assertFalse($attendance->isGsrProxy());
        $this->assertEquals('', $attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_all_values(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 42,
            intergroupMeetingId: 100,
            memberId: 55,
            meetingGroup: 'Saturday Morning Group',
            gsrName: 'John D.',
            gsrProxy: true,
            gsrProxyName: 'Jane S.'
        );

        $this->assertEquals(42, $attendance->getId());
        $this->assertEquals(100, $attendance->getIntergroupMeetingId());
        $this->assertEquals(55, $attendance->getMemberId());
        $this->assertEquals('Saturday Morning Group', $attendance->getMeetingGroup());
        $this->assertEquals('John D.', $attendance->getGsrName());
        $this->assertTrue($attendance->isGsrProxy());
        $this->assertEquals('Jane S.', $attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function proxy_flag_defaults_to_false(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            intergroupMeetingId: 10,
            memberId: 20,
            meetingGroup: 'Some Group',
            gsrName: 'Bob R.'
        );

        $this->assertFalse($attendance->isGsrProxy());
        $this->assertEquals('', $attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function proxy_name_is_independent_of_proxy_flag(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            gsrProxy: false,
            gsrProxyName: 'Orphaned Name'
        );

        $this->assertFalse($attendance->isGsrProxy());
        $this->assertEquals('Orphaned Name', $attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function it_stores_meeting_group_as_plain_text(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            meetingGroup: 'Tuesday Night Big Book Study'
        );

        $this->assertIsString($attendance->getMeetingGroup());
        $this->assertEquals('Tuesday Night Big Book Study', $attendance->getMeetingGroup());
    }

    /**
     * @test
     */
    public function it_stores_gsr_name_as_plain_text(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            gsrName: 'Mary K.'
        );

        $this->assertIsString($attendance->getGsrName());
        $this->assertEquals('Mary K.', $attendance->getGsrName());
    }

    /**
     * @test
     */
    public function it_handles_empty_strings_for_text_fields(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            meetingGroup: '',
            gsrName: '',
            gsrProxyName: ''
        );

        $this->assertEmpty($attendance->getMeetingGroup());
        $this->assertEmpty($attendance->getGsrName());
        $this->assertEmpty($attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function it_stores_member_id_as_integer(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            memberId: 55
        );

        $this->assertIsInt($attendance->getMemberId());
        $this->assertEquals(55, $attendance->getMemberId());
    }

    /**
     * @test
     */
    public function it_stores_intergroup_meeting_id_as_integer(): void
    {
        $attendance = new TsmlIntergroupMeetingGroupAttendance(
            id: 1,
            intergroupMeetingId: 999
        );

        $this->assertIsInt($attendance->getIntergroupMeetingId());
        $this->assertEquals(999, $attendance->getIntergroupMeetingId());
    }
}