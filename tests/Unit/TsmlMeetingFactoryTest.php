<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\TsmlMeetingFactory;
use WP_Mock;

/**
 * Mock Unity interfaces and classes for testing
 */
// Define mock Unity Contact interfaces if they don't exist
if (!interface_exists('Unity\\Contact\\Interfaces\\ContactInterface')) {
    eval('namespace Unity\\Contact\\Interfaces; interface ContactInterface { public function getName(): string; public function getEmail(): string; public function getPhone(): string; }');
}

if (!interface_exists('Unity\\Contact\\Interfaces\\ContactFactoryInterface')) {
    eval('namespace Unity\\Contact\\Interfaces; interface ContactFactoryInterface { public function createFromSource(array $source): ContactInterface; public function create(string $name = "", string $email = "", string $phone = ""): ContactInterface; }');
}

if (!class_exists('Unity\\Contact\\Contact')) {
    eval('
    namespace Unity\\Contact;

    class Contact implements Interfaces\\ContactInterface {
        private string $name;
        private string $email;
        private string $phone;

        public function __construct(string $name = "", string $email = "", string $phone = "") {
            $this->name = $name;
            $this->email = $email;
            $this->phone = $phone;
        }

        public function getName(): string { return $this->name; }
        public function getEmail(): string { return $this->email; }
        public function getPhone(): string { return $this->phone; }
    }
    ');
}

if (!class_exists('Unity\\Contact\\ContactFactory')) {
    eval('
    namespace Unity\\Contact;

    class ContactFactory implements Interfaces\\ContactFactoryInterface {
        public function createFromSource(array $source): Interfaces\\ContactInterface {
            return new Contact($source["name"] ?? "", $source["email"] ?? "", $source["phone"] ?? "");
        }
        public function create(string $name = "", string $email = "", string $phone = ""): Interfaces\\ContactInterface {
            return new Contact($name, $email, $phone);
        }
    }
    ');
}

// Define mock Unity Meeting interfaces if they don't exist
if (!interface_exists('Unity\\Meetings\\Interfaces\\MeetingFactoryInterface')) {
    eval('namespace Unity\\Meetings\\Interfaces; interface MeetingFactoryInterface { public function createFromSource(array $source); }');
}

if (!interface_exists('Unity\\Meetings\\Interfaces\\MeetingInterface')) {
    eval('namespace Unity\\Meetings\\Interfaces; interface MeetingInterface {}');
}

if (!class_exists('Unity\\Meetings\\Meeting')) {
    eval('
    namespace Unity\\Meetings;

    class Meeting implements Interfaces\\MeetingInterface {
        private int $id;
        private string $name;
        private string $slug;
        private string $location;
        private string $url;
        private int $day;
        private string $dayOfWeek;
        private string $time;
        private string $endTime;
        private array $types;
        private string $state;
        private bool $online;
        private array $contacts;
        private array $meta;
        private string $onlineLink;
        private string $onlineNotes;

        public function __construct(
            int $id,
            string $name,
            string $slug,
            string $location,
            string $url,
            int $day,
            string $dayOfWeek,
            string $time,
            string $endTime,
            array $types,
            string $state,
            bool $online,
            array $contacts,
            array $meta,
            string $onlineLink = "",
            string $onlineNotes = ""
        ) {
            $this->id = $id;
            $this->name = $name;
            $this->slug = $slug;
            $this->location = $location;
            $this->url = $url;
            $this->day = $day;
            $this->dayOfWeek = $dayOfWeek;
            $this->time = $time;
            $this->endTime = $endTime;
            $this->types = $types;
            $this->state = $state;
            $this->online = $online;
            $this->contacts = $contacts;
            $this->meta = $meta;
            $this->onlineLink = $onlineLink;
            $this->onlineNotes = $onlineNotes;
        }

        public function getId(): int { return $this->id; }
        public function getName(): string { return $this->name; }
        public function getSlug(): string { return $this->slug; }
        public function getLocation(): string { return $this->location; }
        public function getUrl(): string { return $this->url; }
        public function getDay(): int { return $this->day; }
        public function getDayOfWeek(): string { return $this->dayOfWeek; }
        public function getTime(): string { return $this->time; }
        public function getEndTime(): string { return $this->endTime; }
        public function getTypes(): array { return $this->types; }
        public function getState(): string { return $this->state; }
        public function isOnline(): bool { return $this->online; }
        public function getContacts(): array { return $this->contacts; }
        public function getMeta(): array { return $this->meta; }
        public function getOnlineLink(): string { return $this->onlineLink; }
        public function getOnlineNotes(): string { return $this->onlineNotes; }
    }
    ');
}

/**
 * @covers \TsmlForUnity\TsmlMeetingFactory
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

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(\Unity\Meetings\Meeting::class, $result);
        $this->assertEquals(123, $result->getId());
        $this->assertEquals('Morning Serenity', $result->getName());
        $this->assertEquals('morning-serenity', $result->getSlug());
        $this->assertEquals('Community Center', $result->getLocation());
        $this->assertEquals('Monday', $result->getDayOfWeek());
        $this->assertEquals('07:00', $result->getTime());
        $this->assertEquals('08:00', $result->getEndTime());
        $this->assertContains('Open', $result->getTypes());
        $this->assertContains('Discussion', $result->getTypes());
        $this->assertFalse($result->isOnline());
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

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(\Unity\Meetings\Meeting::class, $result);
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

        $result = $this->factory->createFromSource($source);

        $this->assertInstanceOf(\Unity\Meetings\Meeting::class, $result);

        $contacts = $result->getContacts();
        $this->assertCount(2, $contacts);

        $this->assertInstanceOf(\Unity\Contact\Interfaces\ContactInterface::class, $contacts[0]);
        $this->assertEquals('John Doe', $contacts[0]->getName());
        $this->assertEquals('john@example.com', $contacts[0]->getEmail());
        $this->assertEquals('555-1234', $contacts[0]->getPhone());

        $this->assertInstanceOf(\Unity\Contact\Interfaces\ContactInterface::class, $contacts[1]);
        $this->assertEquals('Jane Smith', $contacts[1]->getName());
    }
}
