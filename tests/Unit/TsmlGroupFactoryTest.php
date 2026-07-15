<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Contacts\Interfaces\Contact;
use Unity\Groups\Interfaces\Group;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;
use WP_Mock;

/**
 * @covers \TsmlForUnity\Groups\TsmlGroupFactory
 */
class TsmlGroupFactoryTest extends TestCase
{
    private TsmlGroupFactory $factory;

    /** @var MeetingRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $meetingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // A group's meetings come from the MeetingRepository, so the factory
        // needs one to return anything but an empty list. The contact factory
        // is left to its default on purpose, which exercises that fallback.
        $this->meetingRepository = $this->createMock(MeetingRepository::class);
        $this->factory = new TsmlGroupFactory(null, $this->meetingRepository);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_returns_null_when_post_does_not_exist(): void
    {
        WP_Mock::userFunction('get_post')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->factory->createFromSource(999);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_post_is_wrong_type(): void
    {
        $post = $this->createMockPost([
            'ID' => 123,
            'post_type' => 'post', // Wrong type, should be 'tsml_group'
            'post_title' => 'Wrong Post Type',
        ]);

        WP_Mock::userFunction('get_post')
            ->once()
            ->with(123)
            ->andReturn($post);

        $result = $this->factory->createFromSource(123);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_creates_group_from_valid_post(): void
    {
        $postId = 100;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_title' => 'Test Group',
        ]);

        $meta = [
            TsmlGroupFields::EMAIL => ['group@example.com'],
            TsmlGroupFields::GROUP_NOTES => ['Some notes about the group'],
            TsmlGroupFields::WEBSITE => ['https://testgroup.org'],
            TsmlGroupFields::PHONE => ['555-1234'],
            TsmlGroupFields::VENMO => ['@TestGroup'],
            TsmlGroupFields::PAYPAL => ['TestGroupAA'],
            TsmlGroupFields::SQUARE => ['$TestGroup'],
            TsmlGroupFields::DISTRICT_ID => ['42'],
            'contact_1_name' => ['John Doe'],
            'contact_1_email' => ['john@example.com'],
            'contact_1_phone' => ['555-5678'],
        ];

        WP_Mock::userFunction('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        WP_Mock::userFunction('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        $this->meetingRepository->method('findByGroupId')
            ->with($postId)
            ->willReturn([
                $this->createMeeting(200),
                $this->createMeeting(201),
                $this->createMeeting(202),
            ]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('https://example.com/group/test-group');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Group::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Test Group', $result->getTitle());
        $this->assertEquals('group@example.com', $result->getEmail());
        $this->assertEquals(
            [200, 201, 202],
            array_map(static fn (Meeting $meeting): int => $meeting->getId(), $result->getMeetings())
        );
        $this->assertEquals('https://example.com/group/test-group', $result->getLink());
        $this->assertEquals('Some notes about the group', $result->getGroupNotes());
        $this->assertEquals('https://testgroup.org', $result->getWebsite());
        $this->assertEquals('555-1234', $result->getPhone());
        $this->assertEquals('@TestGroup', $result->getVenmo());
        $this->assertEquals('TestGroupAA', $result->getPaypal());
        $this->assertEquals('$TestGroup', $result->getSquare());
        $this->assertEquals(42, $result->getDistrictId());
    }

    /**
     * @test
     */
    public function it_extracts_multiple_contacts(): void
    {
        $postId = 200;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_title' => 'Multi-TsmlContact Group',
        ]);

        $meta = [
            'contact_1_name' => ['John Doe'],
            'contact_1_email' => ['john@example.com'],
            'contact_1_phone' => ['555-1111'],
            'contact_2_name' => ['Jane Smith'],
            'contact_2_email' => ['jane@example.com'],
            'contact_2_phone' => ['555-2222'],
            'contact_3_name' => ['Bob Wilson'],
            'contact_3_email' => ['bob@example.com'],
            'contact_3_phone' => ['555-3333'],
        ];

        WP_Mock::userFunction('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        WP_Mock::userFunction('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Group::class, $result);
        
        $contacts = $result->getContacts();
        $this->assertCount(3, $contacts);
        
        $this->assertInstanceOf(Contact::class, $contacts[0]);
        $this->assertEquals('John Doe', $contacts[0]->getName());
        $this->assertEquals('john@example.com', $contacts[0]->getEmail());
        $this->assertEquals('555-1111', $contacts[0]->getPhone());
        
        $this->assertInstanceOf(Contact::class, $contacts[1]);
        $this->assertEquals('Jane Smith', $contacts[1]->getName());
        $this->assertEquals('jane@example.com', $contacts[1]->getEmail());
        $this->assertEquals('555-2222', $contacts[1]->getPhone());
        
        $this->assertInstanceOf(Contact::class, $contacts[2]);
        $this->assertEquals('Bob Wilson', $contacts[2]->getName());
        $this->assertEquals('bob@example.com', $contacts[2]->getEmail());
        $this->assertEquals('555-3333', $contacts[2]->getPhone());
    }

    /**
     * @test
     */
    public function it_handles_empty_meta(): void
    {
        $postId = 300;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_title' => 'Minimal Group',
        ]);

        WP_Mock::userFunction('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Group::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Minimal Group', $result->getTitle());
        $this->assertEquals('', $result->getEmail());
        $this->assertEquals([], $result->getMeetings());
        $this->assertEquals('', $result->getGroupNotes());
        $this->assertNull($result->getDistrictId());
        $this->assertEquals([], $result->getContacts());
    }

    /**
     * @test
     */
    public function it_handles_partial_contact_info(): void
    {
        $postId = 400;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_title' => 'Partial TsmlContact Group',
        ]);

        $meta = [
            'contact_1_name' => ['John Doe'],
            // Missing email and phone for contact 1
            'contact_2_email' => ['jane@example.com'],
            // Missing name and phone for contact 2
        ];

        WP_Mock::userFunction('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn($meta);

        WP_Mock::userFunction('maybe_unserialize')
            ->andReturnUsing(function ($value) {
                return $value;
            });

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $contacts = $result->getContacts();
        $this->assertCount(2, $contacts);
        
        // First contact has name only
        $this->assertInstanceOf(Contact::class, $contacts[0]);
        $this->assertEquals('John Doe', $contacts[0]->getName());
        $this->assertEquals('', $contacts[0]->getEmail());
        $this->assertEquals('', $contacts[0]->getPhone());
        
        // Second contact has email only
        $this->assertInstanceOf(Contact::class, $contacts[1]);
        $this->assertEquals('', $contacts[1]->getName());
        $this->assertEquals('jane@example.com', $contacts[1]->getEmail());
        $this->assertEquals('', $contacts[1]->getPhone());
    }

    /**
     * @test
     */
    public function it_handles_false_permalink(): void
    {
        $postId = 500;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::POST_TYPE,
            'post_title' => 'Test',
        ]);

        WP_Mock::userFunction('get_post')
            ->once()
            ->with($postId)
            ->andReturn($post);

        WP_Mock::userFunction('get_post_custom')
            ->once()
            ->with($postId)
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn(false);

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Group::class, $result);
        $this->assertEquals('', $result->getLink());
    }

    /**
     * Create a Meeting that reports the given ID and carries no contacts.
     *
     * @param int $id Meeting ID.
     * @return Meeting&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMeeting(int $id)
    {
        $meeting = $this->createMock(Meeting::class);
        $meeting->method('getId')->willReturn($id);
        $meeting->method('getContacts')->willReturn([]);

        return $meeting;
    }

    /**
     * Create a mock WP_Post object
     *
     * @param array $properties Post properties
     * @return object Mock post object
     */
    private function createMockPost(array $properties): object
    {
        return (object) array_merge([
            'ID' => 0,
            'post_title' => '',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => '',
        ], $properties);
    }
}
