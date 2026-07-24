<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Positions\TsmlPositionView;
use TsmlForUnity\Positions\TsmlPositionViewFactory;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionRepository;
use WP_Mock;

/**
 * Tests for choosing which member currently holds a position.
 *
 * A position can have several members attached — an outgoing officer and
 * the one who replaced them both carry the same position id — so the
 * factory picks whoever has the latest rotation date, and returns every
 * member sharing that date (a genuine job-share). Get this wrong and the
 * committee page shows the wrong name, or a rotated-off officer.
 *
 * Rotation dates arrive in two formats (Y-m-d and d/m/Y) and are often
 * missing entirely, so the unparseable and absent cases are covered
 * alongside the happy path.
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionViewFactory
 * @covers \TsmlForUnity\Positions\TsmlPositionView
 */
class TsmlPositionViewRotationTest extends TestCase
{
    private const POSITION_ID = 5;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function position(): Position
    {
        $position = $this->createMock(Position::class);
        $position->method('getId')->willReturn(self::POSITION_ID);
        $position->method('getShortDescription')->willReturn('Treasurer');

        return $position;
    }

    private function member(int $id, string $rotation, string $email = ''): Member
    {
        $member = $this->createMock(Member::class);
        $member->method('getId')->willReturn($id);
        $member->method('getIntergroupPosition')->willReturn(self::POSITION_ID);
        $member->method('getIntergroupPositionRotation')->willReturn($rotation);
        $member->method('getPersonalEmail')->willReturn($email);
        $member->method('getMobileNumber')->willReturn('');

        return $member;
    }

    /** @param Member[] $members */
    private function factoryWith(array $members): TsmlPositionViewFactory
    {
        $positions = $this->createMock(PositionRepository::class);
        $positions->method('findAll')->willReturn([$this->position()]);
        $positions->method('findById')->willReturn($this->position());

        $memberRepo = $this->createMock(MemberRepository::class);
        $memberRepo->method('findAll')->willReturn($members);

        return new TsmlPositionViewFactory($positions, $memberRepo);
    }

    // ─── choosing the current holder ────────────────────────────────

    /** @test */
    public function the_member_with_the_latest_rotation_date_is_chosen(): void
    {
        $outgoing = $this->member(1, '2025-01-01');
        $current  = $this->member(2, '2027-01-01');

        $views = $this->factoryWith([$outgoing, $current])->createAll();

        $this->assertCount(1, $views);
        $this->assertSame($current, $views[0]->getMember());
    }

    /** @test */
    public function the_two_rotation_date_formats_are_compared_correctly(): void
    {
        // d/m/Y and Y-m-d both normalise to Y-m-d before comparison.
        $earlier = $this->member(1, '01/01/2025');
        $later   = $this->member(2, '2027-06-30');

        $views = $this->factoryWith([$earlier, $later])->createAll();

        $this->assertSame($later, $views[0]->getMember());
    }

    /** @test */
    public function members_sharing_the_latest_date_are_all_returned(): void
    {
        // A genuine job-share: both hold the position from the same date.
        $a = $this->member(1, '2027-01-01');
        $b = $this->member(2, '2027-01-01');
        $old = $this->member(3, '2020-01-01');

        $views = $this->factoryWith([$a, $b, $old])->createAll();

        $this->assertCount(2, $views[0]->getMembers());
    }

    /** @test */
    public function a_member_with_no_rotation_date_is_skipped_when_others_have_one(): void
    {
        $undated = $this->member(1, '');
        $dated   = $this->member(2, '2027-01-01');

        $views = $this->factoryWith([$undated, $dated])->createAll();

        $this->assertSame($dated, $views[0]->getMember());
    }

    /** @test */
    public function an_unparseable_rotation_date_is_skipped(): void
    {
        $bad  = $this->member(1, 'not a date');
        $good = $this->member(2, '2027-01-01');

        $views = $this->factoryWith([$bad, $good])->createAll();

        $this->assertSame($good, $views[0]->getMember());
    }

    /** @test */
    public function when_no_date_is_usable_the_first_member_is_taken(): void
    {
        // Nothing to order by, so the list order decides rather than
        // leaving the position looking vacant.
        $first  = $this->member(1, '');
        $second = $this->member(2, 'nonsense');

        $views = $this->factoryWith([$first, $second])->createAll();

        $this->assertSame($first, $views[0]->getMember());
    }

    /** @test */
    public function a_position_with_no_members_yields_a_vacant_view(): void
    {
        $views = $this->factoryWith([])->createAll();

        $this->assertCount(1, $views);
        $this->assertNull($views[0]->getMember());
        $this->assertSame([], $views[0]->getMembers());
    }

    /** @test */
    public function a_single_member_is_used_directly(): void
    {
        $only = $this->member(1, '2027-01-01');

        $views = $this->factoryWith([$only])->createAll();

        $this->assertSame($only, $views[0]->getMember());
        $this->assertSame([$only], $views[0]->getMembers());
    }

    /** @test */
    public function create_from_also_resolves_the_latest_holder(): void
    {
        $outgoing = $this->member(1, '2025-01-01');
        $current  = $this->member(2, '2027-01-01');

        $view = $this->factoryWith([$outgoing, $current])->createFrom(self::POSITION_ID);

        $this->assertNotNull($view);
        $this->assertSame($current, $view->getMember());
    }

    // ─── view construction ──────────────────────────────────────────

    /** @test */
    public function a_member_whose_details_cannot_be_read_still_yields_a_view(): void
    {
        // The view reads contact details in a try/catch: one member with a
        // broken record must not take down a whole committee listing.
        $member = $this->createMock(Member::class);
        $member->method('getId')->willReturn(1);
        $member->method('getPersonalEmail')->willThrowException(new Exception('unreadable'));

        $view = new TsmlPositionView($this->position(), $member);

        $this->assertSame($member, $view->getMember());
        $this->assertNull($view->getRotationDate());
    }

    /** @test */
    public function a_view_without_a_rotation_date_reports_no_months_remaining(): void
    {
        $view = new TsmlPositionView($this->position(), $this->member(1, ''));

        $this->assertNull($view->getMonthsUntilRotation());
    }

    /** @test */
    public function a_future_rotation_date_reports_a_positive_month_count(): void
    {
        $future = (new \DateTime('today'))->modify('+13 months')->format('Y-m-d');

        $view = new TsmlPositionView($this->position(), $this->member(1, $future));

        $this->assertGreaterThan(0, $view->getMonthsUntilRotation());
    }

    /** @test */
    public function a_past_rotation_date_reports_a_negative_month_count(): void
    {
        $past = (new \DateTime('today'))->modify('-13 months')->format('Y-m-d');

        $view = new TsmlPositionView($this->position(), $this->member(1, $past));

        $this->assertLessThan(0, $view->getMonthsUntilRotation());
    }
}
