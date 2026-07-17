<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Members\TsmlMemberRevisor;
use TsmlForUnity\Tests\Fixtures\MemberStub;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRevisor;

/**
 * Unit tests for TsmlMemberRevisor
 *
 * The property under test throughout: a field you do not name is carried
 * over. That is the inverse of MemberFactory::createNew(), where an omitted
 * parameter resets to the default and the repository persists that reset as a
 * deletion.
 */
class TsmlMemberRevisorTest extends TestCase
{
    private TsmlMemberRevisor $revisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->revisor = new TsmlMemberRevisor();
    }

    /**
     * @test
     */
    public function it_implements_the_unity_contract(): void
    {
        $this->assertInstanceOf(MemberRevisor::class, $this->revisor);
    }

    /**
     * @test
     */
    public function it_returns_a_member(): void
    {
        $revised = $this->revisor->revise($this->member(), mobileNumber: '07999999999');

        $this->assertInstanceOf(Member::class, $revised);
    }

    /**
     * @test
     */
    public function revising_one_field_changes_only_that_field(): void
    {
        $base = $this->member();

        $revised = $this->revisor->revise($base, mobileNumber: '07999999999');

        $this->assertEquals('07999999999', $revised->getMobileNumber());

        $expected = $base->toArray();
        $expected['mobileNumber'] = '07999999999';
        $this->assertEquals($expected, $revised->toArray());
    }

    /**
     * The bug this whole contract exists to make impossible: changing a
     * mobile number must not erase the member's GDPR consent record.
     *
     * @test
     */
    public function revising_an_unrelated_field_preserves_gdpr_consent(): void
    {
        $revised = $this->revisor->revise($this->member(), mobileNumber: '07999999999');

        $this->assertTrue($revised->isGdprAccepted());
        $this->assertEquals('2026-04-27 15:45:00', $revised->getGdprAcceptedAt());
        $this->assertEquals('2.1', $revised->getGdprAcceptanceVersion());
        $this->assertEquals('web-form', $revised->getGdprAcceptanceMethod());
        $this->assertEquals('I agree to the privacy policy.', $revised->getGdprAcceptanceStatement());
        $this->assertTrue($revised->isTwelfthStepper());
        $this->assertTrue($revised->isTelephoneResponder());
        $this->assertEquals('North', $revised->getArea());
        $this->assertEquals(['accepts-male'], $revised->getAccepts());
    }

    /**
     * @test
     */
    public function revising_nothing_returns_an_equal_member(): void
    {
        $base = $this->member();

        $this->assertEquals($base->toArray(), $this->revisor->revise($base)->toArray());
    }

    /**
     * @test
     */
    public function it_can_revise_several_fields_at_once(): void
    {
        $revised = $this->revisor->revise(
            $this->member(),
            anonymousName: 'Jane B.',
            homeGroup: 99,
            isGSR: false
        );

        $this->assertEquals('Jane B.', $revised->getAnonymousName());
        $this->assertEquals(99, $revised->getHomeGroup());
        $this->assertFalse($revised->isGSR());
        $this->assertEquals('john@example.com', $revised->getPersonalEmail());
    }

    /**
     * Falsy values must be distinguishable from "not supplied" — the
     * changes are filtered on `!== null`, not on truthiness. If that ever
     * regressed to array_filter()'s default, revising a flag to false or a
     * string to '' would silently do nothing.
     *
     * @test
     */
    public function falsy_values_are_applied_not_treated_as_absent(): void
    {
        $base = $this->member();

        $revised = $this->revisor->revise(
            $base,
            isGSR: false,
            anonymousProfile: '',
            homeGroup: 0,
            twelfthStepper: false,
            accepts: [],
            gdprAccepted: false
        );

        $this->assertFalse($revised->isGSR(), 'false must be applied, not ignored');
        $this->assertEquals('', $revised->getAnonymousProfile(), "'' must be applied");
        $this->assertEquals(0, $revised->getHomeGroup(), '0 must be applied');
        $this->assertFalse($revised->isTwelfthStepper());
        $this->assertEquals([], $revised->getAccepts(), '[] must be applied');
        $this->assertFalse($revised->isGdprAccepted());
    }

    /**
     * @test
     */
    public function it_leaves_the_base_member_untouched(): void
    {
        $base   = $this->member();
        $before = $base->toArray();

        $this->revisor->revise($base, mobileNumber: 'changed', gdprAccepted: false);

        $this->assertEquals($before, $base->toArray());
    }

    /**
     * @test
     */
    public function id_and_updated_are_carried_over_and_cannot_be_revised(): void
    {
        $revised = $this->revisor->revise($this->member(), anonymousName: 'Jane B.');

        $this->assertEquals(42, $revised->getId());
        $this->assertEquals('2026-04-27 15:45:00', $revised->getUpdated());
    }

    /**
     * @test
     */
    public function meeting_po_is_carried_over(): void
    {
        $base    = $this->member()->with(['meetingPO' => 'PO-123']);
        $revised = $this->revisor->revise($base, anonymousName: 'Jane B.');

        $this->assertEquals('PO-123', $revised->getMeetingPO());
    }

    /**
     * The revisor delegates to TsmlMember::with(), so it cannot revise a
     * foreign Member implementation. Fail loudly rather than silently
     * reconstructing from getters, which would be drift-prone.
     *
     * @test
     */
    public function it_rejects_a_member_it_did_not_build(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('can only revise a TsmlMember');

        $this->revisor->revise(new MemberStub(id: 1), mobileNumber: '07999999999');
    }

    private function member(): TsmlMember
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
