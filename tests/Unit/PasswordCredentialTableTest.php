<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Auth\TsmlPasswordCredentialTable;
use TsmlForUnity\Tests\Support\WpdbStub;
use TsmlForUnity\Tests\TestCase;

/**
 * Tests for the member credentials table's install/upgrade lifecycle.
 *
 * <p>The gate is the same one the attendance tables have, and matters for
 * the same reason: maybeUpgrade() runs on every load, and an unguarded
 * dbDelta() per request would be expensive.</p>
 *
 * <p>What is new here is the absorb. This table replaces two
 * plugin-private ones — Reach's and Fellowship's — that held the same
 * schema and could hold different passwords for the same member. The
 * copy has to be ordered by <code>updated_at</code> rather than by
 * whichever table happens to be read second, and it must never destroy
 * what it read.</p>
 *
 * @covers \TsmlForUnity\Auth\TsmlPasswordCredentialTable
 */
class PasswordCredentialTableTest extends TestCase
{
    private WpdbStub $wpdb;

    private mixed $previousWpdb = null;

    /** Option values seen by the stubbed get_option(). */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new WpdbStub();
        $GLOBALS['wpdb'] = $this->wpdb;

        $GLOBALS['tsml_test_dbdelta'] = [];
        $this->options = [];

        Functions\when('esc_sql')->returnArg();
        Functions\when('get_option')
            ->alias(fn (string $name, $default = false) => $this->options[$name] ?? $default);
        Functions\when('update_option')
            ->alias(function (string $name, $value): bool {
                $this->options[$name] = $value;

                return true;
            });
        Functions\when('delete_option')
            ->alias(function (string $name): bool {
                unset($this->options[$name]);

                return true;
            });
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;

        parent::tearDown();
    }

    /** The SQL dbDelta was last handed. */
    private function lastDdl(): string
    {
        $calls = $GLOBALS['tsml_test_dbdelta'] ?? [];

        return $calls === [] ? '' : (string) end($calls);
    }

    /** @return array<int, string> Every INSERT the absorb issued. */
    private function copies(): array
    {
        return array_values(array_filter(
            $this->wpdb->queries,
            static fn (string $q): bool => str_contains($q, 'INSERT INTO wp_unity_credentials')
        ));
    }

    /** @test */
    public function the_table_name_is_prefixed(): void
    {
        $this->assertSame('wp_unity_credentials', TsmlPasswordCredentialTable::getTableName());
    }

    /** @test */
    public function it_creates_the_table_keyed_on_the_address(): void
    {
        TsmlPasswordCredentialTable::createTable();

        $ddl = $this->lastDdl();

        $this->assertStringContainsString('CREATE TABLE wp_unity_credentials', $ddl);
        $this->assertStringContainsString('PRIMARY KEY  (email)', $ddl);
        $this->assertStringContainsString('KEY reset_token_hash', $ddl);
    }

    /**
     * Only hashes. A database dump alone must yield neither a usable
     * password nor a usable reset link.
     *
     * @test
     */
    public function the_schema_holds_no_raw_secret(): void
    {
        TsmlPasswordCredentialTable::createTable();

        $ddl = $this->lastDdl();

        $this->assertStringContainsString('password_hash', $ddl);
        $this->assertStringContainsString('reset_token_hash', $ddl);
        $this->assertStringNotContainsString('password VARCHAR', $ddl);
        $this->assertStringNotContainsString('reset_token VARCHAR', $ddl);
    }

    /** @test */
    public function creating_records_the_schema_version(): void
    {
        TsmlPasswordCredentialTable::createTable();

        $this->assertSame(
            TsmlPasswordCredentialTable::DB_VERSION,
            $this->options[TsmlPasswordCredentialTable::DB_VERSION_OPTION] ?? null
        );
    }

    /**
     * The gate. This runs on every load.
     *
     * @test
     */
    public function an_upgrade_is_skipped_when_the_version_matches(): void
    {
        $this->options[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = TsmlPasswordCredentialTable::DB_VERSION;

        TsmlPasswordCredentialTable::maybeUpgrade();

        $this->assertSame([], $GLOBALS['tsml_test_dbdelta']);
    }

    /** @test */
    public function an_upgrade_runs_when_the_version_differs(): void
    {
        $this->options[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = '0.9';

        TsmlPasswordCredentialTable::maybeUpgrade();

        $this->assertStringContainsString('CREATE TABLE wp_unity_credentials', $this->lastDdl());
    }

    /**
     * A fresh site, or one that only ever ran Fellowship, has one or
     * neither of the old tables. A missing one is silence, not a failure,
     * and must not produce a copy against a table that is not there.
     *
     * @test
     */
    public function it_copies_nothing_when_there_is_nothing_to_copy(): void
    {
        $this->wpdb->nextVar = null;

        TsmlPasswordCredentialTable::createTable();

        $this->assertSame([], $this->copies());
    }

    /** @test */
    public function it_copies_from_an_old_table_that_exists(): void
    {
        // SHOW TABLES LIKE answers the name it was asked about.
        $this->wpdb->nextVar = 'wp_reach_credentials';

        TsmlPasswordCredentialTable::createTable();

        $copies = $this->copies();

        $this->assertNotSame([], $copies);
        $this->assertStringContainsString('FROM wp_reach_credentials', $copies[0]);
    }

    /**
     * The newer row wins, in either direction, so running this twice or
     * with the tables swapped reaches the same answer. A member who set a
     * password in Reach and later in Fellowship holds two hashes, and
     * only one of them is the password they believe they have.
     *
     * @test
     */
    public function the_newer_password_wins(): void
    {
        $this->wpdb->nextVar = 'wp_reach_credentials';

        TsmlPasswordCredentialTable::createTable();

        $copy = $this->copies()[0];

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $copy);
        $this->assertStringContainsString(
            'password_hash = IF(VALUES(updated_at) > wp_unity_credentials.updated_at',
            $copy
        );
        $this->assertStringContainsString(
            'updated_at = GREATEST(wp_unity_credentials.updated_at, VALUES(updated_at))',
            $copy
        );
    }

    /**
     * This is the only copy of some members' passwords, and the upgrade
     * runs unattended on a page load. A bad one that has also destroyed
     * its source is not recoverable.
     *
     * @test
     */
    public function it_never_drops_the_tables_it_read(): void
    {
        $this->wpdb->nextVar = 'wp_reach_credentials';

        TsmlPasswordCredentialTable::createTable();

        foreach ($this->wpdb->queries as $query) {
            $this->assertStringNotContainsStringIgnoringCase('DROP TABLE', $query);
            $this->assertStringNotContainsStringIgnoringCase('TRUNCATE', $query);
        }

        $this->assertSame([], $this->wpdb->deletes);
    }

    /**
     * Dropping is only ever the uninstall path, and it is explicit.
     *
     * @test
     */
    public function dropping_removes_the_table_and_forgets_the_version(): void
    {
        $this->options[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = TsmlPasswordCredentialTable::DB_VERSION;

        TsmlPasswordCredentialTable::dropTable();

        $this->assertStringContainsString('DROP TABLE IF EXISTS', $this->wpdb->lastQuery());
        $this->assertArrayNotHasKey(TsmlPasswordCredentialTable::DB_VERSION_OPTION, $this->options);
    }
}
