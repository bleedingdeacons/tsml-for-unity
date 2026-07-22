<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Members\TsmlMember;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionView;
use TsmlForUnity\Positions\TsmlPositionViewCollection;

/**
 * Tests for TsmlPositionViewCollection
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionViewCollection
 */
class TsmlPositionViewCollectionTest extends TestCase
{
    /**
     * @test
     */
    public function an_empty_collection_counts_zero(): void
    {
        $collection = new TsmlPositionViewCollection();

        $this->assertSame(0, $collection->count());
        $this->assertSame([], $collection->getAll());
    }

    /**
     * @test
     */
    public function it_separates_filled_from_vacant_positions(): void
    {
        $filled = $this->view('Chair', 'chair@example.com', member: $this->member('John D.'));
        $vacant = $this->view('Treasurer', 'treasurer@example.com');

        $collection = new TsmlPositionViewCollection([$filled, $vacant]);

        $this->assertSame([$filled], array_values($collection->getFilledPositions()->getAll()));
        $this->assertSame([$vacant], array_values($collection->getVacantPositions()->getAll()));
    }

    /**
     * @test
     */
    public function rotating_soon_selects_positions_within_the_window(): void
    {
        $soon = $this->view('Chair', 'c@example.com', member: $this->memberRotatingIn(10));
        $far  = $this->view('Sec', 's@example.com', member: $this->memberRotatingIn(400));
        $overdue = $this->view('Treas', 't@example.com', member: $this->memberRotatingIn(-5));

        $collection = new TsmlPositionViewCollection([$soon, $far, $overdue]);

        $rotatingSoon = array_values($collection->getPositionsRotatingSoon(30)->getAll());
        $this->assertSame([$soon], $rotatingSoon);
    }

    /**
     * @test
     */
    public function overdue_selects_positions_past_their_rotation_date(): void
    {
        $overdue = $this->view('Treas', 't@example.com', member: $this->memberRotatingIn(-5));
        $soon    = $this->view('Chair', 'c@example.com', member: $this->memberRotatingIn(10));

        $collection = new TsmlPositionViewCollection([$overdue, $soon]);

        $this->assertSame([$overdue], array_values($collection->getOverduePositions()->getAll()));
    }

    /**
     * @test
     */
    public function sort_by_days_until_rotation_puts_nearest_first_and_nulls_last(): void
    {
        $far     = $this->view('Far', 'f@example.com', member: $this->memberRotatingIn(400));
        $soon    = $this->view('Soon', 's@example.com', member: $this->memberRotatingIn(10));
        $noDate  = $this->view('None', 'n@example.com', member: $this->member('No Date'));

        $collection = new TsmlPositionViewCollection([$far, $noDate, $soon]);

        $sorted = $collection->sortByDaysUntilRotation()->getAll();

        $this->assertSame([$soon, $far, $noDate], $sorted);
    }

    /**
     * @test
     */
    public function sort_by_days_descending_reverses_the_order(): void
    {
        $far  = $this->view('Far', 'f@example.com', member: $this->memberRotatingIn(400));
        $soon = $this->view('Soon', 's@example.com', member: $this->memberRotatingIn(10));

        $collection = new TsmlPositionViewCollection([$soon, $far]);

        $this->assertSame([$far, $soon], $collection->sortByDaysUntilRotation(false)->getAll());
    }

    /**
     * @test
     */
    public function sort_by_name_orders_by_position_long_name(): void
    {
        $zebra = $this->view('Zebra', 'z@example.com', longName: 'Zebra');
        $alpha = $this->view('Alpha', 'a@example.com', longName: 'Alpha');

        $collection = new TsmlPositionViewCollection([$zebra, $alpha]);

        $this->assertSame([$alpha, $zebra], $collection->sortByName()->getAll());
        $this->assertSame([$zebra, $alpha], $collection->sortByName(false)->getAll());
    }

    /**
     * @test
     */
    public function sort_by_title_orders_by_short_description(): void
    {
        $b = $this->view('B title', 'b@example.com');
        $a = $this->view('A title', 'a@example.com');

        $collection = new TsmlPositionViewCollection([$b, $a]);

        $this->assertSame([$a, $b], $collection->sortByTitle()->getAll());
    }

    /**
     * @test
     */
    public function sort_by_email_orders_by_position_email(): void
    {
        $b = $this->view('Chair', 'b@example.com');
        $a = $this->view('Sec', 'a@example.com');

        $collection = new TsmlPositionViewCollection([$b, $a]);

        $this->assertSame([$a, $b], $collection->sortByEmail()->getAll());
        $this->assertSame([$b, $a], $collection->sortByEmail(false)->getAll());
    }

    /**
     * @test
     */
    public function filter_applies_an_arbitrary_predicate(): void
    {
        $filled = $this->view('Chair', 'c@example.com', member: $this->member('John'));
        $vacant = $this->view('Sec', 's@example.com');

        $collection = new TsmlPositionViewCollection([$filled, $vacant]);

        $result = $collection->filter(fn ($view) => !$view->isVacant());

        $this->assertSame(1, $result->count());
        $this->assertSame([$filled], array_values($result->getAll()));
    }

    private function view(
        string $title,
        string $email,
        ?TsmlMember $member = null,
        string $longName = ''
    ): TsmlPositionView {
        $position = new TsmlPosition(
            id: 1,
            email: $email,
            longName: $longName !== '' ? $longName : $title,
            shortDescription: $title,
            summary: 'summary',
        );

        return new TsmlPositionView($position, $member);
    }

    private function member(string $name): TsmlMember
    {
        return new TsmlMember(id: 1, anonymousName: $name);
    }

    private function memberRotatingIn(int $days): TsmlMember
    {
        $date = (new \DateTime('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');

        return new TsmlMember(id: 1, intergroupPositionRotation: $date);
    }
}
