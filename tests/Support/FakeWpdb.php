<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Support;

/**
 * A recording stand-in for WordPress's global $wpdb.
 *
 * The custom-table repositories build SQL by hand and hand it to $wpdb, so
 * the interesting behaviour is *what they ask the database for* — which
 * filters became WHERE clauses, whether ORDER BY was whitelisted, whether a
 * save became an INSERT or an UPDATE. This double therefore records every
 * call and lets a test queue the rows to hand back, so those questions can
 * be asked directly rather than inferred.
 *
 * prepare() interpolates naively — enough for assertions about the shape of
 * a statement, without pretending to be WordPress's escaping.
 */
final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';

    /** Every statement passed to a query method, in order. */
    public array $queries = [];

    /** Rows returned by get_results(). */
    public array $results = [];

    /** Row returned by get_row(); null means "not found". */
    public ?array $row = null;

    /** Scalar returned by get_var(). */
    public mixed $var = '0';

    /** Return value handed back by insert(); false simulates a failure. */
    public mixed $insertResult = 1;

    /** Return value handed back by update(); false simulates a failure. */
    public mixed $updateResult = 1;

    /** Return value handed back by delete(); false simulates a failure. */
    public mixed $deleteResult = 1;

    /** Id reported after an insert. */
    public int $insert_id = 123;

    /** Calls to insert(), as [table, data, formats]. */
    public array $inserts = [];

    /** Calls to update(), as [table, data, where, formats, whereFormats]. */
    public array $updates = [];

    /** Calls to delete(), as [table, where, formats]. */
    public array $deletes = [];

    public function prepare(string $query, ...$args): string
    {
        // Flatten a single array argument, matching how callers spread values.
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg) ? (string) $arg : "'" . $arg . "'";
            $query = preg_replace('/%[sdf]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function get_row(string $query, $output = null): ?array
    {
        $this->queries[] = $query;

        return $this->row;
    }

    public function get_results(string $query, $output = null): array
    {
        $this->queries[] = $query;

        return $this->results;
    }

    public function get_var(string $query): mixed
    {
        $this->queries[] = $query;

        return $this->var;
    }

    public function query(string $query): int
    {
        $this->queries[] = $query;

        return 1;
    }

    public function insert(string $table, array $data, $formats = null): mixed
    {
        $this->inserts[] = [$table, $data, $formats];

        return $this->insertResult;
    }

    public function update(string $table, array $data, array $where, $formats = null, $whereFormats = null): mixed
    {
        $this->updates[] = [$table, $data, $where, $formats, $whereFormats];

        return $this->updateResult;
    }

    public function delete(string $table, array $where, $formats = null): mixed
    {
        $this->deletes[] = [$table, $where, $formats];

        return $this->deleteResult;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    /** The most recent statement, for terse assertions. */
    public function lastQuery(): string
    {
        return $this->queries === [] ? '' : (string) end($this->queries);
    }
}
