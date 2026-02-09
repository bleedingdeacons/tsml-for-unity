<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\TsmlMemberFactory;
use TsmlForUnity\TsmlMemberFields;
use Unity\Members\Interfaces\Member;
use Unity\Members\Member;
use WP_Mock;

/**
 * Mock Unity Member interfaces and classes for testing
 */
if (!interface_exists('Unity\\Members\\Interfaces\\Member')) {
    eval('namespace Unity\\Members\\Interfaces; 
    interface Member { 
        public function getId(): int;
        public function getAnonymousName(): string;
        public function getPrivateName(): string;
        public function getEmail(): string;
        public function showAnonymousName(): bool;
        public function showMemberProfile(): bool;
        public function getAnonymousProfile(): string;
        public function getIntergroupPosition(): int;
        public function getIntergroupPositionRotation(): string;
        public function getHomeGroup(): int;
        public function isGSR(): bool;
        public function getMeetingPO(): mixed;
        public function getPersonalEmail(): string;
        public function getMobileNumber(): string;
    }');
}

if (!interface_exists('Unity\\Members\\Interfaces\\MemberFactory')) {
    eval('namespace Unity\\Members\\Interfaces; 
    interface MemberFactory { 
        public function createFromSource(int $id): Member; 
    }');
}

if (!class_exists('Unity\\Members\\Member')) {
    eval('
    namespace Unity\\Members;

    class Member implements Interfaces\\Member {
        private int $id;
        private string $anonymousName;
        private string $privateName;
        private string $email;
        private bool $showAnonymousName;
        private bool $showMemberProfile;
        private string $anonymousProfile;
        private int $intergroupPosition;
        private string $intergroupPositionRotation;
        private int $homeGroup;
        private bool $isGSR;
        private mixed $meetingPO;
        private string $personalEmail;
        private string $mobileNumber;

        public function __construct(
            int $id,
            string $anonymousName = "",
            string $privateName = "",
            string $email = "",
            bool $showAnonymousName = false,
            bool $showMemberProfile = false,
            string $anonymousProfile = "",
            int $intergroupPosition = 0,
            string $intergroupPositionRotation = "",
            int $homeGroup = 0,
            bool $isGSR = false,
            mixed $meetingPO = null,
            string $personalEmail = "",
            string $mobileNumber = ""
        ) {
            $this->id = $id;
            $this->anonymousName = $anonymousName;
            $this->privateName = $privateName;
            $this->email = $email;
            $this->showAnonymousName = $showAnonymousName;
            $this->showMemberProfile = $showMemberProfile;
            $this->anonymousProfile = $anonymousProfile;
            $this->intergroupPosition = $intergroupPosition;
            $this->intergroupPositionRotation = $intergroupPositionRotation;
            $this->homeGroup = $homeGroup;
            $this->isGSR = $isGSR;
            $this->meetingPO = $meetingPO;
            $this->personalEmail = $personalEmail;
            $this->mobileNumber = $mobileNumber;
        }

        public function getId(): int { return $this->id; }
        public function getAnonymousName(): string { return $this->anonymousName; }
        public function getPrivateName(): string { return $this->privateName; }
        public function getEmail(): string { return $this->email; }
        public function showAnonymousName(): bool { return $this->showAnonymousName; }
        public function showMemberProfile(): bool { return $this->showMemberProfile; }
        public function getAnonymousProfile(): string { return $this->anonymousProfile; }
        public function getIntergroupPosition(): int { return $this->intergroupPosition; }
        public function getIntergroupPositionRotation(): string { return $this->intergroupPositionRotation; }
        public function getHomeGroup(): int { return $this->homeGroup; }
        public function isGSR(): bool { return $this->isGSR; }
        public function getMeetingPO(): mixed { return $this->meetingPO; }
        public function getPersonalEmail(): string { return $this->personalEmail; }
        public function getMobileNumber(): string { return $this->mobileNumber; }
    }
    ');
}

/**
 * @covers \TsmlForUnity\TsmlMemberFactory
 */
class TsmlMemberFactoryTest extends TestCase
{
    private TsmlMemberFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlMemberFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_creates_member_with_basic_fields(): void
    {
        $postId = 123;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('John Doe');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $postId)
            ->andReturn('John D.');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $postId)
            ->andReturn('john@example.com');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $postId)
            ->andReturn(true);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $postId)
            ->andReturn('Anonymous profile text');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_INTERGROUP_POSITION, $postId)
            ->andReturn(5);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $postId)
            ->andReturn('2024-01-01');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn(42);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $postId)
            ->andReturn(true);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_MEETING_PO, $postId)
            ->andReturn(null);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_MOBILE_NUMBER, $postId)
            ->andReturn('555-1234');

        $member = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($postId, $member->getId());
        $this->assertSame('John D.', $member->getAnonymousName());
        $this->assertSame('John Doe', $member->getPrivateName());
        $this->assertSame('john@example.com', $member->getEmail());
        $this->assertTrue($member->showAnonymousName());
        $this->assertFalse($member->showMemberProfile());
        $this->assertSame('Anonymous profile text', $member->getAnonymousProfile());
        $this->assertSame(5, $member->getIntergroupPosition());
        $this->assertSame('2024-01-01', $member->getIntergroupPositionRotation());
        $this->assertSame(42, $member->getHomeGroup());
        $this->assertTrue($member->isGSR());
        $this->assertNull($member->getMeetingPO());
        $this->assertSame('john@example.com', $member->getPersonalEmail());
        $this->assertSame('555-1234', $member->getMobileNumber());
    }

    /**
     * @test
     */
    public function it_handles_home_group_as_array(): void
    {
        $postId = 124;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('Jane Doe');

        // Create mock WP_Post objects
        $wpPost1 = (object) ['ID' => 99, 'post_type' => 'tsml_group'];
        $wpPost2 = (object) ['ID' => 100, 'post_type' => 'tsml_group'];

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn([$wpPost1, $wpPost2]); // ACF relationship field returns array of WP_Post objects

        // Mock other required fields with defaults
        $this->mockDefaultFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(99, $member->getHomeGroup()); // Should use ID from first WP_Post object
    }

    /**
     * @test
     */
    public function it_handles_home_group_as_wp_post_object(): void
    {
        $postId = 127;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('Alice Smith');

        // Create mock WP_Post object
        $wpPost = (object) ['ID' => 42, 'post_type' => 'tsml_group', 'post_title' => 'Test Group'];

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn($wpPost); // ACF post object field returns single WP_Post

        // Mock other required fields with defaults
        $this->mockDefaultFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(42, $member->getHomeGroup()); // Should use ID from WP_Post object
    }

    /**
     * @test
     */
    public function it_handles_home_group_as_numeric_array(): void
    {
        $postId = 128;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('Bob Jones');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn([55, 56]); // Array of numeric IDs (legacy format)

        // Mock other required fields with defaults
        $this->mockDefaultFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(55, $member->getHomeGroup()); // Should use first numeric ID
    }

    /**
     * @test
     */
    public function it_handles_empty_home_group_array(): void
    {
        $postId = 125;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('Bob Smith');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn([]); // Empty array

        // Mock other required fields with defaults
        $this->mockDefaultFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(0, $member->getHomeGroup()); // Should default to 0
    }

    /**
     * @test
     */
    public function it_handles_null_home_group(): void
    {
        $postId = 129;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn('Carol White');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOME_GROUP, $postId)
            ->andReturn(null); // get_field returns null

        // Mock other required fields with defaults
        $this->mockDefaultFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(0, $member->getHomeGroup()); // Should default to 0
    }

    /**
     * @test
     */
    public function it_handles_null_fields_with_defaults(): void
    {
        $postId = 126;

        WP_Mock::userFunction('get_the_title')
            ->once()
            ->with($postId)
            ->andReturn(null);

        // Mock all fields returning null
        WP_Mock::userFunction('get_field')
            ->andReturn(null);

        $member = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($postId, $member->getId());
        $this->assertSame('', $member->getAnonymousName());
        $this->assertSame('', $member->getPrivateName());
        $this->assertSame('', $member->getEmail());
        $this->assertFalse($member->showAnonymousName());
        $this->assertFalse($member->showMemberProfile());
        $this->assertSame('', $member->getAnonymousProfile());
        $this->assertSame(0, $member->getIntergroupPosition());
        $this->assertSame('', $member->getIntergroupPositionRotation());
        $this->assertSame(0, $member->getHomeGroup());
        $this->assertFalse($member->isGSR());
        $this->assertNull($member->getMeetingPO());
        $this->assertSame('', $member->getPersonalEmail());
        $this->assertSame('', $member->getMobileNumber());
    }

    /**
     * Helper method to mock default field values
     */
    private function mockDefaultFields(int $postId): void
    {
        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ANONYMOUS_NAME, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_PERSONAL_EMAIL, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ANONYMOUS_PROFILE, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_INTERGROUP_POSITION, $postId)
            ->andReturn(0);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_HOMEGROUP_GSR, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_MEETING_PO, $postId)
            ->andReturn(null);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_MOBILE_NUMBER, $postId)
            ->andReturn('');
    }
}