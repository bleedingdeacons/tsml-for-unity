<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Support;

/**
 * A wpdb stand-in that records the SQL after prepare() has substituted the
 * bound values, so a test can assert on the statement a repository actually
 * chose to emit rather than on a placeholder template.
 *
 * <p>Separate from {@see FakeWpdb}, which is final and cannot be aliased to
 * `wpdb` for a constructor type-hint. TsmlPasswordCredentialRepository takes
 * a wpdb rather than reaching for the global, because every one of its
 * statements is a write to the store two apps sign in against and a test has
 * to be able to hand it one. The bootstrap aliases this class to `wpdb` when
 * WordPress is not loaded, which is the same trick Reach and Unity use for
 * their own repository tests.</p>
 */
class WpdbStub
{
    public string $prefix = 'wp_';

    /** @var array<int, string> */
    public array $queries = [];

    /** @var array<string, mixed>|null */
    public ?array $nextRow = null;

    /** @var array<int, array<string, mixed>> */
    public array $nextResults = [];

    public mixed $nextVar = null;

    /** @var array<int, array{table: string, where: array<string, mixed>}> */
    public array $deletes = [];

    public function get_charset_collate(): string
    {
        return '';
    }

    /**
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): ?string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $out = $query;

        foreach ($args as $a) {
            $repl = is_int($a) || (is_string($a) && ctype_digit($a))
                ? (string) (int) $a
                : "'" . str_replace("'", "''", (string) $a) . "'";
            $out = (string) preg_replace('/%[ds]/', $repl, $out, 1);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_row(string $sql, string $mode = 'ARRAY_A'): ?array
    {
        $this->queries[] = $sql;

        return $this->nextRow;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_results(string $sql, string $mode = 'ARRAY_A'): array
    {
        $this->queries[] = $sql;

        return $this->nextResults;
    }

    public function get_var(string $sql): mixed
    {
        $this->queries[] = $sql;

        return $this->nextVar;
    }

    public function query(string $sql): int
    {
        $this->queries[] = $sql;

        return 1;
    }

    /**
     * @param array<string, mixed> $where
     * @param array<int, string>|null $whereFormat
     */
    public function delete(string $table, array $where, ?array $whereFormat = null): int
    {
        $this->deletes[] = ['table' => $table, 'where' => $where];

        return 1;
    }

    public function lastQuery(): string
    {
        return $this->queries === [] ? '' : (string) end($this->queries);
    }
}
