<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeeting;

/**
 * Tests for TsmlIntergroupMeeting entity
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting
 */
class TsmlIntergroupMeetingTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_intergroup_meeting_interface(): void
    {
        $meeting = new TsmlIntergroupMeeting(1);

        $this->assertInstanceOf(IntergroupMeeting::class, $meeting);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_default_values(): void
    {
        $meeting = new TsmlIntergroupMeeting(1);

        $this->assertEquals(1, $meeting->getId());
        $this->assertEquals('', $meeting->getTitle());
        $this->assertEquals([], $meeting->getGroupAttendees());
        $this->assertEquals([], $meeting->getOfficersAttending());
        $this->assertEquals('', $meeting->getDate());
        $this->assertEquals([], $meeting->getAttendingGroups());
        $this->assertEquals([], $meeting->getAttendingOfficers());
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_all_values(): void
    {
        $meeting = new TsmlIntergroupMeeting(
            id: 42,
            title: 'March Intergroup',
            groupAttendees: [10, 20, 30],
            officersAttending: [5, 6],
            date: '2026-03-15',
            attendingGroups: [10, 20, 30],
            attendingOfficers: [5, 6]
        );

        $this->assertEquals(42, $meeting->getId());
        $this->assertEquals('March Intergroup', $meeting->getTitle());
        $this->assertEquals([10, 20, 30], $meeting->getGroupAttendees());
        $this->assertEquals([5, 6], $meeting->getOfficersAttending());
        $this->assertEquals('2026-03-15', $meeting->getDate());
        $this->assertEquals([10, 20, 30], $meeting->getAttendingGroups());
        $this->assertEquals([5, 6], $meeting->getAttendingOfficers());
    }

    /**
     * @test
     */
    public function attending_groups_returns_array_of_integers(): void
    {
        $meeting = new TsmlIntergroupMeeting(
            id: 1,
            attendingGroups: [100, 200, 300]
        );

        $this->assertIsArray($meeting->getAttendingGroups());
        $this->assertContainsOnly('int', $meeting->getAttendingGroups());
        $this->assertEquals([100, 200, 300], $meeting->getAttendingGroups());
    }

    /**
     * @test
     */
    public function attending_officers_returns_array_of_integers(): void
    {
        $meeting = new TsmlIntergroupMeeting(
            id: 1,
            attendingOfficers: [7, 8, 9]
        );

        $this->assertIsArray($meeting->getAttendingOfficers());
        $this->assertContainsOnly('int', $meeting->getAttendingOfficers());
        $this->assertEquals([7, 8, 9], $meeting->getAttendingOfficers());
    }

    /**
     * @test
     */
    public function attending_groups_defaults_to_empty_array(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $this->assertIsArray($meeting->getAttendingGroups());
        $this->assertEmpty($meeting->getAttendingGroups());
    }

    /**
     * @test
     */
    public function attending_officers_defaults_to_empty_array(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $this->assertIsArray($meeting->getAttendingOfficers());
        $this->assertEmpty($meeting->getAttendingOfficers());
    }

    /**
     * @test
     */
    public function add_group_attendee_updates_group_attendees(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $result = $meeting->addGroupAttendee(10);

        $this->assertTrue($result);
        $this->assertContains(10, $meeting->getGroupAttendees());
    }

    /**
     * @test
     */
    public function add_group_attendee_returns_false_if_already_present(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

        $result = $meeting->addGroupAttendee(10);

        $this->assertFalse($result);
        $this->assertCount(1, $meeting->getGroupAttendees());
    }

    /**
     * @test
     */
    public function remove_group_attendee_removes_from_list(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10, 20]);

        $result = $meeting->removeGroupAttendee(10);

        $this->assertTrue($result);
        $this->assertNotContains(10, $meeting->getGroupAttendees());
        $this->assertContains(20, $meeting->getGroupAttendees());
    }

    /**
     * @test
     */
    public function remove_group_attendee_returns_false_if_not_present(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [20]);

        $result = $meeting->removeGroupAttendee(99);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function has_group_attendee_returns_correct_boolean(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

        $this->assertTrue($meeting->hasGroupAttendee(10));
        $this->assertFalse($meeting->hasGroupAttendee(99));
    }

    /**
     * @test
     */
    public function add_officer_attendee_updates_officers_attending(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $result = $meeting->addOfficerAttendee(5);

        $this->assertTrue($result);
        $this->assertContains(5, $meeting->getOfficersAttending());
    }

    /**
     * @test
     */
    public function add_officer_attendee_returns_false_if_already_present(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [5]);

        $result = $meeting->addOfficerAttendee(5);

        $this->assertFalse($result);
        $this->assertCount(1, $meeting->getOfficersAttending());
    }

    /**
     * @test
     */
    public function remove_officer_attendee_removes_from_list(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [5, 6]);

        $result = $meeting->removeOfficerAttendee(5);

        $this->assertTrue($result);
        $this->assertNotContains(5, $meeting->getOfficersAttending());
        $this->assertContains(6, $meeting->getOfficersAttending());
    }

    /**
     * @test
     */
    public function remove_officer_attendee_returns_false_if_not_present(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [6]);

        $result = $meeting->removeOfficerAttendee(99);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function has_officer_attendee_returns_correct_boolean(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [5]);

        $this->assertTrue($meeting->hasOfficerAttendee(5));
        $this->assertFalse($meeting->hasOfficerAttendee(99));
    }
}
