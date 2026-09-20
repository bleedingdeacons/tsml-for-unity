<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository;
use TsmlForUnity\Tests\TestCase;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;

/**
 * Tests for TsmlIntergroupMeetingRepository.
 */
#[CoversClass(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository::class)]
class TsmlIntergroupMeetingRepositoryTest extends TestCase
{
    /** @var IntergroupMeetingFactory&MockObject */
    private $factory;

    private TsmlIntergroupMeetingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createMock(IntergroupMeetingFactory::class);
        $this->repository = new TsmlIntergroupMeetingRepository($this->factory);
    }

    private function meetingPost(): object
    {
        return (object) ['post_type' => TsmlIntergroupMeetingFields::POST_TYPE];
    }

    #[Test]
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(IntergroupMeetingRepository::class, $this->repository);
    }

    #[Test]
    public function find_by_id_returns_null_for_a_missing_post(): void
    {
        expect('get_post')->with(9)->andReturn(null);

        $this->assertNull($this->repository->findById(9));
    }

    #[Test]
    public function find_by_id_returns_null_for_the_wrong_post_type(): void
    {
        expect('get_post')->with(9)->andReturn((object) ['post_type' => 'page']);

        $this->assertNull($this->repository->findById(9));
    }

    #[Test]
    public function find_by_id_delegates_to_the_factory(): void
    {
        expect('get_post')->with(5)->andReturn($this->meetingPost());

        $meeting = new TsmlIntergroupMeeting(id: 5, title: 'July');
        $this->factory->expects($this->once())
            ->method('createFromSource')->with(5)->willReturn($meeting);

        $this->assertSame($meeting, $this->repository->findById(5));
    }

    #[Test]
    public function find_all_maps_posts_through_find_by_id(): void
    {
        expect('get_posts')->once()->andReturn([
            (object) ['ID' => 1],
            (object) ['ID' => 2],
        ]);
        expect('get_post')->andReturn($this->meetingPost());

        $a = new TsmlIntergroupMeeting(id: 1);
        $b = new TsmlIntergroupMeeting(id: 2);
        $this->factory->method('createFromSource')->willReturnMap([[1, $a], [2, $b]]);

        $this->assertSame([$a, $b], $this->repository->findAll());
    }

    #[Test]
    public function find_all_returns_empty_when_there_are_no_posts(): void
    {
        expect('get_posts')->once()->andReturn([]);

        $this->assertSame([], $this->repository->findAll());
    }

    #[Test]
    public function count_returns_the_number_of_ids(): void
    {
        expect('get_posts')->once()->andReturn([10, 11]);

        $this->assertSame(2, $this->repository->count());
    }

    #[Test]
    public function count_translates_pagination_args_without_error(): void
    {
        // posts_per_page → numberposts and paged → offset are handled in
        // buildQueryArgs; the count path then forces numberposts -1.
        expect('get_posts')->once()->andReturn([1, 2, 3]);

        $this->assertSame(3, $this->repository->count([
            'posts_per_page' => 10,
            'paged' => 2,
        ]));
    }

    #[Test]
    public function save_writes_both_relationship_fields_by_key(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

        $writes = [];
        expect('update_field')->andReturnUsing(
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

    #[Test]
    public function delete_force_deletes_the_post(): void
    {
        expect('wp_delete_post')->once()->with(5, true)->andReturn((object) ['ID' => 5]);

        $this->assertTrue($this->repository->delete(5));
    }

    #[Test]
    public function delete_returns_false_when_removal_fails(): void
    {
        expect('wp_delete_post')->once()->with(5, true)->andReturn(false);

        $this->assertFalse($this->repository->delete(5));
    }
}
