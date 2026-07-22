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
    public function it_implements_the_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeeting::class, new TsmlIntergroupMeeting(id: 1));
    }

    /**
     * @test
     */
    public function it_exposes_constructor_values(): void
    {
        $meeting = new TsmlIntergroupMeeting(
            id: 5,
            title: 'July Intergroup',
            groupAttendees: [10, 20],
            officersAttending: [1, 2],
            date: '2026-07-01',
            updated: '2026-07-01 20:00:00'
        );

        $this->assertSame(5, $meeting->getId());
        $this->assertSame('July Intergroup', $meeting->getTitle());
        $this->assertSame([10, 20], $meeting->getGroupAttendees());
        $this->assertSame([1, 2], $meeting->getOfficersAttending());
        $this->assertSame('2026-07-01', $meeting->getDate());
        $this->assertSame('2026-07-01 20:00:00', $meeting->getUpdated());
    }

    /**
     * @test
     */
    public function it_defaults_collections_to_empty(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $this->assertSame('', $meeting->getTitle());
        $this->assertSame([], $meeting->getGroupAttendees());
        $this->assertSame([], $meeting->getOfficersAttending());
        $this->assertSame('', $meeting->getDate());
        $this->assertSame('', $meeting->getUpdated());
    }

    // ── group attendee mutators ────────────────────────────────────────

    /**
     * @test
     */
    public function adding_a_group_attendee_returns_true_and_records_it(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $this->assertTrue($meeting->addGroupAttendee(10));
        $this->assertTrue($meeting->hasGroupAttendee(10));
        $this->assertSame([10], $meeting->getGroupAttendees());
    }

    /**
     * @test
     */
    public function adding_a_duplicate_group_attendee_returns_false(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

        $this->assertFalse($meeting->addGroupAttendee(10));
        $this->assertSame([10], $meeting->getGroupAttendees());
    }

    /**
     * @test
     */
    public function removing_a_group_attendee_reindexes_the_list(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10, 20, 30]);

        $this->assertTrue($meeting->removeGroupAttendee(20));
        $this->assertSame([10, 30], $meeting->getGroupAttendees());
        $this->assertFalse($meeting->hasGroupAttendee(20));
    }

    /**
     * @test
     */
    public function removing_an_absent_group_attendee_returns_false(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, groupAttendees: [10]);

        $this->assertFalse($meeting->removeGroupAttendee(99));
        $this->assertSame([10], $meeting->getGroupAttendees());
    }

    // ── officer attendee mutators ──────────────────────────────────────

    /**
     * @test
     */
    public function adding_an_officer_attendee_returns_true_and_records_it(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1);

        $this->assertTrue($meeting->addOfficerAttendee(3));
        $this->assertTrue($meeting->hasOfficerAttendee(3));
        $this->assertSame([3], $meeting->getOfficersAttending());
    }

    /**
     * @test
     */
    public function adding_a_duplicate_officer_attendee_returns_false(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3]);

        $this->assertFalse($meeting->addOfficerAttendee(3));
        $this->assertSame([3], $meeting->getOfficersAttending());
    }

    /**
     * @test
     */
    public function removing_an_officer_attendee_reindexes_the_list(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3, 4, 5]);

        $this->assertTrue($meeting->removeOfficerAttendee(4));
        $this->assertSame([3, 5], $meeting->getOfficersAttending());
        $this->assertFalse($meeting->hasOfficerAttendee(4));
    }

    /**
     * @test
     */
    public function removing_an_absent_officer_attendee_returns_false(): void
    {
        $meeting = new TsmlIntergroupMeeting(id: 1, officersAttending: [3]);

        $this->assertFalse($meeting->removeOfficerAttendee(99));
        $this->assertSame([3], $meeting->getOfficersAttending());
    }
}
