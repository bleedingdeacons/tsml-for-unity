<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use function Brain\Monkey\Functions\when;
use TsmlForUnity\Auth\TsmlPasswordCredentialTable;
use TsmlForUnity\Tests\Support\WpdbStub;

/*
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
 */

covers(\TsmlForUnity\Auth\TsmlPasswordCredentialTable::class);

beforeEach(function () {
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $this->wpdb = new WpdbStub();
    $GLOBALS['wpdb'] = $this->wpdb;

    $GLOBALS['tsml_test_dbdelta'] = [];
    $this->storedOptions = [];

    when('esc_sql')->returnArg();
    when('get_option')
        ->alias(fn (string $name, $default = false) => $this->storedOptions[$name] ?? $default);
    when('update_option')
        ->alias(function (string $name, $value): bool {
            $this->storedOptions[$name] = $value;

            return true;
        });
    when('delete_option')
        ->alias(function (string $name): bool {
            unset($this->storedOptions[$name]);

            return true;
        });
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

/** The SQL dbDelta was last handed. */
function credentialLastDdl(): string
{
    $calls = $GLOBALS['tsml_test_dbdelta'] ?? [];

    return $calls === [] ? '' : (string) end($calls);
}

/** @return array<int, string> Every INSERT the absorb issued. */
function copies(): array
{
    return array_values(array_filter(
        test()->wpdb->queries,
        static fn (string $q): bool => str_contains($q, 'INSERT INTO wp_unity_credentials')
    ));
}

test('the table name is prefixed', function () {
    expect(TsmlPasswordCredentialTable::getTableName())->toBe('wp_unity_credentials');
});

it('creates the table keyed on the address', function () {
    TsmlPasswordCredentialTable::createTable();

    $ddl = credentialLastDdl();

    expect($ddl)->toContain('CREATE TABLE wp_unity_credentials')
        ->and($ddl)->toContain('PRIMARY KEY  (email)')
        ->and($ddl)->toContain('KEY reset_token_hash');
});

/*
 * Only hashes. A database dump alone must yield neither a usable
 * password nor a usable reset link.
 */
test('the schema holds no raw secret', function () {
    TsmlPasswordCredentialTable::createTable();

    $ddl = credentialLastDdl();

    expect($ddl)->toContain('password_hash')
        ->and($ddl)->toContain('reset_token_hash')
        ->and($ddl)->not->toContain('password VARCHAR')
        ->and($ddl)->not->toContain('reset_token VARCHAR');
});

test('creating records the schema version', function () {
    TsmlPasswordCredentialTable::createTable();

    expect($this->storedOptions[TsmlPasswordCredentialTable::DB_VERSION_OPTION] ?? null)->toBe(TsmlPasswordCredentialTable::DB_VERSION);
});

/*
 * The gate. This runs on every load.
 */
test('an upgrade is skipped when the version matches', function () {
    $this->storedOptions[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = TsmlPasswordCredentialTable::DB_VERSION;

    TsmlPasswordCredentialTable::maybeUpgrade();

    expect($GLOBALS['tsml_test_dbdelta'])->toBe([]);
});

test('an upgrade runs when the version differs', function () {
    $this->storedOptions[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = '0.9';

    TsmlPasswordCredentialTable::maybeUpgrade();

    expect(credentialLastDdl())->toContain('CREATE TABLE wp_unity_credentials');
});

/*
 * A fresh site, or one that only ever ran Fellowship, has one or
 * neither of the old tables. A missing one is silence, not a failure,
 * and must not produce a copy against a table that is not there.
 */
it('copies nothing when there is nothing to copy', function () {
    $this->wpdb->nextVar = null;

    TsmlPasswordCredentialTable::createTable();

    expect(copies())->toBe([]);
});

it('copies from an old table that exists', function () {
    // SHOW TABLES LIKE answers the name it was asked about.
    $this->wpdb->nextVar = 'wp_reach_credentials';

    TsmlPasswordCredentialTable::createTable();

    $copies = copies();

    expect($copies)->not->toBe([])
        ->and($copies[0])->toContain('FROM wp_reach_credentials');
});

/*
 * The newer row wins, in either direction, so running this twice or
 * with the tables swapped reaches the same answer. A member who set a
 * password in Reach and later in Fellowship holds two hashes, and
 * only one of them is the password they believe they have.
 */
test('the newer password wins', function () {
    $this->wpdb->nextVar = 'wp_reach_credentials';

    TsmlPasswordCredentialTable::createTable();

    $copy = copies()[0];

    expect($copy)->toContain('ON DUPLICATE KEY UPDATE')
        ->and($copy)->toContain('password_hash = IF(VALUES(updated_at) > wp_unity_credentials.updated_at')
        ->and($copy)->toContain('updated_at = GREATEST(wp_unity_credentials.updated_at, VALUES(updated_at))');
});

/*
 * This is the only copy of some members' passwords, and the upgrade
 * runs unattended on a page load. A bad one that has also destroyed
 * its source is not recoverable.
 */
it('never drops the tables it read', function () {
    $this->wpdb->nextVar = 'wp_reach_credentials';

    TsmlPasswordCredentialTable::createTable();

    foreach ($this->wpdb->queries as $query) {
        expect($query)->not->toMatch('/DROP TABLE/i')
            ->not->toMatch('/TRUNCATE/i');
    }

    expect($this->wpdb->deletes)->toBe([]);
});

/*
 * Dropping is only ever the uninstall path, and it is explicit.
 */
test('dropping removes the table and forgets the version', function () {
    $this->storedOptions[TsmlPasswordCredentialTable::DB_VERSION_OPTION] = TsmlPasswordCredentialTable::DB_VERSION;

    TsmlPasswordCredentialTable::dropTable();

    expect($this->wpdb->lastQuery())->toContain('DROP TABLE IF EXISTS')
        ->and($this->storedOptions)->not->toHaveKey(TsmlPasswordCredentialTable::DB_VERSION_OPTION);
});
