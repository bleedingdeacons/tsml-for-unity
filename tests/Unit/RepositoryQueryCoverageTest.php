<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Groups\TsmlGroupRepository;
use TsmlForUnity\Members\TsmlMemberFactory;
use TsmlForUnity\Members\TsmlMemberFields;
use TsmlForUnity\Members\TsmlMemberRepository;
use TsmlForUnity\Positions\TsmlPositionFields;
use TsmlForUnity\Positions\TsmlPositionRepository;
use Unity\Contacts\Interfaces\ContactFactory;
use Unity\Groups\Interfaces\Group;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\ResponderCertification;
use Unity\Positions\Interfaces\Position;
use Unity\Positions\Interfaces\PositionFactory;
use WP_Mock;

/**
 * Query-path coverage for the CPT-backed repositories.
 *
 * The group, position and member repositories all follow the same shape:
 * build a WP_Query argument array, hand it to get_posts(), and turn each
 * post id back into a domain object through the factory. The tests assert
 * on the arguments produced, because that is where the behaviour lives —
 * in particular count(), which deliberately forces a lightweight ids-only
 * query regardless of what the caller asked for.
 *
 * delete() is unimplemented on the group and position repositories and
 * throws; that is pinned here so the contract cannot quietly change.
 */
class RepositoryQueryCoverageTest extends TestCase
{
    /** Arguments captured from the last get_posts() call. */
    private array $capturedArgs = [];

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // wp_parse_args is pure; mirror it rather than assert against a stub.
        WP_Mock::userFunction('wp_parse_args')
            ->andReturnUsing(static fn ($args, $defaults = []) => array_merge($defaults, (array) $args));

        $this->capturedArgs = [];
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /** Stub get_posts(), capturing the arguments and returning $posts. */
    private function stubGetPosts(array $posts): void
    {
        WP_Mock::userFunction('get_posts')->andReturnUsing(function ($args) use ($posts) {
            $this->capturedArgs = (array) $args;

            return $posts;
        });
    }

    private function postObjects(int ...$ids): array
    {
        return array_map(static fn (int $id): object => (object) ['ID' => $id], $ids);
    }

    // ══ Group repository ══════════════════════════════════════════════

    /** @test */
    public function group_find_by_id_delegates_straight_to_the_factory(): void
    {
        $group = $this->createMock(Group::class);
        $factory = $this->createMock(GroupFactory::class);
        $factory->expects($this->once())->method('createFromSource')->with(7)->willReturn($group);

        $this->assertSame($group, (new TsmlGroupRepository($factory))->findById(7));
    }

    /** @test */
    public function group_find_all_queries_published_groups_and_hydrates_each_post(): void
    {
        $this->stubGetPosts($this->postObjects(1, 2));

        $factory = $this->createMock(GroupFactory::class);
        $factory->expects($this->exactly(2))
            ->method('createFromSource')
            ->willReturn($this->createMock(Group::class));

        $groups = (new TsmlGroupRepository($factory))->findAll();

        $this->assertCount(2, $groups);
        $this->assertSame(TsmlGroupFields::POST_TYPE, $this->capturedArgs['post_type']);
        $this->assertSame('publish', $this->capturedArgs['post_status']);
        $this->assertSame(-1, $this->capturedArgs['posts_per_page']);
    }

    /** @test */
    public function group_find_all_skips_posts_without_an_id(): void
    {
        // A malformed row must not reach the factory as ID 0.
        $this->stubGetPosts([(object) ['ID' => 0], (object) ['ID' => 3]]);

        $factory = $this->createMock(GroupFactory::class);
        $factory->expects($this->once())
            ->method('createFromSource')
            ->with(3)
            ->willReturn($this->createMock(Group::class));

        $this->assertCount(1, (new TsmlGroupRepository($factory))->findAll());
    }

    /** @test */
    public function group_find_all_drops_posts_the_factory_rejects(): void
    {
        $this->stubGetPosts($this->postObjects(1, 2));

        $factory = $this->createMock(GroupFactory::class);
        $factory->method('createFromSource')
            ->willReturnOnConsecutiveCalls($this->createMock(Group::class), null);

        $this->assertCount(1, (new TsmlGroupRepository($factory))->findAll());
    }

    /** @test */
    public function group_count_forces_an_ids_only_query(): void
    {
        $this->stubGetPosts([1, 2, 3]);

        $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

        // Even asked for full posts and a page size, count() overrides both.
        $this->assertSame(3, $repository->count(['fields' => 'all', 'posts_per_page' => 10]));
        $this->assertSame('ids', $this->capturedArgs['fields']);
        $this->assertSame(-1, $this->capturedArgs['posts_per_page']);
    }

    /** @test */
    public function group_count_is_zero_when_the_query_returns_nothing_usable(): void
    {
        WP_Mock::userFunction('get_posts')->andReturn(null);

        $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

        $this->assertSame(0, $repository->count());
    }

    /** @test */
    public function group_delete_is_explicitly_unimplemented(): void
    {
        $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Delete is not implemented');

        $repository->delete(1);
    }

    // ══ Group factory setters ═════════════════════════════════════════

    /** @test */
    public function group_factory_dependencies_can_be_supplied_after_construction(): void
    {
        $factory = new TsmlGroupFactory();

        $factory->setContactFactory($this->createMock(ContactFactory::class));
        $factory->setMeetingRepository($this->createMock(MeetingRepository::class));

        $this->assertInstanceOf(GroupFactory::class, $factory);
    }

    // ══ Position repository ═══════════════════════════════════════════

    /** @test */
    public function position_find_by_id_delegates_straight_to_the_factory(): void
    {
        $position = $this->createMock(Position::class);
        $factory = $this->createMock(PositionFactory::class);
        $factory->expects($this->once())->method('createFromSource')->with(9)->willReturn($position);

        $this->assertSame($position, (new TsmlPositionRepository($factory))->findById(9));
    }

    /** @test */
    public function position_find_all_queries_published_positions(): void
    {
        $this->stubGetPosts($this->postObjects(1, 2, 3));

        $factory = $this->createMock(PositionFactory::class);
        $factory->method('createFromSource')->willReturn($this->createMock(Position::class));

        $this->assertCount(3, (new TsmlPositionRepository($factory))->findAll());
        $this->assertSame(TsmlPositionFields::POST_TYPE, $this->capturedArgs['post_type']);
        $this->assertSame('publish', $this->capturedArgs['post_status']);
    }

    /** @test */
    public function position_find_all_drops_posts_the_factory_rejects(): void
    {
        $this->stubGetPosts($this->postObjects(1, 2));

        $factory = $this->createMock(PositionFactory::class);
        $factory->method('createFromSource')
            ->willReturnOnConsecutiveCalls(null, $this->createMock(Position::class));

        $this->assertCount(1, (new TsmlPositionRepository($factory))->findAll());
    }

    /** @test */
    public function position_count_asks_only_for_ids(): void
    {
        $this->stubGetPosts([1, 2]);

        $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

        $this->assertSame(2, $repository->count());
        $this->assertSame('ids', $this->capturedArgs['fields']);
    }

    /** @test */
    public function position_count_is_zero_when_the_query_returns_nothing_usable(): void
    {
        WP_Mock::userFunction('get_posts')->andReturn(null);

        $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

        $this->assertSame(0, $repository->count());
    }

    /** @test */
    public function position_delete_is_explicitly_unimplemented(): void
    {
        $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Delete is not implemented');

        $repository->delete(1);
    }

    // ══ Member repository ═════════════════════════════════════════════

    /** @test */
    public function member_count_asks_only_for_ids(): void
    {
        $this->stubGetPosts([1, 2, 3, 4]);

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertSame(4, $repository->count());
        $this->assertSame(TsmlMemberFields::POST_TYPE, $this->capturedArgs['post_type']);
        $this->assertSame('ids', $this->capturedArgs['fields']);
    }

    /** @test */
    public function member_count_is_zero_when_the_query_returns_nothing_usable(): void
    {
        WP_Mock::userFunction('get_posts')->andReturn(null);

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertSame(0, $repository->count());
    }

    /** @test */
    public function creating_a_member_inserts_a_post_and_mirrors_the_name_into_acf(): void
    {
        WP_Mock::userFunction('wp_insert_post')->andReturn(77);
        WP_Mock::userFunction('is_wp_error')->andReturn(false);
        WP_Mock::userFunction('update_field')->andReturn(true);
        WP_Mock::userFunction('do_action')->andReturn(null);
        WP_Mock::userFunction('get_post')->andReturn(null);

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertSame(77, $repository->create('Anonymous Alex'));
    }

    /** @test */
    public function a_failed_member_insert_reports_zero(): void
    {
        WP_Mock::userFunction('wp_insert_post')->andReturn('error');
        WP_Mock::userFunction('is_wp_error')->andReturn(true);

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertSame(0, $repository->create('Anonymous Alex'));
    }

    /** @test */
    public function deleting_a_member_forces_a_permanent_delete(): void
    {
        $captured = [];
        WP_Mock::userFunction('wp_delete_post')->andReturnUsing(
            function ($id, $force = false) use (&$captured) {
                $captured = [$id, $force];

                return (object) ['ID' => $id];
            }
        );

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertTrue($repository->delete(5));
        $this->assertSame([5, true], $captured, 'Members are hard-deleted, not trashed.');
    }

    /** @test */
    public function a_failed_member_delete_reports_false(): void
    {
        WP_Mock::userFunction('wp_delete_post')->andReturn(false);

        $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

        $this->assertFalse($repository->delete(5));
    }

    // ══ Member factory ════════════════════════════════════════════════

    /** @test */
    public function create_new_builds_a_member_from_explicit_values(): void
    {
        $member = (new TsmlMemberFactory())->createNew(
            id: 42,
            anonymousName: 'Anonymous Alex',
            showAnonymousName: true,
            personalEmail: 'alex@example.test',
            telephoneResponder: true,
            responderCertification: ResponderCertification::Certified,
        );

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame(42, $member->getId());
        $this->assertSame('Anonymous Alex', $member->getAnonymousName());
        $this->assertTrue($member->showAnonymousName());
        $this->assertSame('alex@example.test', $member->getPersonalEmail());
        $this->assertTrue($member->isTelephoneResponder());
        $this->assertSame(ResponderCertification::Certified, $member->getResponderCertification());
    }

    /** @test */
    public function create_new_defaults_every_optional_value(): void
    {
        $member = (new TsmlMemberFactory())->createNew(id: 1);

        $this->assertSame('', $member->getAnonymousName());
        $this->assertFalse($member->showAnonymousName());
        $this->assertFalse($member->isTwelfthStepper());
        $this->assertFalse($member->isGdprAccepted());
        // A member with no certification recorded sits at None.
        $this->assertSame(ResponderCertification::None, $member->getResponderCertification());
    }
}
