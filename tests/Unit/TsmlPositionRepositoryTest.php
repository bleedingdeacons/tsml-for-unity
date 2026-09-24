<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Positions\TsmlPosition;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Positions\TsmlPositionRepository;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionFactory;

/*
 * Tests for TsmlPositionRepository's write paths.
 *
 * save() picks its branch on the ID — id 0 inserts, anything else
 * delegates to update() — and both branches gate on isValid(). These
 * tests pin that routing down, and pin it at the concrete-TsmlPosition
 * level as well as the Position-interface level: an interface mock can
 * stub getId() and isValid() into combinations the real class cannot
 * produce, which is exactly how the insert branch stayed dead-code
 * while looking covered.
 */

covers(\TsmlForUnity\Positions\TsmlPositionRepository::class);

beforeEach(function () {
    $this->factory = $this->createMock(PositionFactory::class);
    $this->repository = new TsmlPositionRepository($this->factory);
});

/**
 * Helper: a fully populated TsmlPosition — every isValid() rule met.
 *
 * @param int $id 0 builds an unsaved position bound for the insert path.
 */
function repositoryPosition(int $id = 0): TsmlPosition
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
function capturePositionUpdateField(string $field, &$captured): void
{
    Functions\expect('update_field')
        ->withArgs(function ($key) use ($field) {
            return $key === $field;
        })
        ->andReturnUsing(function ($key, $value) use (&$captured) {
            $captured = $value;
            return true;
        });

    // The other fields in the same save are not under test.
    Functions\expect('update_field')->andReturn(true);
}

// ─── save() insert path accepts a real, unsaved TsmlPosition ─────
test('save inserts a new unsaved tsml position', function () {
    $position = repositoryPosition(0);

    expect($position->isValid())->toBeTrue('A fully populated, unsaved position must be valid');

    Functions\expect('wp_insert_post')->once()->andReturn(4242);

    $written = null;
    capturePositionUpdateField(TsmlPositionFields::LONG_NAME, $written);

    expect($this->repository->save($position))->toBeTrue()
        ->and($written)->toBe('Treasurer');
});

/*
 * The ID is the repository's business, not the position's: an unsaved
 * position is still valid data.
 */
test('a new unsaved position is valid', function () {
    expect(repositoryPosition(0)->isValid())->toBeTrue();
});

/*
 * Dropping the ID rule must not weaken the data rules.
 */
test('save rejects a new position with incomplete data', function (TsmlPosition $position) {
    // No wp_insert_post expectation: a call would fail the test.
    expect($position->isValid())->toBeFalse()
        ->and($this->repository->save($position))->toBeFalse();
})->with([
    'no email' => [new TsmlPosition(0, 6, 1, '', 'Treasurer', 'Handles the money', 'Summary')],
    'no long name' => [new TsmlPosition(0, 6, 1, 'a@example.test', '', 'Handles the money', 'Summary')],
    'no short description' => [new TsmlPosition(0, 6, 1, 'a@example.test', 'Treasurer', '', 'Summary')],
    'no summary' => [new TsmlPosition(0, 6, 1, 'a@example.test', 'Treasurer', 'Handles the money', '')],
    'sobriety below 6' => [new TsmlPosition(0, 5, 1, 'a@example.test', 'Treasurer', 'Handles the money', 'Summary')],
    'term under 1 year' => [new TsmlPosition(0, 6, 0, 'a@example.test', 'Treasurer', 'Handles the money', 'Summary')],
]);

test('save returns false when wp insert post fails', function () {
    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_insert_post')->once()->andReturn($error);

    // No update_field expectation: the failure returns before any writes.

    expect($this->repository->save(repositoryPosition(0)))->toBeFalse();
});

// ─── save() with an existing ID delegates to update() ────────────
test('save with existing id delegates to update', function () {
    // Delegation is observed via wp_update_post being used rather
    // than wp_insert_post, which has no expectation here.
    $postId = 4242;

    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $written = null;
    capturePositionUpdateField(TsmlPositionFields::LONG_NAME, $written);

    expect($this->repository->save(repositoryPosition($postId)))->toBeTrue()
        ->and($written)->toBe('Treasurer');
});

// ─── update() path ──────────────────────────────────────────────
test('update writes fields for an existing position', function () {
    $postId = 4242;

    Functions\expect('wp_update_post')->once()->andReturn($postId);

    $written = null;
    capturePositionUpdateField(TsmlPositionFields::SUMMARY, $written);

    expect($this->repository->update(repositoryPosition($postId)))->toBeTrue()
        ->and($written)->toBe('Keeps the accounts and reports monthly');
});

/*
 * update() guards the ID itself rather than leaning on isValid(),
 * which is what lets isValid() stay silent about persistence.
 */
test('update returns false for zero post id without writing', function () {
    // Valid data, no ID: never reaches wp_update_post or update_field.
    $position = repositoryPosition(0);

    expect($position->isValid())->toBeTrue()
        ->and($this->repository->update($position))->toBeFalse();
});

test('update returns false for an invalid position', function () {
    // Real ID, but incomplete data — no wp_update_post expectation.
    $position = new TsmlPosition(4242, 6, 1, 'a@example.test', 'Treasurer', 'Handles the money', '');

    expect($this->repository->update($position))->toBeFalse();
});

test('update returns false when wp update post fails', function () {
    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_update_post')->once()->andReturn($error);

    expect($this->repository->update(repositoryPosition(4242)))->toBeFalse();
});

// ─── interface-level contract ───────────────────────────────────
/*
 * The repository is typed against Position, where identity and
 * validity are separate parts of the contract. An implementation
 * that reports id 0 but invalid must not be inserted.
 */
test('save returns false for invalid position without inserting', function () {
    // No wp_insert_post expectation: a call would fail the test.
    $position = $this->createMock(Position::class);
    $position->method('getId')->willReturn(0);
    $position->method('isValid')->willReturn(false);
    $position->method('getLongName')->willReturn('Treasurer');

    expect($this->repository->save($position))->toBeFalse();
});
