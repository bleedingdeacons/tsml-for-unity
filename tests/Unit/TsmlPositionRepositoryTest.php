<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Positions\TsmlPositionRepository;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionFactory;
use WP_Mock;

/**
 * Tests for TsmlPositionRepository's write paths.
 *
 * save() picks its branch on the ID — id 0 inserts, anything else
 * delegates to update() — and both branches gate on isValid(). These
 * tests pin that routing down, and pin it at the concrete-TsmlPosition
 * level as well as the Position-interface level: an interface mock can
 * stub getId() and isValid() into combinations the real class cannot
 * produce, which is exactly how the insert branch stayed dead-code
 * while looking covered.
 *
 * @covers \TsmlForUnity\Positions\TsmlPositionRepository
 */
class TsmlPositionRepositoryTest extends TestCase
{
    /** @var PositionFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlPositionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(PositionFactory::class);
        $this->repository = new TsmlPositionRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Helper: a fully populated TsmlPosition — every isValid() rule met.
     *
     * @param int $id 0 builds an unsaved position bound for the insert path.
     */
    private function position(int $id = 0): TsmlPosition
    {
        return new TsmlPosition(
            $id,
            6,
            1,
            'treasurer@example.test',
            'Treasurer',
            'Handles the money',
            'Keeps the accounts and reports monthly'
        );
    }

    /**
     * Helper: capture the value written to a given ACF field.
     *
     * update_field() is called once per field per save; this narrows to
     * one field and records what it received.
     */
    private function captureUpdateField(string $field, &$captured): void
    {
        WP_Mock::userFunction('update_field')
            ->withArgs(function ($key) use ($field) {
                return $key === $field;
            })
            ->andReturnUsing(function ($key, $value) use (&$captured) {
                $captured = $value;
                return true;
            });

        // The other fields in the same save are not under test.
        WP_Mock::userFunction('update_field')->andReturn(true);
    }

    // ─── save() insert path accepts a real, unsaved TsmlPosition ─────

    /**
     * @test
     */
    public function save_inserts_a_new_unsaved_tsml_position(): void
    {
        $position = $this->position(0);

        $this->assertTrue($position->isValid(), 'A fully populated, unsaved position must be valid');

        WP_Mock::userFunction('wp_insert_post')->once()->andReturn(4242);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlPositionFields::LONG_NAME, $written);

        $this->assertTrue($this->repository->save($position));
        $this->assertSame('Treasurer', $written);
    }

    /**
     * The ID is the repository's business, not the position's: an unsaved
     * position is still valid data.
     *
     * @test
     */
    public function a_new_unsaved_position_is_valid(): void
    {
        $this->assertTrue($this->position(0)->isValid());
    }

    /**
     * Dropping the ID rule must not weaken the data rules.
     *
     * @test
     * @dataProvider incompletePositions
     */
    public function save_rejects_a_new_position_with_incomplete_data(TsmlPosition $position): void
    {
        // No wp_insert_post expectation: a call would fail the test.
        $this->assertFalse($position->isValid());
        $this->assertFalse($this->repository->save($position));
    }

    /**
     * @return array<string, array{TsmlPosition}>
     */
    public function incompletePositions(): array
    {
        return [
            'no email' => [new TsmlPosition(0, 6, 1, '', 'Treasurer', 'Handles the money', 'Summary')],
            'no long name' => [new TsmlPosition(0, 6, 1, 'a@example.test', '', 'Handles the money', 'Summary')],
            'no short description' => [new TsmlPosition(0, 6, 1, 'a@example.test', 'Treasurer', '', 'Summary')],
            'no summary' => [new TsmlPosition(0, 6, 1, 'a@example.test', 'Treasurer', 'Handles the money', '')],
            'sobriety below 6' => [new TsmlPosition(0, 5, 1, 'a@example.test', 'Treasurer', 'Handles the money', 'Summary')],
            'term under 1 year' => [new TsmlPosition(0, 6, 0, 'a@example.test', 'Treasurer', 'Handles the money', 'Summary')],
        ];
    }

    /**
     * @test
     */
    public function save_returns_false_when_wp_insert_post_fails(): void
    {
        $error = new \stdClass();
        WP_Mock::userFunction('wp_insert_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        // No update_field expectation: the failure returns before any writes.

        $this->assertFalse($this->repository->save($this->position(0)));
    }

    // ─── save() with an existing ID delegates to update() ────────────

    /**
     * @test
     */
    public function save_with_existing_id_delegates_to_update(): void
    {
        // Delegation is observed via wp_update_post being used rather
        // than wp_insert_post, which has no expectation here.
        $postId = 4242;

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlPositionFields::LONG_NAME, $written);

        $this->assertTrue($this->repository->save($this->position($postId)));
        $this->assertSame('Treasurer', $written);
    }

    // ─── update() path ──────────────────────────────────────────────

    /**
     * @test
     */
    public function update_writes_fields_for_an_existing_position(): void
    {
        $postId = 4242;

        WP_Mock::userFunction('wp_update_post')->once()->andReturn($postId);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);

        $written = null;
        $this->captureUpdateField(TsmlPositionFields::SUMMARY, $written);

        $this->assertTrue($this->repository->update($this->position($postId)));
        $this->assertSame('Keeps the accounts and reports monthly', $written);
    }

    /**
     * update() guards the ID itself rather than leaning on isValid(),
     * which is what lets isValid() stay silent about persistence.
     *
     * @test
     */
    public function update_returns_false_for_zero_post_id_without_writing(): void
    {
        // Valid data, no ID: never reaches wp_update_post or update_field.
        $position = $this->position(0);

        $this->assertTrue($position->isValid());
        $this->assertFalse($this->repository->update($position));
    }

    /**
     * @test
     */
    public function update_returns_false_for_an_invalid_position(): void
    {
        // Real ID, but incomplete data — no wp_update_post expectation.
        $position = new TsmlPosition(4242, 6, 1, 'a@example.test', 'Treasurer', 'Handles the money', '');

        $this->assertFalse($this->repository->update($position));
    }

    /**
     * @test
     */
    public function update_returns_false_when_wp_update_post_fails(): void
    {
        $error = new \stdClass();
        WP_Mock::userFunction('wp_update_post')->once()->andReturn($error);
        WP_Mock::userFunction('is_wp_error')->with($error)->andReturn(true);

        $this->assertFalse($this->repository->update($this->position(4242)));
    }

    // ─── interface-level contract ───────────────────────────────────

    /**
     * The repository is typed against Position, where identity and
     * validity are separate parts of the contract. An implementation
     * that reports id 0 but invalid must not be inserted.
     *
     * @test
     */
    public function save_returns_false_for_invalid_position_without_inserting(): void
    {
        // No wp_insert_post expectation: a call would fail the test.
        $position = $this->createMock(Position::class);
        $position->method('getId')->willReturn(0);
        $position->method('isValid')->willReturn(false);
        $position->method('getLongName')->willReturn('Treasurer');

        $this->assertFalse($this->repository->save($position));
    }
}
