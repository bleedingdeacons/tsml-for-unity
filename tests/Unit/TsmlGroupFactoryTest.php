<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\TsmlGroup;
use TsmlForUnity\TsmlGroupFactory;
use TsmlForUnity\TsmlGroupFields;
use WP_Mock;

// Define mock Unity interfaces if they don't exist
if (!interface_exists('Unity\\Groups\\Interfaces\\GroupInterface')) {
    eval('
    namespace Unity\\Groups\\Interfaces;
    
    interface GroupInterface {
        public function getId(): int;
        public function getTitle(): string;
        public function getEmail(): string;
        public function getMeetingIds(): array;
        public function getLink(): string;
        public function isValid(): bool;
    }
    ');
}

if (!interface_exists('Unity\\Groups\\Interfaces\\GroupFactoryInterface')) {
    eval('
    namespace Unity\\Groups\\Interfaces;
    
    interface GroupFactoryInterface {
        public function createFromSource(int $sourceId): ?GroupInterface;
    }
    ');
}

/**
 * @covers \TsmlForUnity\TsmlGroupFactory
 */
class TsmlGroupFactoryTest extends TestCase
{
    private TsmlGroupFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlGroupFactory();
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
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
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

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([200, 201, 202]); // Meeting IDs

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('https://example.com/group/test-group');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(TsmlGroup::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Test Group', $result->getTitle());
        $this->assertEquals('group@example.com', $result->getEmail());
        $this->assertEquals([200, 201, 202], $result->getMeetingIds());
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
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
            'post_title' => 'Multi-Contact Group',
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

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(TsmlGroup::class, $result);
        
        $contacts = $result->getContacts();
        $this->assertCount(3, $contacts);
        
        $this->assertEquals('John Doe', $contacts[0]['name']);
        $this->assertEquals('john@example.com', $contacts[0]['email']);
        $this->assertEquals('555-1111', $contacts[0]['phone']);
        
        $this->assertEquals('Jane Smith', $contacts[1]['name']);
        $this->assertEquals('jane@example.com', $contacts[1]['email']);
        $this->assertEquals('555-2222', $contacts[1]['phone']);
        
        $this->assertEquals('Bob Wilson', $contacts[2]['name']);
        $this->assertEquals('bob@example.com', $contacts[2]['email']);
        $this->assertEquals('555-3333', $contacts[2]['phone']);
    }

    /**
     * @test
     */
    public function it_handles_empty_meta(): void
    {
        $postId = 300;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
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

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(TsmlGroup::class, $result);
        $this->assertEquals($postId, $result->getId());
        $this->assertEquals('Minimal Group', $result->getTitle());
        $this->assertEquals('', $result->getEmail());
        $this->assertEquals([], $result->getMeetingIds());
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
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
            'post_title' => 'Partial Contact Group',
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

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn('');

        $result = $this->factory->createFromSource($postId);

        $contacts = $result->getContacts();
        $this->assertCount(2, $contacts);
        
        // First contact has name only
        $this->assertEquals('John Doe', $contacts[0]['name']);
        $this->assertEquals('', $contacts[0]['email']);
        $this->assertEquals('', $contacts[0]['phone']);
        
        // Second contact has email only
        $this->assertEquals('', $contacts[1]['name']);
        $this->assertEquals('jane@example.com', $contacts[1]['email']);
        $this->assertEquals('', $contacts[1]['phone']);
    }

    /**
     * @test
     */
    public function it_handles_false_permalink(): void
    {
        $postId = 500;
        $post = $this->createMockPost([
            'ID' => $postId,
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
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

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        WP_Mock::userFunction('get_permalink')
            ->once()
            ->with($postId)
            ->andReturn(false);

        $result = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(TsmlGroup::class, $result);
        $this->assertEquals('', $result->getLink());
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
