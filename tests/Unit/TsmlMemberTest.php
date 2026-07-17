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
        $this->assertFalse($member->isTelephoneResponder());
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
            telephoneResponder: true,
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
        $this->assertTrue($member->isTelephoneResponder());
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
    public function telephone_responder_is_independent_of_twelfth_stepper(): void
    {
        $responderOnly = new TsmlMember(
            id: 1,
            twelfthStepper: false,
            telephoneResponder: true
        );

        $stepperOnly = new TsmlMember(
            id: 2,
            twelfthStepper: true,
            telephoneResponder: false
        );

        $both = new TsmlMember(
            id: 3,
            twelfthStepper: true,
            telephoneResponder: true
        );

        $neither = new TsmlMember(id: 4);

        $this->assertFalse($responderOnly->isTwelfthStepper());
        $this->assertTrue($responderOnly->isTelephoneResponder());

        $this->assertTrue($stepperOnly->isTwelfthStepper());
        $this->assertFalse($stepperOnly->isTelephoneResponder());

        $this->assertTrue($both->isTwelfthStepper());
        $this->assertTrue($both->isTelephoneResponder());

        $this->assertFalse($neither->isTwelfthStepper());
        $this->assertFalse($neither->isTelephoneResponder());
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

    // ── toArray() / with() ─────────────────────────────────────────────

    /**
     * toArray()'s keys must stay identical to the constructor's parameter
     * names, because with() spreads the array as named arguments. If they
     * drift, with() throws "Unknown named parameter" — so pin them here.
     *
     * @test
     */
    public function to_array_keys_match_the_constructor_parameter_names(): void
    {
        $member = new TsmlMember(id: 1);

        $constructorParams = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(TsmlMember::class, '__construct'))->getParameters()
        );

        $this->assertSame(
            $constructorParams,
            array_keys($member->toArray()),
            'toArray() keys must match the constructor parameter names, in order.'
        );
    }

    /**
     * @test
     */
    public function to_array_round_trips_through_the_constructor(): void
    {
        $original = $this->fullyPopulatedMember();

        $rebuilt = new TsmlMember(...$original->toArray());

        $this->assertEquals($original->toArray(), $rebuilt->toArray());
    }

    /**
     * @test
     */
    public function with_replaces_only_the_named_field(): void
    {
        $original = $this->fullyPopulatedMember();

        $updated = $original->with(['mobileNumber' => '07999999999']);

        $this->assertEquals('07999999999', $updated->getMobileNumber());

        // Everything else carried over untouched. This is the whole point:
        // createNew() would have reset every field not passed.
        $expected = $original->toArray();
        $expected['mobileNumber'] = '07999999999';
        $this->assertEquals($expected, $updated->toArray());
    }

    /**
     * The failure mode this class exists to prevent: a partial update must
     * not silently erase the GDPR consent record.
     *
     * @test
     */
    public function with_preserves_gdpr_consent_when_changing_an_unrelated_field(): void
    {
        $member = $this->fullyPopulatedMember();

        $updated = $member->with(['mobileNumber' => '07999999999']);

        $this->assertTrue($updated->isGdprAccepted());
        $this->assertEquals('2026-04-27 15:45:00', $updated->getGdprAcceptedAt());
        $this->assertEquals('2.1', $updated->getGdprAcceptanceVersion());
        $this->assertEquals('web-form', $updated->getGdprAcceptanceMethod());
        $this->assertEquals('I agree to the privacy policy.', $updated->getGdprAcceptanceStatement());
        $this->assertTrue($updated->isTelephoneResponder());
        $this->assertEquals('North', $updated->getArea());
        $this->assertEquals(['accepts-male'], $updated->getAccepts());
    }

    /**
     * @test
     */
    public function with_can_replace_several_fields_at_once(): void
    {
        $member = $this->fullyPopulatedMember();

        $updated = $member->with([
            'anonymousName' => 'Jane B.',
            'homeGroup'     => 99,
            'gdprAccepted'  => false,
        ]);

        $this->assertEquals('Jane B.', $updated->getAnonymousName());
        $this->assertEquals(99, $updated->getHomeGroup());
        $this->assertFalse($updated->isGdprAccepted());
        $this->assertEquals('john@example.com', $updated->getPersonalEmail());
    }

    /**
     * @test
     */
    public function with_leaves_the_original_untouched(): void
    {
        $original = $this->fullyPopulatedMember();
        $before   = $original->toArray();

        $original->with(['mobileNumber' => 'changed']);

        $this->assertEquals($before, $original->toArray(), 'with() must not mutate the receiver.');
    }

    /**
     * @test
     */
    public function with_no_changes_returns_an_equal_member(): void
    {
        $original = $this->fullyPopulatedMember();

        $this->assertEquals($original->toArray(), $original->with([])->toArray());
    }

    private function fullyPopulatedMember(): TsmlMember
    {
        return new TsmlMember(
            id: 42,
            anonymousName: 'John D.',
            showAnonymousName: true,
            showMemberProfile: true,
            anonymousProfile: 'A profile',
            intergroupPosition: 7,
            intergroupPositionRotation: '2026-01-01',
            homeGroup: 9,
            isGSR: true,
            meetingPO: null,
            personalEmail: 'john@example.com',
            mobileNumber: '07700900000',
            twelfthStepper: true,
            telephoneResponder: true,
            area: 'North',
            accepts: ['accepts-male'],
            gdprAccepted: true,
            gdprAcceptedAt: '2026-04-27 15:45:00',
            gdprAcceptanceVersion: '2.1',
            gdprAcceptanceMethod: 'web-form',
            gdprAcceptanceStatement: 'I agree to the privacy policy.',
            updated: '2026-04-27 15:45:00'
        );
    }
}