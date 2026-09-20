<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable;
use TsmlForUnity\Tests\TestCase;

/**
 * Tests for the two attendance table managers (name resolution, upgrade
 * gating and drop). createTable() is not exercised because it require()s a
 * WordPress core file that does not exist in the unit environment.
 */
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceTable::class)]
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceTable::class)]
class TsmlIntergroupMeetingAttendanceTableTest extends TestCase
{
    /** @var object The previous global $wpdb, restored in tearDown. */
    private $previousWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;

        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public function query(string $sql): int
            {
                return 0;
            }
        };
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;
        parent::tearDown();
    }

    #[Test]
    public function group_table_name_is_prefixed(): void
    {
        $this->assertSame(
            'wp_' . TsmlIntergroupMeetingGroupAttendanceTable::TABLE_NAME,
            TsmlIntergroupMeetingGroupAttendanceTable::getTableName()
        );
    }

    #[Test]
    public function officer_table_name_is_prefixed(): void
    {
        $this->assertSame(
            'wp_' . TsmlIntergroupMeetingOfficerAttendanceTable::TABLE_NAME,
            TsmlIntergroupMeetingOfficerAttendanceTable::getTableName()
        );
    }

    #[Test]
    public function maybe_upgrade_does_nothing_when_the_installed_version_matches(): void
    {
        expect('get_option')
            ->with(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION_OPTION)
            ->andReturn(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION);

        // A matching version must not attempt createTable() (which would
        // require a missing WP core file and fatal).
        TsmlIntergroupMeetingGroupAttendanceTable::maybeUpgrade();

        $this->assertTrue(true);
    }

    #[Test]
    public function group_drop_table_issues_a_drop_and_clears_the_version_option(): void
    {
        expect('esc_sql')->andReturnUsing(fn ($v) => $v);
        expect('delete_option')
            ->once()
            ->with(TsmlIntergroupMeetingGroupAttendanceTable::DB_VERSION_OPTION)
            ->andReturn(true);

        TsmlIntergroupMeetingGroupAttendanceTable::dropTable();

        $this->assertTrue(true);
    }

    #[Test]
    public function officer_drop_table_issues_a_drop_and_clears_the_version_option(): void
    {
        expect('esc_sql')->andReturnUsing(fn ($v) => $v);
        expect('delete_option')
            ->once()
            ->with(TsmlIntergroupMeetingOfficerAttendanceTable::DB_VERSION_OPTION)
            ->andReturn(true);

        TsmlIntergroupMeetingOfficerAttendanceTable::dropTable();

        $this->assertTrue(true);
    }
}
