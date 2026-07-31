<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Tests\TestCase;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use Unity\Members\Interfaces\Member;
use Unity\Members\ResponderCertification;

/**
 * @covers \TsmlForUnity\Members\TsmlMemberFactory
 */
class TsmlMemberFactoryTest extends TestCase
{
    private TsmlMemberFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TsmlMemberFactory();

        // Every createFromSource reads post_modified_gmt for the updated
        // timestamp; no test here asserts on it.
        Functions\expect('get_post')
            ->andReturn((object) ['post_modified_gmt' => '2024-01-01 00:00:00']);
    }

    /**
     * @test
     */
    public function it_creates_member_with_basic_fields(): void
    {
        $postId = 123;

        $this->stubFields([
            TsmlMemberFields::FIELD_ANONYMOUS_NAME => 'John D.',
            TsmlMemberFields::FIELD_PERSONAL_EMAIL => 'john@example.com',
            TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME => true,
            TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE => false,
            TsmlMemberFields::FIELD_ANONYMOUS_PROFILE => 'Anonymous profile text',
            TsmlMemberFields::FIELD_INTERGROUP_POSITION => 5,
            TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION => '2024-01-01',
            TsmlMemberFields::FIELD_HOME_GROUP => 42,
            TsmlMemberFields::FIELD_HOMEGROUP_GSR => true,
            TsmlMemberFields::FIELD_MEETING_PO => null,
            TsmlMemberFields::FIELD_MOBILE_NUMBER => '555-1234',
            TsmlMemberFields::FIELD_TWELFTH_STEPPER => true,
            TsmlMemberFields::FIELD_TELEPHONE_RESPONDER => true,
            TsmlMemberFields::FIELD_RESPONDER_CERTIFICATION => 'Certified',
            TsmlMemberFields::FIELD_AREA => 'North London',
            TsmlMemberFields::FIELD_ACCEPTS => ['phone', 'email'],
        ]);

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
        $this->assertSame(ResponderCertification::Certified, $member->getResponderCertification());
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

        $this->mockDefaultFields($postId, [
            TsmlMemberFields::FIELD_HOME_GROUP => [$wpPost1, $wpPost2],  // ACF relationship field returns array of WP_Post objects
        ]);

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

        $this->mockDefaultFields($postId, [
            TsmlMemberFields::FIELD_HOME_GROUP => $wpPost,  // ACF post object field returns single WP_Post
        ]);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(42, $member->getHomeGroup()); // Should use ID from WP_Post object
    }

    /**
     * @test
     */
    public function it_handles_home_group_as_numeric_array(): void
    {
        $postId = 128;

        $this->mockDefaultFields($postId, [
            TsmlMemberFields::FIELD_HOME_GROUP => [55, 56],  // Array of numeric IDs (legacy format)
        ]);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(55, $member->getHomeGroup()); // Should use first numeric ID
    }

    /**
     * @test
     */
    public function it_handles_empty_home_group_array(): void
    {
        $postId = 125;

        $this->mockDefaultFields($postId, [
            TsmlMemberFields::FIELD_HOME_GROUP => [],  // Empty array
        ]);

        $member = $this->factory->createFromSource($postId);

        $this->assertSame(0, $member->getHomeGroup()); // Should default to 0
    }

    /**
     * @test
     */
    public function it_handles_null_home_group(): void
    {
        $postId = 129;

        $this->mockDefaultFields($postId, [
            TsmlMemberFields::FIELD_HOME_GROUP => null,  // get_field returns null
        ]);

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
        Functions\expect('get_field')
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
        $this->assertSame(ResponderCertification::None, $member->getResponderCertification());
        $this->assertSame('', $member->getArea());
        $this->assertSame([], $member->getAccepts());
    }

    /**
     * Stub every field the factory reads, with defaults.
     *
     * A single call, because a test cannot layer a second get_field stub on
     * top of this one — see stubFields(). Per-test values go in $overrides.
     *
     * @param array<string, mixed> $overrides Field name => value.
     */
    private function mockDefaultFields(int $postId, array $overrides = []): void
    {
        $this->stubFields(array_merge([
            TsmlMemberFields::FIELD_ANONYMOUS_NAME => '',
            TsmlMemberFields::FIELD_PERSONAL_EMAIL => '',
            TsmlMemberFields::FIELD_SHOW_ANONYMOUS_NAME => false,
            TsmlMemberFields::FIELD_SHOW_MEMBER_PROFILE => false,
            TsmlMemberFields::FIELD_ANONYMOUS_PROFILE => '',
            TsmlMemberFields::FIELD_INTERGROUP_POSITION => 0,
            TsmlMemberFields::FIELD_INTERGROUP_POSITION_ROTATION => '',
            TsmlMemberFields::FIELD_HOMEGROUP_GSR => false,
            TsmlMemberFields::FIELD_MEETING_PO => null,
            TsmlMemberFields::FIELD_MOBILE_NUMBER => '',
            TsmlMemberFields::FIELD_TWELFTH_STEPPER => false,
            TsmlMemberFields::FIELD_TELEPHONE_RESPONDER => false,
            // Conditional logic hides this field for a non-responder, so ACF
            // returns nothing for it.
            TsmlMemberFields::FIELD_RESPONDER_CERTIFICATION => null,
            TsmlMemberFields::FIELD_AREA => '',
            TsmlMemberFields::FIELD_ACCEPTS => null,
        ], self::GDPR_FIELDS_UNSET, $overrides));
    }

    /**
     * The GDPR acceptance fields as unset.
     *
     * The factory reads all five on every createFromSource, so they need a
     * value even for tests that say nothing about GDPR.
     */
    private const GDPR_FIELDS_UNSET = [
        TsmlMemberFields::FIELD_GDPR_ACCEPTED => false,
        TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD => '',
        TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT => '',
    ];

    private function mockGdprFields(int $postId): void
    {
        $this->stubFields([
            TsmlMemberFields::FIELD_GDPR_ACCEPTED => false,
            TsmlMemberFields::FIELD_GDPR_ACCEPTED_AT => '',
            TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_VERSION => '',
            TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_METHOD => '',
            TsmlMemberFields::FIELD_GDPR_ACCEPTANCE_STATEMENT => '',
        ]);
    }

    /**
     * Stub get_field() for a whole set of fields at once.
     *
     * One expectation, dispatching on the field name. Stacking
     * Functions\expect('get_field')->with(...) calls does not work the way the
     * WP_Mock equivalent did: Brain Monkey keeps one stub per function per
     * test, and the first one registered answers every call whatever its
     * ->with() says. The failure is silent, so the mapping is explicit.
     *
     * @param array<string, mixed> $fields Field name => value.
     */
    private function stubFields(array $fields): void
    {
        Functions\expect('get_field')->andReturnUsing(
            static fn (string $field, int $postId): mixed => $fields[$field] ?? null
        );
    }

}