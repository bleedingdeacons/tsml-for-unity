<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Members\Interfaces\Member;
use WP_Mock;

/**
 * @covers \TsmlForUnity\Members\TsmlMemberFactory
 */
class TsmlMemberFactoryTest extends TestCase
{
    private TsmlMemberFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlMemberFactory();

        // Every createFromSource reads post_modified_gmt for the updated
        // timestamp; no test here asserts on it.
        WP_Mock::userFunction('get_post')
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
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

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_TWELFTH_STEPPER, $postId)
            ->andReturn(true);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_TELEPHONE_RESPONDER, $postId)
            ->andReturn(true);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_AREA, $postId)
            ->andReturn('North London');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ACCEPTS, $postId)
            ->andReturn(['phone', 'email']);

        $this->mockGdprFields($postId);

        $member = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($postId, $member->getId());
        $this->assertSame('John D.', $member->getAnonymousName());
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
        $this->assertTrue($member->isTwelfthStepper());
        $this->assertTrue($member->isTelephoneResponder());
        $this->assertSame('North London', $member->getArea());
        $this->assertSame(['phone', 'email'], $member->getAccepts());
    }

    /**
     * @test
     */
    public function it_handles_home_group_as_array(): void
    {
        $postId = 124;

        // ACF hands back real WP_Post objects, and the factory type-checks for
        // them, so a stdClass would silently fall through to the ID default.
        $wpPost1 = new \WP_Post(['ID' => 99, 'post_type' => 'tsml_group']);
        $wpPost2 = new \WP_Post(['ID' => 100, 'post_type' => 'tsml_group']);

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

        $wpPost = new \WP_Post(['ID' => 42, 'post_type' => 'tsml_group', 'post_title' => 'Test Group']);

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

        // Mock all fields returning null
        WP_Mock::userFunction('get_field')
            ->andReturn(null);

        $member = $this->factory->createFromSource($postId);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($postId, $member->getId());
        $this->assertSame('', $member->getAnonymousName());
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
        $this->assertFalse($member->isTwelfthStepper());
        $this->assertFalse($member->isTelephoneResponder());
        $this->assertSame('', $member->getArea());
        $this->assertSame([], $member->getAccepts());
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

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_TWELFTH_STEPPER, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_TELEPHONE_RESPONDER, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_AREA, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_ACCEPTS, $postId)
            ->andReturn(null);

        $this->mockGdprFields($postId);
    }

    /**
     * Mock the GDPR acceptance fields as unset.
     *
     * The factory reads all five on every createFromSource, so they need a
     * handler even for tests that say nothing about GDPR.
     *
     * @param int $postId Member post ID.
     * @return void
     */
    private function mockGdprFields(int $postId): void
    {
        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_GDPR_ACCEPTED, $postId)
            ->andReturn(false);

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD, $postId)
            ->andReturn('');

        WP_Mock::userFunction('get_field')
            ->with(TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT, $postId)
            ->andReturn('');
    }
}