<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Locations\TsmlLocation;
use TsmlForUnity\Meetings\TsmlMeeting;
use Unity\Meetings\Interfaces\Meeting;

/**
 * Tests for TsmlMeeting entity
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeeting
 */
class TsmlMeetingTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_meeting_interface(): void
    {
        $this->assertInstanceOf(Meeting::class, $this->minimalMeeting());
    }

    /**
     * @test
     */
    public function it_exposes_every_required_field(): void
    {
        $location = new TsmlLocation(id: 3, name: 'Hall');

        $meeting = new TsmlMeeting(
            id: 10,
            name: 'Monday Nooners',
            slug: 'monday-nooners',
            location: $location,
            url: 'https://example.com/meeting',
            day: 1,
            dayOfWeek: 'Monday',
            time: '12:00',
            endTime: '13:00',
            types: ['O', 'D'],
            state: 'active',
            online: true,
            contacts: ['c1'],
            meta: ['key' => 'value'],
            onlineLink: 'https://zoom.example/1',
            onlineNotes: 'Password 123',
            updated: '2026-06-01 10:00:00'
        );

        $this->assertSame(10, $meeting->getId());
        $this->assertSame('Monday Nooners', $meeting->getName());
        $this->assertSame('monday-nooners', $meeting->getSlug());
        $this->assertSame($location, $meeting->getLocation());
        $this->assertSame('https://example.com/meeting', $meeting->getUrl());
        $this->assertSame(1, $meeting->getDay());
        $this->assertSame('Monday', $meeting->getDayOfWeek());
        $this->assertSame('12:00', $meeting->getTime());
        $this->assertSame('13:00', $meeting->getEndTime());
        $this->assertSame(['O', 'D'], $meeting->getTypes());
        $this->assertSame('active', $meeting->getState());
        $this->assertTrue($meeting->isOnline());
        $this->assertSame(['c1'], $meeting->getContacts());
        $this->assertSame(['key' => 'value'], $meeting->getMeta());
        $this->assertSame('https://zoom.example/1', $meeting->getOnlineLink());
        $this->assertSame('Password 123', $meeting->getOnlineNotes());
        $this->assertSame('2026-06-01 10:00:00', $meeting->getUpdated());
    }

    /**
     * @test
     */
    public function optional_trailing_fields_default_to_empty(): void
    {
        $meeting = $this->minimalMeeting();

        $this->assertSame([], $meeting->getContacts());
        $this->assertSame([], $meeting->getMeta());
        $this->assertSame('', $meeting->getOnlineLink());
        $this->assertSame('', $meeting->getOnlineNotes());
        $this->assertSame('', $meeting->getUpdated());
    }

    /**
     * @test
     */
    public function location_may_be_null(): void
    {
        $this->assertNull($this->minimalMeeting()->getLocation());
    }

    /**
     * @test
     */
    public function offline_meeting_reports_not_online(): void
    {
        $this->assertFalse($this->minimalMeeting()->isOnline());
    }

    private function minimalMeeting(): TsmlMeeting
    {
        return new TsmlMeeting(
            id: 1,
            name: 'Meeting',
            slug: 'meeting',
            location: null,
            url: '',
            day: 0,
            dayOfWeek: 'Sunday',
            time: '18:00',
            endTime: '19:00',
            types: [],
            state: 'active',
            online: false
        );
    }
}
