<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use LogicException;
use Unity\Auth\PasswordCredential;
use TsmlForUnity\Auth\TsmlPasswordCredentialRepository;
use TsmlForUnity\Tests\Support\WpdbStub;
use wpdb;

/**
 * A wpdb whose prepare() answers null, which the real one does when a
 * statement and its arguments disagree. Exists only to reach the guard
 * clauses on the write paths.
 */
final class NullPreparingWpdb extends WpdbStub
{
    /**
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): ?string
    {
        return null;
    }
}

/*
 * The shared member password store.
 *
 * <p>Unity declares the contract; this is the implementation behind it,
 * and these tests came with it from there. Reach and Fellowship each had
 * an identical copy of the same class and were asserting the same
 * statements twice.</p>
 *
 * <p>Read paths are asserted on what comes back; write paths on the SQL
 * that goes out, because there is no database here to read it back from.
 * The shape of that SQL is load-bearing in two places — the upsert clears
 * the reset token and the lockout in the same statement, and the
 * failed-attempt write is an UPDATE rather than an upsert — so both are
 * checked rather than assumed.</p>
 */

beforeEach(function () {
    $this->wpdb = new WpdbStub();

    /** @var wpdb $wpdb */
    $wpdb = $this->wpdb;
    $this->repository = new TsmlPasswordCredentialRepository($wpdb);
});

/**
 * @return array<string, mixed>
 */
function row(string $email = 'member@example.test'): array
{
    return [
        'email'            => $email,
        'password_hash'    => '$2y$10$abcdefghijklmnopqrstuv',
        'reset_token_hash' => str_repeat('a', 64),
        'reset_expires_at' => '1800',
        'failed_attempts'  => '2',
        'locked_until'     => '1700',
        'updated_at'       => '1600',
    ];
}

it('names its table from the site prefix', function () {
    $this->wpdb->prefix = 'bd_';

    /** @var wpdb $wpdb */
    $wpdb = $this->wpdb;

    expect(TsmlPasswordCredentialRepository::tableName($wpdb))->toBe('bd_unity_credentials');
});

it('hydrates a credential from a row', function () {
    $this->wpdb->nextRow = row();

    $credential = $this->repository->find('member@example.test');

    expect($credential)->toBeInstanceOf(PasswordCredential::class)
        ->and($credential->email)->toBe('member@example.test')
        ->and($credential->resetExpiresAt)->toBe(1800)
        ->and($credential->failedAttempts)->toBe(2)
        ->and($credential->lockedUntil)->toBe(1700)
        ->and($credential->updatedAt)->toBe(1600)
        ->and($credential->hasPassword())->toBeTrue();
});

it('answers null when there is no row', function () {
    $this->wpdb->nextRow = null;

    expect($this->repository->find('nobody@example.test'))->toBeNull();
});

/*
 * An empty hash would match every reset-free row in the table, which
 * would hand a credential to a request carrying no token at all.
 */
it('refuses an empty reset token hash without querying', function () {
    $this->wpdb->nextRow = row();

    expect($this->repository->findByResetTokenHash(''))->toBeNull()
        ->and($this->wpdb->queries)->toBe([]);
});

it('finds a credential by reset token hash', function () {
    $this->wpdb->nextRow = row();

    $credential = $this->repository->findByResetTokenHash(str_repeat('a', 64));

    expect($credential)->toBeInstanceOf(PasswordCredential::class)
        ->and($this->wpdb->lastQuery())->toContain('reset_token_hash = ');
});

/*
 * Setting a password is a clean slate: any pending reset token goes,
 * and so does any lockout. All three in one statement, so a crash
 * between them is not a state that can exist.
 */
test('setting a password also clears the token and the lockout', function () {
    $this->repository->upsertPasswordHash('member@example.test', 'hashed', 1234);

    $sql = $this->wpdb->lastQuery();

    expect($sql)->toContain('INSERT INTO wp_unity_credentials')
        ->and($sql)->toContain('ON DUPLICATE KEY UPDATE')
        ->and($sql)->toContain("reset_token_hash = ''")
        ->and($sql)->toContain('reset_expires_at = 0')
        ->and($sql)->toContain('failed_attempts = 0')
        ->and($sql)->toContain('locked_until = 0');
});

test('storing a reset token leaves the password alone', function () {
    $this->repository->storeResetToken('member@example.test', 'tokenhash', 2000, 1000);

    $sql = $this->wpdb->lastQuery();

    expect($sql)->toContain('reset_token_hash = VALUES(reset_token_hash)')
        ->and($sql)->not->toContain('password_hash');
});

test('clearing a reset token empties it', function () {
    $this->repository->clearResetToken('member@example.test', 1000);

    $sql = $this->wpdb->lastQuery();

    expect($sql)->toContain('UPDATE wp_unity_credentials')
        ->and($sql)->toContain("reset_token_hash = ''");
});

/*
 * An UPDATE, never an upsert. An unknown email has no password to
 * guess, and creating a row for one would both leak that the address
 * is unknown and let an attacker seed the table.
 */
test('a failed attempt never creates a row', function () {
    $this->repository->recordFailedAttempt('nobody@example.test', 3, 9999, 1000);

    $sql = $this->wpdb->lastQuery();

    expect(trim($sql))->toStartWith('UPDATE wp_unity_credentials')
        ->and($sql)->not->toContain('INSERT');
});

test('a successful login zeroes the counters', function () {
    $this->repository->resetFailedAttempts('member@example.test', 1000);

    $sql = $this->wpdb->lastQuery();

    expect($sql)->toContain('failed_attempts = 0')
        ->and($sql)->toContain('locked_until = 0');
});

it('deletes by email', function () {
    $this->repository->delete('member@example.test');

    expect($this->wpdb->deletes)->toBe([['table' => 'wp_unity_credentials', 'where' => ['email' => 'member@example.test']]]);
});

/*
 * <p>prepare() answers null only when the statement carries no
 * placeholders or the arguments do not match them — a coding error
 * rather than a runtime condition. These statements are the source of
 * truth for password sign-in, so the repository throws rather than
 * silently issuing no query and letting a caller believe a password or
 * a lockout counter had been stored.</p>
 *
 * @param callable(TsmlPasswordCredentialRepository): void $write
 */
test('every write refuses to run on an unprepared statement', function (callable $write) {
    /** @var wpdb $wpdb */
    $wpdb = new NullPreparingWpdb();
    $repository = new TsmlPasswordCredentialRepository($wpdb);

    $write($repository);
})->with([
    'upsertPasswordHash'  => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->upsertPasswordHash('e', 'h', 1)],
    'storeResetToken'     => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->storeResetToken('e', 't', 2, 1)],
    'clearResetToken'     => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->clearResetToken('e', 1)],
    'recordFailedAttempt' => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->recordFailedAttempt('e', 1, 2, 3)],
    'resetFailedAttempts' => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->resetFailedAttempts('e', 1)],
])->throws(LogicException::class);
