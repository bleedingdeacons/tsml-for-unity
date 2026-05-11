<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMember;
use Unity\Members\Interfaces\Member;

/**
 * Tests for TsmlMember entity
 *
 * @covers \TsmlForUnity\Members\TsmlMember
 */
class TsmlMemberTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_member_interface(): void
    {
        $member = new TsmlMember(id: 1);

        $this->assertInstanceOf(Member::class, $member);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_minimal_values(): void
    {
        $member = new TsmlMember(id: 1);

        $this->assertEquals(1, $member->getId());
        $this->assertEquals('', $member->getAnonymousName());
        $this->assertFalse($member->showAnonymousName());
        $this->assertFalse($member->showMemberProfile());
        $this->assertEquals('', $member->getAnonymousProfile());
        $this->assertEquals(0, $member->getIntergroupPosition());
        $this->assertEquals('', $member->getIntergroupPositionRotation());
        $this->assertEquals(0, $member->getHomeGroup());
        $this->assertFalse($member->isGSR());
        $this->assertNull($member->getMeetingPO());
        $this->assertEquals('', $member->getPersonalEmail());
        $this->assertEquals('', $member->getMobileNumber());
        $this->assertFalse($member->isTwelfthStepper());
        $this->assertEquals('', $member->getArea());
        $this->assertSame([], $member->getAccepts());
        $this->assertFalse($member->isGdprAccepted());
        $this->assertEquals('', $member->getGdprAcceptedAt());
        $this->assertEquals('', $member->getGdprAcceptanceVersion());
        $this->assertEquals('', $member->getGdprAcceptanceMethod());
        $this->assertEquals('', $member->getGdprAcceptanceStatement());
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_all_values(): void
    {
        $member = new TsmlMember(
            id: 42,
            anonymousName: 'John D.',
            showAnonymousName: true,
            showMemberProfile: true,
            anonymousProfile: 'A member since 2020',
            intergroupPosition: 5,
            intergroupPositionRotation: '2024-01',
            homeGroup: 100,
            isGSR: true,
            meetingPO: 200,
            personalEmail: 'john.personal@example.com',
            mobileNumber: '+1234567890',
            twelfthStepper: true,
            area: 'North London',
            accepts: ['phone', 'in-person'],
            gdprAccepted: true,
            gdprAcceptedAt: '2026-04-27 15:45:00',
            gdprAcceptanceVersion: '2.1',
            gdprAcceptanceMethod: 'web-form',
            gdprAcceptanceStatement: 'I agree to the privacy policy.'
        );

        $this->assertEquals(42, $member->getId());
        $this->assertEquals('John D.', $member->getAnonymousName());
        $this->assertTrue($member->showAnonymousName());
        $this->assertTrue($member->showMemberProfile());
        $this->assertEquals('A member since 2020', $member->getAnonymousProfile());
        $this->assertEquals(5, $member->getIntergroupPosition());
        $this->assertEquals('2024-01', $member->getIntergroupPositionRotation());
        $this->assertEquals(100, $member->getHomeGroup());
        $this->assertTrue($member->isGSR());
        $this->assertEquals(200, $member->getMeetingPO());
        $this->assertEquals('john.personal@example.com', $member->getPersonalEmail());
        $this->assertEquals('+1234567890', $member->getMobileNumber());
        $this->assertTrue($member->isTwelfthStepper());
        $this->assertEquals('North London', $member->getArea());
        $this->assertSame(['phone', 'in-person'], $member->getAccepts());
        $this->assertTrue($member->isGdprAccepted());
        $this->assertEquals('2026-04-27 15:45:00', $member->getGdprAcceptedAt());
        $this->assertEquals('2.1', $member->getGdprAcceptanceVersion());
        $this->assertEquals('web-form', $member->getGdprAcceptanceMethod());
        $this->assertEquals('I agree to the privacy policy.', $member->getGdprAcceptanceStatement());
    }

    /**
     * @test
     */
    public function gsr_flag_can_be_toggled(): void
    {
        $gsrMember = new TsmlMember(id: 1, isGSR: true);
        $regularMember = new TsmlMember(id: 2, isGSR: false);

        $this->assertTrue($gsrMember->isGSR());
        $this->assertFalse($regularMember->isGSR());
    }

    /**
     * @test
     */
    public function visibility_flags_work_independently(): void
    {
        $member1 = new TsmlMember(
            id: 1,
            showAnonymousName: true,
            showMemberProfile: false
        );

        $member2 = new TsmlMember(
            id: 2,
            showAnonymousName: false,
            showMemberProfile: true
        );

        $this->assertTrue($member1->showAnonymousName());
        $this->assertFalse($member1->showMemberProfile());

        $this->assertFalse($member2->showAnonymousName());
        $this->assertTrue($member2->showMemberProfile());
    }

    /**
     * @test
     */
    public function it_handles_empty_strings_for_optional_fields(): void
    {
        $member = new TsmlMember(
            id: 1,
            anonymousName: '',
            personalEmail: '',
            mobileNumber: ''
        );

        $this->assertEmpty($member->getAnonymousName());
        $this->assertEmpty($member->getPersonalEmail());
        $this->assertEmpty($member->getMobileNumber());
    }

    /**
     * @test
     */
    public function it_stores_intergroup_position_as_integer(): void
    {
        $member = new TsmlMember(
            id: 1,
            intergroupPosition: 10
        );

        $this->assertIsInt($member->getIntergroupPosition());
        $this->assertEquals(10, $member->getIntergroupPosition());
    }

    /**
     * @test
     */
    public function it_stores_home_group_as_integer(): void
    {
        $member = new TsmlMember(
            id: 1,
            homeGroup: 42
        );

        $this->assertIsInt($member->getHomeGroup());
        $this->assertEquals(42, $member->getHomeGroup());
    }

    /**
     * @test
     */
    public function meeting_po_accepts_mixed_types(): void
    {
        $withInt = new TsmlMember(id: 1, meetingPO: 200);
        $withString = new TsmlMember(id: 2, meetingPO: 'Some PO');
        $withNull = new TsmlMember(id: 3, meetingPO: null);

        $this->assertEquals(200, $withInt->getMeetingPO());
        $this->assertEquals('Some PO', $withString->getMeetingPO());
        $this->assertNull($withNull->getMeetingPO());
    }

    /**
     * @test
     */
    public function twelfth_stepper_and_contact_fields_are_independent(): void
    {
        $stepper = new TsmlMember(
            id: 1,
            twelfthStepper: true,
            area: 'East London',
            accepts: ['phone', 'email']
        );
        $regular = new TsmlMember(id: 2);

        $this->assertTrue($stepper->isTwelfthStepper());
        $this->assertEquals('East London', $stepper->getArea());
        $this->assertSame(['phone', 'email'], $stepper->getAccepts());

        $this->assertFalse($regular->isTwelfthStepper());
        $this->assertEquals('', $regular->getArea());
        $this->assertSame([], $regular->getAccepts());
    }

    /**
     * @test
     */
    public function gdpr_compliance_fields_are_independent(): void
    {
        $accepted = new TsmlMember(
            id: 1,
            gdprAccepted: true,
            gdprAcceptedAt: '2026-04-27 15:45:00',
            gdprAcceptanceVersion: '2.1',
            gdprAcceptanceMethod: 'web-form',
            gdprAcceptanceStatement: 'I agree to the privacy policy.'
        );

        $notAccepted = new TsmlMember(id: 2);

        $this->assertTrue($accepted->isGdprAccepted());
        $this->assertEquals('2026-04-27 15:45:00', $accepted->getGdprAcceptedAt());
        $this->assertEquals('2.1', $accepted->getGdprAcceptanceVersion());
        $this->assertEquals('web-form', $accepted->getGdprAcceptanceMethod());
        $this->assertEquals('I agree to the privacy policy.', $accepted->getGdprAcceptanceStatement());

        $this->assertFalse($notAccepted->isGdprAccepted());
        $this->assertEquals('', $notAccepted->getGdprAcceptedAt());
        $this->assertEquals('', $notAccepted->getGdprAcceptanceVersion());
        $this->assertEquals('', $notAccepted->getGdprAcceptanceMethod());
        $this->assertEquals('', $notAccepted->getGdprAcceptanceStatement());
    }
}