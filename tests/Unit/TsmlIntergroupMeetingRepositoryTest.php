<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;
use WP_Mock;

/**
 * Tests for TsmlIntergroupMeetingRepository.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository
 */
class TsmlIntergroupMeetingRepositoryTest extends TestCase
{
    /** @var IntergroupMeetingFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlIntergroupMeetingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->factory = $this->createMock(IntergroupMeetingFactory::class);
        $this->repository = new TsmlIntergroupMeetingRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function meetingPost(): object
    {
        return (object) ['post_type' => TsmlIntergroupMeetingFields::POST_TYPE];
    }

    /**
     * @test
     */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingRepository::class, $this->repository);
    }

    /**
     * @test
     */
    public function find_by_id_returns_null_for_a_missing_post(): void
    {
        WP_Mock::userFunction('get_post')->with(9)->andReturn(null);

        $this->assertNull($this->repository->findById(9));
    }

    /**
     * @test
     */
    public function find_by_id_returns_null_for_the_wrong_post_type(): void
    {
        WP_Mock::userFunction('get_post')->with(9)->andReturn((object) ['post_type' => 'page']);

        $this->assertNull($this->repository->findById(9));
    }

    /**
     * @test
     */
    public function find_by_id_delegates_to_the_factory(): void
    {
        WP_Mock::userFunction('get_post')->with(5)->andReturn($this->meetingPost());

        $meeting = new TsmlIntergroupMeeting(id: 5, title: 'July');
        $this->factory->expects($this->once())
            ->method('createFromSource')->with(5)->willReturn($meeting);

        $this->assertSame($meeting, $this->repository->findById(5));
    }

    /**
     * @test
     */
    public function find_all_maps_posts_through_find_by_id(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([
            (object) ['ID' => 1],
            (object) ['ID' => 2],
        ]);
        WP_Mock::userFunction('get_post')->andReturn($this->meetingPost());

        $a = new TsmlIntergroupMeeting(id: 1);
        $b = new TsmlIntergroupMeeting(id: 2);
        $this->factory->method('createFromSource')->willReturnMap([[1, $a], [2, $b]]);

        $this->assertSame([$a, $b], $this->repository->findAll());
    }

    /**
     * @test
     */
    public function find_all_returns_empty_when_there_are_no_posts(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([]);

        $this->assertSame([], $this->repository->findAll());
    }

    /**
     * @test
     */
    public function count_returns_the_number_of_ids(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([10, 11]);

        $this->assertSame(2, $this->repository->count());
    }

    /**
     * @test
     */
    public function count_translates_pagination_args_without_error(): void
    {
        // posts_per_page → numberposts and paged → offset are handled in
        // buildQueryArgs; the count path then forces numberposts -1.
        WP_Mock::userFunction('get_posts')->once()->andReturn([1, 2, 3]);

        $this->assertSame(3, $this->repository->count([
            'posts_per_page' => 10,
            'paged' => 2,
        ]));
    }

    /**
     * @test
     */
    public function save_writes_both_relationship_fields_by_key(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

        $writes = [];
        WP_Mock::userFunction('update_field')->andReturnUsing(
            function ($key, $value, $id) use (&$writes) {
                $writes[$key] = $value;
                return true;
            }
        );

        $meeting = new TsmlIntergroupMeeting(
            id: 5,
            groupAttendees: [1, 2],
            officersAttending: [3],
        );

        $this->assertTrue($this->repository->save($meeting));

        // Field keys come from the resolver's hardcoded fallbacks.
        $this->assertSame([1, 2], $writes[TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES]);
        $this->assertSame([3], $writes[TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS]);
    }

    /**
     * @test
     */
    public function delete_force_deletes_the_post(): void
    {
        WP_Mock::userFunction('wp_delete_post')->once()->with(5, true)->andReturn((object) ['ID' => 5]);

        $this->assertTrue($this->repository->delete(5));
    }

    /**
     * @test
     */
    public function delete_returns_false_when_removal_fails(): void
    {
        WP_Mock::userFunction('wp_delete_post')->once()->with(5, true)->andReturn(false);

        $this->assertFalse($this->repository->delete(5));
    }
}
