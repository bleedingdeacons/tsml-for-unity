<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable;
use TsmlForUnity\Tests\Support\FakeWpdb;
use TsmlForUnity\Tests\TestCase;

/**
 * Tests for the custom attendance tables' install/upgrade lifecycle.
 *
 * Both registers live in their own tables, created through dbDelta() and
 * gated on a stored schema version. The behaviour worth pinning is the
 * gate: maybeUpgrade() must run the DDL when the recorded version differs
 * and stay out of the way when it matches, because it is called on every
 * load and an unguarded dbDelta() on each request would be expensive.
 */
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::class)]
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::class)]
class AttendanceTableInstallerTest extends TestCase
{
    private FakeWpdb $wpdb;
    private $previousWpdb;

    /** Option values seen by the stubbed get_option(). */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $GLOBALS['tsml_test_dbdelta'] = [];
        $this->options = [];

        expect('esc_sql')->andReturnUsing(static fn ($v) => $v);
        expect('get_option')
            ->andReturnUsing(fn (string $name, $default = false) => $this->options[$name] ?? $default);
        expect('update_option')
            ->andReturnUsing(function (string $name, $value): bool {
                $this->options[$name] = $value;

                return true;
            });
        expect('delete_option')
            ->andReturnUsing(function (string $name): bool {
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

    // ══ group attendance table ════════════════════════════════════════
    #[Test]
    public function the_group_table_name_is_prefixed(): void
    {
        $this->assertStringStartsWith('wp_', TsmlIntergroupMeetingGroupAttendanceTable::getTableName());
    }

    #[Test]
    public function creating_the_group_table_issues_ddl_and_records_the_version(): void
    {
        TsmlIntergroupMeetingGroupAttendanceTable::createTable();

        $ddl = $this->lastDdl();
        $this->assertStringContainsString('CREATE TABLE', $ddl);
        $this->assertStringContainsString('intergroup_meeting_id', $ddl);
        $this->assertStringContainsString('group_id', $ddl);
        // A group may only appear once per meeting.
        $this->assertStringContainsString('UNIQUE KEY', $ddl);

        $this->assertNotEmpty($this->options, 'The schema version should be stored.');
    }

    #[Test]
    public function dropping_the_group_table_removes_the_table_and_its_version(): void
    {
        TsmlIntergroupMeetingGroupAttendanceTable::createTable();
        TsmlIntergroupMeetingGroupAttendanceTable::dropTable();

        $this->assertStringContainsString('DROP TABLE IF EXISTS', $this->wpdb->lastQuery());
        $this->assertSame([], $this->options, 'The stored version should be cleared.');
    }

    #[Test]
    public function the_group_table_is_created_when_no_version_is_recorded(): void
    {
        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

        $this->assertStringContainsString('CREATE TABLE', $this->lastDdl());
    }

    #[Test]
    public function the_group_table_upgrade_is_skipped_when_the_version_matches(): void
    {
        // First call installs and records the version.
        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
        $GLOBALS['tsml_test_dbdelta'] = [];

        // Second call sees a matching version and should do nothing.
        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

        $this->assertSame([], $GLOBALS['tsml_test_dbdelta'], 'dbDelta should not run again.');
    }

    #[Test]
    public function a_stale_group_table_version_triggers_an_upgrade(): void
    {
        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();
        // Pretend an older release installed the table.
        foreach (array_keys($this->options) as $key) {
            $this->options[$key] = '0.0.1';
        }
        $GLOBALS['tsml_test_dbdelta'] = [];

        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

        $this->assertStringContainsString('CREATE TABLE', $this->lastDdl());
    }

    // ══ officer attendance table ══════════════════════════════════════
    #[Test]
    public function the_officer_table_name_is_prefixed(): void
    {
        $this->assertStringStartsWith('wp_', TsmlIntergroupMeetingOfficerAttendanceTable::getTableName());
    }

    #[Test]
    public function creating_the_officer_table_issues_ddl_and_records_the_version(): void
    {
        TsmlIntergroupMeetingOfficerAttendanceTable::createTable();

        $ddl = $this->lastDdl();
        $this->assertStringContainsString('CREATE TABLE', $ddl);
        $this->assertStringContainsString('officer_id', $ddl);
        $this->assertStringContainsString('position_name', $ddl);
        $this->assertStringContainsString('UNIQUE KEY uq_meeting_officer', $ddl);

        $this->assertNotEmpty($this->options);
    }

    #[Test]
    public function dropping_the_officer_table_removes_the_table_and_its_version(): void
    {
        TsmlIntergroupMeetingOfficerAttendanceTable::createTable();
        TsmlIntergroupMeetingOfficerAttendanceTable::dropTable();

        $this->assertStringContainsString('DROP TABLE IF EXISTS', $this->wpdb->lastQuery());
        $this->assertSame([], $this->options);
    }

    #[Test]
    public function the_officer_table_is_created_when_no_version_is_recorded(): void
    {
        TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();

        $this->assertStringContainsString('CREATE TABLE', $this->lastDdl());
    }

    #[Test]
    public function the_officer_table_upgrade_is_skipped_when_the_version_matches(): void
    {
        TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();
        $GLOBALS['tsml_test_dbdelta'] = [];

        TsmlIntergroupMeetingOfficerAttendanceTable::maybeUpgrade();

        $this->assertSame([], $GLOBALS['tsml_test_dbdelta']);
    }
}
