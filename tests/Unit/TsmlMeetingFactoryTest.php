<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeeting;
use TsmlForUnity\Meetings\TsmlMeetingFactory;
use Unity\Contacts\Interfaces\Contact;
use WP_Mock;

/**
 * @covers \TsmlForUnity\Meetings\TsmlMeetingFactory
 */
class TsmlMeetingFactoryTest extends TestCase
{
    private TsmlMeetingFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlMeetingFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testCreateFromSourceReturnsNullForEmptySource(): void
    {
        $result = $this->factory->createFromSource([]);
        $this->assertNull($result);
    }

    public function testCreateFromSourceReturnsNullForMissingRequiredFields(): void
    {
        $source = [
            'id' => 123,
            'name' => 'Test Meeting',
            // Missing 'slug' and 'location'
        ];

        $result = $this->factory->createFromSource($source);
        $this->assertNull($result);
    }

    public function testCreateFromSourceReturnsMeetingForValidSource(): void
    {
        $source = [
            'id' => 123,
            'name' => 'Morning Serenity',
            'slug' => 'morning-serenity',
            'location' => 'Community Center',
            'day' => 1,
            'time' => '07:00',
            'end_time' => '08:00',
            'types' => ['O', 'D'],
            'attendance_option' => 'in_person',
        ];

        // Mock WordPress functions
        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with(123)
            ->andReturn('https://example.com/meetings/morning-serenity/');

        WP_Mock::userFunction('get_post_status')
            ->once()
            ->with(123)
            ->andReturn('publish');

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with(123)
            ->andReturn([]);

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        $this->stubPostLookups(123);

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(TsmlMeeting::class, $result);
        $this->assertEquals(123, $result->getId());
        $this->assertEquals('Morning Serenity', $result->getName());
        $this->assertEquals('morning-serenity', $result->getSlug());
        $this->assertSame('Community Center', $result->getLocation()->getName());
        $this->assertEquals('Monday', $result->getDayOfWeek());
        $this->assertEquals('07:00', $result->getTime());
        $this->assertEquals('08:00', $result->getEndTime());
        $this->assertContains('Open', $result->getTypes());
        $this->assertContains('Discussion', $result->getTypes());
        $this->assertFalse($result->isOnline());

        // Pin the fields that map to the constructor's remaining positional
        // slots. Without these, a mid-list parameter insertion could rebind
        // url/state/day/meta/updated to the wrong value and still pass:
        // PHPStan cannot catch a same-typed swap, and these getters were
        // previously unasserted.
        $this->assertSame('https://example.com/meetings/morning-serenity/', $result->getUrl());
        $this->assertSame('publish', $result->getState());
        $this->assertSame(1, $result->getDay());
        $this->assertSame([], $result->getMeta());
        $this->assertSame('2024-01-01 00:00:00', $result->getUpdated());
    }

    public function testCreateFromSourceHandlesOnlineMeeting(): void
    {
        $source = [
            'id' => 456,
            'name' => 'Online Meeting',
            'slug' => 'online-meeting',
            'location' => 'Zoom',
            'day' => 3,
            'time' => '19:00',
            'types' => ['O', 'VM'],
            'attendance_option' => 'online',
        ];

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with(456)
            ->andReturn('https://example.com/meetings/online-meeting/');

        WP_Mock::userFunction('get_post_status')
            ->once()
            ->with(456)
            ->andReturn('publish');

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with(456)
            ->andReturn([
                'conference_url' => ['https://zoom.us/j/123456789'],
                'conference_url_notes' => ['Password: 12345'],
            ]);

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        $this->stubPostLookups(456);

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(TsmlMeeting::class, $result);
        $this->assertTrue($result->isOnline());
        $this->assertEquals('https://zoom.us/j/123456789', $result->getOnlineLink());
        $this->assertEquals('Password: 12345', $result->getOnlineNotes());
    }

    public function testGetTypeNameReturnsCorrectName(): void
    {
        $this->assertEquals('Open', $this->factory->getTypeName('O'));
        $this->assertEquals('Closed', $this->factory->getTypeName('C'));
        $this->assertEquals('Discussion', $this->factory->getTypeName('D'));
        $this->assertEquals('Big Book', $this->factory->getTypeName('B'));
        $this->assertEquals('Wheelchair Access', $this->factory->getTypeName('X'));
    }

    public function testGetTypeNameReturnsNullForUnknownCode(): void
    {
        $this->assertNull($this->factory->getTypeName('UNKNOWN'));
    }

    public function testGetTypeCodeReturnsCorrectCode(): void
    {
        $this->assertEquals('O', $this->factory->getTypeCode('Open'));
        $this->assertEquals('C', $this->factory->getTypeCode('Closed'));
        $this->assertEquals('D', $this->factory->getTypeCode('Discussion'));
    }

    public function testGetTypeCodeReturnsNullForUnknownName(): void
    {
        $this->assertNull($this->factory->getTypeCode('Unknown Type'));
    }

    public function testGetAllTypesReturnsArray(): void
    {
        $types = $this->factory->getAllTypes();

        $this->assertIsArray($types);
        $this->assertNotEmpty($types);
        $this->assertArrayHasKey('O', $types);
        $this->assertArrayHasKey('C', $types);
        $this->assertEquals('Open', $types['O']);
        $this->assertEquals('Closed', $types['C']);
    }

    public function testGetDayNameReturnsCorrectDay(): void
    {
        $this->assertEquals('Sunday', $this->factory->getDayName(0));
        $this->assertEquals('Monday', $this->factory->getDayName(1));
        $this->assertEquals('Tuesday', $this->factory->getDayName(2));
        $this->assertEquals('Wednesday', $this->factory->getDayName(3));
        $this->assertEquals('Thursday', $this->factory->getDayName(4));
        $this->assertEquals('Friday', $this->factory->getDayName(5));
        $this->assertEquals('Saturday', $this->factory->getDayName(6));
    }

    public function testGetDayNameReturnsNullForInvalidDay(): void
    {
        $this->assertNull($this->factory->getDayName(7));
        $this->assertNull($this->factory->getDayName(-1));
    }

    public function testCreateFromSourceExtractsContacts(): void
    {
        $source = [
            'id' => 789,
            'name' => 'Test Meeting',
            'slug' => 'test-meeting',
            'location' => 'Test Location',
        ];

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with(789)
            ->andReturn('https://example.com/meetings/test-meeting/');

        WP_Mock::userFunction('get_post_status')
            ->once()
            ->with(789)
            ->andReturn('publish');

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with(789)
            ->andReturn([
                'contact_1_name' => ['John Doe'],
                'contact_1_email' => ['john@example.com'],
                'contact_1_phone' => ['555-1234'],
                'contact_2_name' => ['Jane Smith'],
                'contact_2_email' => ['jane@example.com'],
                'contact_2_phone' => ['555-5678'],
            ]);

        WP_Mock::userFunction('is_serialized')
            ->andReturn(false);

        $this->stubPostLookups(789);

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(TsmlMeeting::class, $result);

        $contacts = $result->getContacts();
        $this->assertCount(2, $contacts);

        $this->assertInstanceOf(Contact::class, $contacts[0]);
        $this->assertEquals('John Doe', $contacts[0]->getName());
        $this->assertEquals('john@example.com', $contacts[0]->getEmail());
        $this->assertEquals('555-1234', $contacts[0]->getPhone());

        $this->assertInstanceOf(Contact::class, $contacts[1]);
        $this->assertEquals('Jane Smith', $contacts[1]->getName());
    }

    /**
     * Stub the lookups createFromSource needs beyond the per-test ones.
     *
     * The factory guards on is_wp_error and get_post_meta existing before it
     * will build anything, and reads post_modified_gmt off get_post, so a
     * source that should succeed still yields null without these.
     *
     * @param int $id Meeting post ID.
     * @return void
     */
    private function stubPostLookups(int $id): void
    {
        WP_Mock::userFunction('get_post')
            ->with($id)
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);

        WP_Mock::userFunction('is_wp_error')
            ->andReturn(false);

        WP_Mock::userFunction('get_post_meta')
            ->andReturn('');
    }
}
