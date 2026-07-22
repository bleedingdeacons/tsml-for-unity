<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingGroupAttendanceFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingOfficerAttendanceFactory;

/**
 * Tests for the two intergroup-meeting attendance factories.
 *
 * Both read a row from a custom table via the global $wpdb, hydrate a value
 * object from it, and offer a createNew() for unsaved rows.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingGroupAttendanceFactory
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingOfficerAttendanceFactory
 */
class TsmlIntergroupMeetingAttendanceFactoryTest extends TestCase
{
    /** @var object The previous global $wpdb, restored in tearDown. */
    private $previousWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['wpdb'] = $this->previousWpdb;
        parent::tearDown();
    }

    /**
     * A tiny $wpdb double: prepare() interpolates naively, get_row() returns
     * whatever the test queued.
     *
     * @param array<string,mixed>|null $row
     */
    private function installWpdb(?array $row): void
    {
        $GLOBALS['wpdb'] = new class ($row) {
            public string $prefix = 'wp_';
            /** @var array<string,mixed>|null */
            private $row;
            public function __construct($row)
            {
                $this->row = $row;
            }
            public function prepare(string $query, ...$args): string
            {
                return $query;
            }
            /** @return array<string,mixed>|null */
            public function get_row($query, $output = null)
            {
                return $this->row;
            }
        };
    }

    // ─── group attendance ───────────────────────────────────────────

    /**
     * @test
     */
    public function group_factory_implements_the_interface(): void
    {
        $this->assertInstanceOf(
            IntergroupMeetingGroupAttendanceFactory::class,
            new TsmlIntergroupMeetingGroupAttendanceFactory()
        );
    }

    /**
     * @test
     */
    public function group_create_from_source_hydrates_from_a_row(): void
    {
        $this->installWpdb([
            'id'                    => '5',
            'intergroup_meeting_id' => '42',
            'meeting_label'         => 'July',
            'group_id'              => '10',
            'member_id'             => '7',
            'meeting_group'         => 'Tuesday Group',
            'gsr_name'              => 'Alice A.',
            'gsr_proxy'             => '1',
            'gsr_proxy_name'        => 'Bob B.',
        ]);

        $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createFromSource(5);

        $this->assertSame(5, $attendance->getId());
        $this->assertSame(42, $attendance->getIntergroupMeetingId());
        $this->assertSame('July', $attendance->getMeetingLabel());
        $this->assertSame(10, $attendance->getGroupId());
        $this->assertSame(7, $attendance->getMemberId());
        $this->assertSame('Tuesday Group', $attendance->getMeetingGroup());
        $this->assertSame('Alice A.', $attendance->getGsrName());
        $this->assertTrue($attendance->isGsrProxy());
        $this->assertSame('Bob B.', $attendance->getGsrProxyName());
    }

    /**
     * @test
     */
    public function group_create_from_source_returns_an_empty_object_for_a_missing_row(): void
    {
        $this->installWpdb(null);

        $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createFromSource(99);

        $this->assertSame(99, $attendance->getId());
        $this->assertSame(0, $attendance->getGroupId());
        $this->assertFalse($attendance->isGsrProxy());
    }

    /**
     * @test
     */
    public function group_create_new_builds_an_unsaved_row(): void
    {
        $attendance = (new TsmlIntergroupMeetingGroupAttendanceFactory())->createNew(
            42,
            'July',
            10,
            7,
            'Tuesday Group',
            'Alice A.',
            true,
            'Bob B.'
        );

        $this->assertSame(0, $attendance->getId());
        $this->assertSame(42, $attendance->getIntergroupMeetingId());
        $this->assertSame('Alice A.', $attendance->getGsrName());
        $this->assertTrue($attendance->isGsrProxy());
        $this->assertSame('Bob B.', $attendance->getGsrProxyName());
    }

    // ─── officer attendance ─────────────────────────────────────────

    /**
     * @test
     */
    public function officer_factory_implements_the_interface(): void
    {
        $this->assertInstanceOf(
            IntergroupMeetingOfficerAttendanceFactory::class,
            new TsmlIntergroupMeetingOfficerAttendanceFactory()
        );
    }

    /**
     * @test
     */
    public function officer_create_from_source_hydrates_from_a_row(): void
    {
        $this->installWpdb([
            'id'                    => '3',
            'intergroup_meeting_id' => '42',
            'meeting_label'         => 'July',
            'officer_id'            => '9',
            'position_name'         => 'Chair',
            'officer_name'          => 'Carol C.',
        ]);

        $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createFromSource(3);

        $this->assertSame(3, $attendance->getId());
        $this->assertSame(42, $attendance->getIntergroupMeetingId());
        $this->assertSame('July', $attendance->getMeetingLabel());
        $this->assertSame(9, $attendance->getOfficerId());
        $this->assertSame('Chair', $attendance->getPositionName());
        $this->assertSame('Carol C.', $attendance->getOfficerName());
    }

    /**
     * @test
     */
    public function officer_create_from_source_returns_an_empty_object_for_a_missing_row(): void
    {
        $this->installWpdb(null);

        $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createFromSource(88);

        $this->assertSame(88, $attendance->getId());
        $this->assertSame(0, $attendance->getOfficerId());
    }

    /**
     * @test
     */
    public function officer_create_new_builds_an_unsaved_row(): void
    {
        $attendance = (new TsmlIntergroupMeetingOfficerAttendanceFactory())->createNew(
            42,
            'July',
            9,
            'Chair',
            'Carol C.'
        );

        $this->assertSame(0, $attendance->getId());
        $this->assertSame(42, $attendance->getIntergroupMeetingId());
        $this->assertSame('Chair', $attendance->getPositionName());
        $this->assertSame('Carol C.', $attendance->getOfficerName());
    }
}
