<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionView;
use Unity\Positions\Interfaces\PositionView;

/**
 * Tests for TsmlPositionView
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionView
 */
class TsmlPositionViewTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_position_view_interface(): void
    {
        $view = new TsmlPositionView($this->position());

        $this->assertInstanceOf(PositionView::class, $view);
    }

    /**
     * @test
     */
    public function a_view_with_no_member_is_vacant(): void
    {
        $view = new TsmlPositionView($this->position());

        $this->assertTrue($view->isVacant());
        $this->assertNull($view->getMember());
        $this->assertSame([], $view->getMembers());
        $this->assertSame('', $view->getOfficerDisplayName());
        $this->assertSame('', $view->getPublicDisplayName());
        $this->assertNull($view->getPersonalEmail());
        $this->assertNull($view->getMobileNumber());
        $this->assertNull($view->getRotationDate());
        $this->assertNull($view->getMonthsUntilRotation());
        $this->assertNull($view->getDaysUntilRotation());
    }

    /**
     * @test
     */
    public function it_derives_title_email_and_description_from_the_position(): void
    {
        $view = new TsmlPositionView($this->position());

        $this->assertSame('Chairs the meeting', $view->getTitle());
        $this->assertSame('Chairs the meeting', $view->getDescription());
        $this->assertSame('chair@example.com', $view->getPositionEmail());
    }

    /**
     * @test
     */
    public function a_view_with_a_member_pulls_contact_details_from_it(): void
    {
        $member = new TsmlMember(
            id: 1,
            anonymousName: 'John D.',
            showAnonymousName: true,
            personalEmail: 'john@example.com',
            mobileNumber: '0700 111',
        );

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertFalse($view->isVacant());
        $this->assertSame($member, $view->getMember());
        $this->assertSame([$member], $view->getMembers());
        $this->assertSame('john@example.com', $view->getPersonalEmail());
        $this->assertSame('0700 111', $view->getMobileNumber());
        $this->assertSame('John D.', $view->getOfficerDisplayName());
        $this->assertSame('John D.', $view->getPublicDisplayName());
    }

    /**
     * @test
     */
    public function public_display_name_is_hidden_when_the_member_opts_out(): void
    {
        $member = new TsmlMember(id: 1, anonymousName: 'John D.', showAnonymousName: false);

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertSame('', $view->getPublicDisplayName());
    }

    /**
     * @test
     */
    public function officer_display_name_joins_all_members(): void
    {
        $a = new TsmlMember(id: 1, anonymousName: 'John D.');
        $b = new TsmlMember(id: 2, anonymousName: 'Jane B.');

        $view = new TsmlPositionView($this->position(), $a, [$a, $b]);

        $this->assertSame('John D., Jane B.', $view->getOfficerDisplayName());
        $this->assertSame([$a, $b], $view->getMembers());
    }

    /**
     * @test
     */
    public function it_parses_an_iso_rotation_date_in_the_future(): void
    {
        $future = (new \DateTime('today'))->modify('+40 days')->format('Y-m-d');
        $member = new TsmlMember(id: 1, intergroupPositionRotation: $future);

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertInstanceOf(\DateTime::class, $view->getRotationDate());
        $this->assertSame($future, $view->getRotationDate()->format('Y-m-d'));
        $this->assertSame(40, $view->getDaysUntilRotation());
        $this->assertGreaterThan(0, $view->getMonthsUntilRotation());
    }

    /**
     * @test
     */
    public function it_parses_a_uk_format_rotation_date(): void
    {
        $member = new TsmlMember(id: 1, intergroupPositionRotation: '25/12/2099');

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertSame('2099-12-25', $view->getRotationDate()->format('Y-m-d'));
    }

    /**
     * @test
     */
    public function a_past_rotation_date_reports_zero_days_but_negative_months(): void
    {
        $past = (new \DateTime('today'))->modify('-40 days')->format('Y-m-d');
        $member = new TsmlMember(id: 1, intergroupPositionRotation: $past);

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertSame(0, $view->getDaysUntilRotation());
        $this->assertLessThan(0, $view->getMonthsUntilRotation());
    }

    /**
     * @test
     */
    public function an_unparseable_rotation_date_yields_no_rotation(): void
    {
        $member = new TsmlMember(id: 1, intergroupPositionRotation: 'not-a-date');

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertNull($view->getRotationDate());
        $this->assertNull($view->getDaysUntilRotation());
        $this->assertNull($view->getMonthsUntilRotation());
    }

    /**
     * @test
     */
    public function is_archivist_matches_the_role_case_insensitively(): void
    {
        $archivist = new TsmlPositionView(new TsmlPosition(shortDescription: 'archivist'));
        $chair     = new TsmlPositionView($this->position());

        $this->assertTrue($archivist->isArchivist());
        $this->assertFalse($chair->isArchivist());
    }

    private function position(): TsmlPosition
    {
        return new TsmlPosition(
            id: 5,
            email: 'chair@example.com',
            longName: 'Intergroup Chair',
            shortDescription: 'Chairs the meeting',
            summary: 'Runs intergroup',
        );
    }
}
