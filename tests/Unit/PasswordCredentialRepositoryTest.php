<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use LogicException;
use Unity\Auth\PasswordCredential;
use TsmlForUnity\Auth\TsmlPasswordCredentialRepository;
use TsmlForUnity\Tests\Support\WpdbStub;
use TsmlForUnity\Tests\TestCase;
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

/**
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
final class TsmlPasswordCredentialRepositoryTest extends TestCase
{
    private WpdbStub $wpdb;

    private TsmlPasswordCredentialRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new WpdbStub();

        /** @var wpdb $wpdb */
        $wpdb = $this->wpdb;
        $this->repository = new TsmlPasswordCredentialRepository($wpdb);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(string $email = 'member@example.test'): array
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

    /**
     * @test
     */
    public function it_names_its_table_from_the_site_prefix(): void
    {
        $this->wpdb->prefix = 'bd_';

        /** @var wpdb $wpdb */
        $wpdb = $this->wpdb;

        $this->assertSame('bd_unity_credentials', TsmlPasswordCredentialRepository::tableName($wpdb));
    }

    /**
     * @test
     */
    public function it_hydrates_a_credential_from_a_row(): void
    {
        $this->wpdb->nextRow = self::row();

        $credential = $this->repository->find('member@example.test');

        $this->assertInstanceOf(PasswordCredential::class, $credential);
        $this->assertSame('member@example.test', $credential->email);
        $this->assertSame(1800, $credential->resetExpiresAt);
        $this->assertSame(2, $credential->failedAttempts);
        $this->assertSame(1700, $credential->lockedUntil);
        $this->assertSame(1600, $credential->updatedAt);
        $this->assertTrue($credential->hasPassword());
    }

    /**
     * @test
     */
    public function it_answers_null_when_there_is_no_row(): void
    {
        $this->wpdb->nextRow = null;

        $this->assertNull($this->repository->find('nobody@example.test'));
    }

    /**
     * An empty hash would match every reset-free row in the table, which
     * would hand a credential to a request carrying no token at all.
     *
     * @test
     */
    public function it_refuses_an_empty_reset_token_hash_without_querying(): void
    {
        $this->wpdb->nextRow = self::row();

        $this->assertNull($this->repository->findByResetTokenHash(''));
        $this->assertSame([], $this->wpdb->queries);
    }

    /**
     * @test
     */
    public function it_finds_a_credential_by_reset_token_hash(): void
    {
        $this->wpdb->nextRow = self::row();

        $credential = $this->repository->findByResetTokenHash(str_repeat('a', 64));

        $this->assertInstanceOf(PasswordCredential::class, $credential);
        $this->assertStringContainsString('reset_token_hash = ', $this->wpdb->lastQuery());
    }

    /**
     * Setting a password is a clean slate: any pending reset token goes,
     * and so does any lockout. All three in one statement, so a crash
     * between them is not a state that can exist.
     *
     * @test
     */
    public function setting_a_password_also_clears_the_token_and_the_lockout(): void
    {
        $this->repository->upsertPasswordHash('member@example.test', 'hashed', 1234);

        $sql = $this->wpdb->lastQuery();

        $this->assertStringContainsString('INSERT INTO wp_unity_credentials', $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString("reset_token_hash = ''", $sql);
        $this->assertStringContainsString('reset_expires_at = 0', $sql);
        $this->assertStringContainsString('failed_attempts = 0', $sql);
        $this->assertStringContainsString('locked_until = 0', $sql);
    }

    /**
     * @test
     */
    public function storing_a_reset_token_leaves_the_password_alone(): void
    {
        $this->repository->storeResetToken('member@example.test', 'tokenhash', 2000, 1000);

        $sql = $this->wpdb->lastQuery();

        $this->assertStringContainsString('reset_token_hash = VALUES(reset_token_hash)', $sql);
        $this->assertStringNotContainsString('password_hash', $sql);
    }

    /**
     * @test
     */
    public function clearing_a_reset_token_empties_it(): void
    {
        $this->repository->clearResetToken('member@example.test', 1000);

        $sql = $this->wpdb->lastQuery();

        $this->assertStringContainsString('UPDATE wp_unity_credentials', $sql);
        $this->assertStringContainsString("reset_token_hash = ''", $sql);
    }

    /**
     * An UPDATE, never an upsert. An unknown email has no password to
     * guess, and creating a row for one would both leak that the address
     * is unknown and let an attacker seed the table.
     *
     * @test
     */
    public function a_failed_attempt_never_creates_a_row(): void
    {
        $this->repository->recordFailedAttempt('nobody@example.test', 3, 9999, 1000);

        $sql = $this->wpdb->lastQuery();

        $this->assertStringStartsWith('UPDATE wp_unity_credentials', trim($sql));
        $this->assertStringNotContainsString('INSERT', $sql);
    }

    /**
     * @test
     */
    public function a_successful_login_zeroes_the_counters(): void
    {
        $this->repository->resetFailedAttempts('member@example.test', 1000);

        $sql = $this->wpdb->lastQuery();

        $this->assertStringContainsString('failed_attempts = 0', $sql);
        $this->assertStringContainsString('locked_until = 0', $sql);
    }

    /**
     * @test
     */
    public function it_deletes_by_email(): void
    {
        $this->repository->delete('member@example.test');

        $this->assertSame(
            [['table' => 'wp_unity_credentials', 'where' => ['email' => 'member@example.test']]],
            $this->wpdb->deletes
        );
    }

    /**
     * <p>prepare() answers null only when the statement carries no
     * placeholders or the arguments do not match them — a coding error
     * rather than a runtime condition. These statements are the source of
     * truth for password sign-in, so the repository throws rather than
     * silently issuing no query and letting a caller believe a password or
     * a lockout counter had been stored.</p>
     *
     * @test
     * @dataProvider writeMethods
     * @param callable(TsmlPasswordCredentialRepository): void $write
     */
    public function every_write_refuses_to_run_on_an_unprepared_statement(callable $write): void
    {
        /** @var wpdb $wpdb */
        $wpdb = new NullPreparingWpdb();
        $repository = new TsmlPasswordCredentialRepository($wpdb);

        $this->expectException(LogicException::class);

        $write($repository);
    }

    /**
     * @return array<string, array{0: callable(TsmlPasswordCredentialRepository): void}>
     */
    public static function writeMethods(): array
    {
        return [
            'upsertPasswordHash'  => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->upsertPasswordHash('e', 'h', 1)],
            'storeResetToken'     => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->storeResetToken('e', 't', 2, 1)],
            'clearResetToken'     => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->clearResetToken('e', 1)],
            'recordFailedAttempt' => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->recordFailedAttempt('e', 1, 2, 3)],
            'resetFailedAttempts' => [static fn(TsmlPasswordCredentialRepository $r): mixed => $r->resetFailedAttempts('e', 1)],
        ];
    }
}
