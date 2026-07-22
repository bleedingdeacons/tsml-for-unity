<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMemberView;
use Unity\Members\Interfaces\MemberView;
use Unity\Members\ResponderCertification;

/**
 * Tests for TsmlMemberView
 *
 * @covers \TsmlForUnity\Members\TsmlMemberView
 */
class TsmlMemberViewTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_member_view_interface(): void
    {
        $this->assertInstanceOf(MemberView::class, new TsmlMemberView());
    }

    /**
     * @test
     */
    public function it_applies_defaults_for_an_empty_view(): void
    {
        $view = new TsmlMemberView();

        $this->assertSame(0, $view->getId());
        $this->assertSame('', $view->getAnonymousName());
        $this->assertSame('', $view->getPersonalEmail());
        $this->assertSame('', $view->getMobileNumber());
        $this->assertSame(0, $view->getHomeGroupId());
        $this->assertSame('', $view->getHomeGroupName());
        $this->assertFalse($view->hasHomeGroup());
        $this->assertFalse($view->isGSR());
        $this->assertSame(0, $view->getPositionId());
        $this->assertSame('', $view->getPositionName());
        $this->assertFalse($view->hasPosition());
        $this->assertSame('', $view->getRotationDate());
        $this->assertFalse($view->isTwelfthStepper());
        $this->assertFalse($view->isTelephoneResponder());
        $this->assertSame(ResponderCertification::None, $view->getResponderCertification());
        $this->assertSame('', $view->getArea());
        $this->assertSame([], $view->getAccepts());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_passed_to_the_constructor(): void
    {
        $view = new TsmlMemberView(
            id: 42,
            anonymousName: 'John D.',
            personalEmail: 'john@example.com',
            mobileNumber: '0700 111',
            homeGroupId: 10,
            homeGroupName: 'Tuesday Group',
            isGSR: true,
            positionId: 5,
            positionName: 'Chair',
            rotationDate: '2026-01-01',
            twelfthStepper: true,
            telephoneResponder: true,
            responderCertification: ResponderCertification::Certified,
            area: 'North',
            accepts: ['phone', 'email']
        );

        $this->assertSame(42, $view->getId());
        $this->assertSame('John D.', $view->getAnonymousName());
        $this->assertSame('john@example.com', $view->getPersonalEmail());
        $this->assertSame('0700 111', $view->getMobileNumber());
        $this->assertSame(10, $view->getHomeGroupId());
        $this->assertSame('Tuesday Group', $view->getHomeGroupName());
        $this->assertTrue($view->hasHomeGroup());
        $this->assertTrue($view->isGSR());
        $this->assertSame(5, $view->getPositionId());
        $this->assertSame('Chair', $view->getPositionName());
        $this->assertTrue($view->hasPosition());
        $this->assertSame('2026-01-01', $view->getRotationDate());
        $this->assertTrue($view->isTwelfthStepper());
        $this->assertTrue($view->isTelephoneResponder());
        $this->assertSame(ResponderCertification::Certified, $view->getResponderCertification());
        $this->assertSame('North', $view->getArea());
        $this->assertSame(['phone', 'email'], $view->getAccepts());
    }

    /**
     * @test
     */
    public function has_home_group_and_has_position_track_their_ids(): void
    {
        $withGroup = new TsmlMemberView(homeGroupId: 3);
        $withPosition = new TsmlMemberView(positionId: 7);

        $this->assertTrue($withGroup->hasHomeGroup());
        $this->assertFalse($withGroup->hasPosition());

        $this->assertFalse($withPosition->hasHomeGroup());
        $this->assertTrue($withPosition->hasPosition());
    }
}
