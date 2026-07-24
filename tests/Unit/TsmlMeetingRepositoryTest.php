<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use Unity\Core\Interfaces\Cache;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use WP_Mock;
use WP_Post;

/**
 * Tests for TsmlMeetingRepository.
 *
 * The repository is a thin translation layer: it turns domain queries into
 * WP_Query arguments and posts into Meetings via the factory. The tests
 * therefore assert on the arguments handed to get_posts() — the meta_query
 * built for day/group/location lookups is where the behaviour actually
 * lives, and it is invisible from the return value alone.
 *
 * The optional cache is exercised on both paths, since a stale or bypassed
 * cache is the kind of fault that only shows up under load.
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingRepository
 */
class TsmlMeetingRepositoryTest extends TestCase
{
    /** @var MeetingFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlMeetingRepository $repository;

    /** Arguments captured from the last get_posts() call. */
    private array $capturedArgs = [];

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(MeetingFactory::class);
        $this->repository = new TsmlMeetingRepository($this->factory);
        $this->capturedArgs = [];
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function post(int $id = 1, string $type = TsmlMeetingFields::POST_TYPE): WP_Post
    {
        return new WP_Post([
            'ID'          => $id,
            'post_type'   => $type,
            'post_title'  => 'Tuesday Meeting',
            'post_name'   => 'tuesday-meeting',
            'post_parent' => 55,
        ]);
    }

    /** Stub get_posts(), capturing the arguments it was called with. */
    private function stubGetPosts(array $posts): void
    {
        WP_Mock::userFunction('get_posts')->andReturnUsing(function (array $args) use ($posts): array {
            $this->capturedArgs = $args;

            return $posts;
        });
    }

    private function stubPostMeta(array $meta = []): void
    {
        WP_Mock::userFunction('get_post_meta')->andReturn($meta);
    }

    private function meeting(bool $online = false): Meeting
    {
        $meeting = $this->createMock(Meeting::class);
        $meeting->method('isOnline')->willReturn($online);

        return $meeting;
    }

    /** @test */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(MeetingRepository::class, $this->repository);
    }

    // ─── findById ───────────────────────────────────────────────────

    /** @test */
    public function find_by_id_rejects_a_non_positive_id_without_touching_wordpress(): void
    {
        $this->assertNull($this->repository->findById(0));
        $this->assertNull($this->repository->findById(-1));
    }

    /** @test */
    public function find_by_id_builds_a_meeting_from_the_post(): void
    {
        WP_Mock::userFunction('get_post')->andReturn($this->post(7));
        $this->stubPostMeta(['day' => ['2'], 'group_id' => ['99']]);

        $expected = $this->meeting();
        $this->factory->expects($this->once())
            ->method('createFromSource')
            ->with($this->callback(function (array $source): bool {
                // Meta is both nested under 'meta' and flattened to the top
                // level, so callers can reach a value either way.
                $this->assertSame(7, $source['id']);
                $this->assertSame('Tuesday Meeting', $source['name']);
                $this->assertSame('tuesday-meeting', $source['slug']);
                $this->assertSame(55, $source['location_id']);
                $this->assertSame('2', $source['day']);
                $this->assertSame(['2'], $source['meta']['day']);

                return true;
            }))
            ->willReturn($expected);

        $this->assertSame($expected, $this->repository->findById(7));
    }

    /** @test */
    public function find_by_id_returns_null_when_the_post_is_missing(): void
    {
        WP_Mock::userFunction('get_post')->andReturn(null);

        $this->assertNull($this->repository->findById(7));
    }

    /** @test */
    public function find_by_id_returns_null_for_a_post_of_the_wrong_type(): void
    {
        WP_Mock::userFunction('get_post')->andReturn($this->post(7, 'page'));

        $this->assertNull($this->repository->findById(7));
    }

    // ─── findById caching ───────────────────────────────────────────

    /** @test */
    public function a_cache_hit_short_circuits_the_post_lookup(): void
    {
        $cached = $this->meeting();
        $cache = $this->createMock(Cache::class);
        $cache->expects($this->once())->method('get')->with('meeting_7', 'unity_meetings')->willReturn($cached);
        $cache->expects($this->never())->method('set');

        $this->factory->expects($this->never())->method('createFromSource');

        $repository = new TsmlMeetingRepository($this->factory, $cache);

        $this->assertSame($cached, $repository->findById(7));
    }

    /** @test */
    public function a_cache_miss_falls_through_and_stores_the_result(): void
    {
        $built = $this->meeting();
        $cache = $this->createMock(Cache::class);
        // WordPress's cache API signals "not found" with false.
        $cache->expects($this->once())->method('get')->willReturn(false);
        $cache->expects($this->once())
            ->method('set')
            ->with('meeting_7', $built, 'unity_meetings', 3600);

        WP_Mock::userFunction('get_post')->andReturn($this->post(7));
        $this->stubPostMeta();
        $this->factory->method('createFromSource')->willReturn($built);

        $repository = new TsmlMeetingRepository($this->factory, $cache);

        $this->assertSame($built, $repository->findById(7));
    }

    // ─── findAll ────────────────────────────────────────────────────

    /** @test */
    public function find_all_applies_the_documented_defaults(): void
    {
        $this->stubGetPosts([]);

        $this->assertSame([], $this->repository->findAll());

        $this->assertSame(TsmlMeetingFields::POST_TYPE, $this->capturedArgs['post_type']);
        $this->assertSame('publish', $this->capturedArgs['post_status']);
        $this->assertSame(100, $this->capturedArgs['posts_per_page']);
        $this->assertSame('title', $this->capturedArgs['orderby']);
        $this->assertSame('ASC', $this->capturedArgs['order']);
    }

    /** @test */
    public function caller_arguments_override_the_defaults(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findAll(['posts_per_page' => 5, 'order' => 'DESC']);

        $this->assertSame(5, $this->capturedArgs['posts_per_page']);
        $this->assertSame('DESC', $this->capturedArgs['order']);
    }

    /** @test */
    public function find_all_builds_a_meeting_for_every_post(): void
    {
        $this->stubGetPosts([$this->post(1), $this->post(2)]);
        $this->stubPostMeta();
        $this->factory->method('createFromSource')->willReturn($this->meeting());

        $this->assertCount(2, $this->repository->findAll());
    }

    /** @test */
    public function posts_the_factory_rejects_are_skipped(): void
    {
        $this->stubGetPosts([$this->post(1), $this->post(2)]);
        $this->stubPostMeta();
        // The second post yields nothing; the result should close over the gap.
        $this->factory->method('createFromSource')
            ->willReturnOnConsecutiveCalls($this->meeting(), null);

        $this->assertCount(1, $this->repository->findAll());
    }

    // ─── findByDay ──────────────────────────────────────────────────

    /** @test */
    public function find_by_day_adds_a_day_meta_query(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findByDay(2);

        $this->assertSame(
            ['key' => 'day', 'value' => '2', 'compare' => '='],
            $this->capturedArgs['meta_query'][0],
            'The day is compared as a string, matching how WordPress stores meta.'
        );
    }

    /** @test */
    public function find_by_day_ands_itself_onto_an_existing_meta_query(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findByDay(2, [
            'meta_query' => [['key' => 'region', 'value' => 'north']],
        ]);

        $this->assertSame('AND', $this->capturedArgs['meta_query']['relation']);
        $this->assertCount(3, $this->capturedArgs['meta_query'], 'existing clause + relation + day');
    }

    /** @test */
    public function find_by_day_leaves_an_explicit_relation_alone(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findByDay(2, [
            'meta_query' => ['relation' => 'OR', ['key' => 'region', 'value' => 'north']],
        ]);

        $this->assertSame('OR', $this->capturedArgs['meta_query']['relation']);
    }

    // ─── online / in person ─────────────────────────────────────────

    /** @test */
    public function find_online_keeps_only_online_meetings(): void
    {
        $this->stubGetPosts([$this->post(1), $this->post(2), $this->post(3)]);
        $this->stubPostMeta();
        $this->factory->method('createFromSource')->willReturnOnConsecutiveCalls(
            $this->meeting(true),
            $this->meeting(false),
            $this->meeting(true),
        );

        $online = $this->repository->findOnline();

        $this->assertCount(2, $online);
        // Re-indexed, so callers can rely on a list rather than a sparse array.
        $this->assertSame([0, 1], array_keys($online));
    }

    /** @test */
    public function find_in_person_keeps_only_the_meetings_that_are_not_online(): void
    {
        $this->stubGetPosts([$this->post(1), $this->post(2)]);
        $this->stubPostMeta();
        $this->factory->method('createFromSource')->willReturnOnConsecutiveCalls(
            $this->meeting(true),
            $this->meeting(false),
        );

        $inPerson = $this->repository->findInPerson();

        $this->assertCount(1, $inPerson);
        $this->assertSame([0], array_keys($inPerson));
    }

    // ─── findByGroupId / findByLocationId ───────────────────────────

    /** @test */
    public function find_by_group_id_adds_a_group_meta_query(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findByGroupId(99);

        $this->assertSame(
            ['key' => 'group_id', 'value' => 99, 'compare' => '='],
            $this->capturedArgs['meta_query'][0]
        );
    }

    /** @test */
    public function find_by_group_id_rejects_a_non_positive_id(): void
    {
        $this->assertSame([], $this->repository->findByGroupId(0));
        $this->assertSame([], $this->repository->findByGroupId(-5));
    }

    /** @test */
    public function find_by_location_id_adds_a_location_meta_query(): void
    {
        $this->stubGetPosts([]);

        $this->repository->findByLocationId(55);

        $this->assertSame(
            ['key' => 'location_id', 'value' => 55, 'compare' => '='],
            $this->capturedArgs['meta_query'][0]
        );
    }

    /** @test */
    public function find_by_location_id_rejects_a_non_positive_id(): void
    {
        $this->assertSame([], $this->repository->findByLocationId(0));
        $this->assertSame([], $this->repository->findByLocationId(-5));
    }

    // ─── search ─────────────────────────────────────────────────────

    /** @test */
    public function search_passes_the_keyword_through_as_a_post_search(): void
    {
        $this->stubGetPosts([]);

        $this->repository->search('serenity');

        $this->assertSame('serenity', $this->capturedArgs['s']);
    }

    /** @test */
    public function an_empty_search_returns_nothing_without_querying(): void
    {
        $this->assertSame([], $this->repository->search(''));
    }

    // ─── count ──────────────────────────────────────────────────────

    /** @test */
    public function count_asks_only_for_ids_and_returns_the_total(): void
    {
        $this->stubGetPosts([1, 2, 3, 4]);

        $this->assertSame(4, $this->repository->count());

        $this->assertSame('ids', $this->capturedArgs['fields'], 'Counting should not hydrate posts.');
        $this->assertSame(-1, $this->capturedArgs['posts_per_page']);
    }

    /** @test */
    public function count_honours_caller_arguments(): void
    {
        $this->stubGetPosts([1]);

        $this->assertSame(1, $this->repository->count(['post_status' => 'draft']));

        $this->assertSame('draft', $this->capturedArgs['post_status']);
    }
}
