<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\TsmlGroup;
use TsmlForUnity\TsmlGroupFields;
use TsmlForUnity\TsmlGroupRepository;
use WP_Mock;

// Define mock Unity interfaces if they don't exist
if (!interface_exists('Unity\\Groups\\Interfaces\\GroupInterface')) {
    eval('
    namespace Unity\\Groups\\Interfaces;
    
    interface GroupInterface {
        public function getId(): int;
        public function getTitle(): string;
        public function getEmail(): string;
        public function getMeetingIds(): array;
        public function getLink(): string;
        public function isValid(): bool;
    }
    ');
}

if (!interface_exists('Unity\\Groups\\Interfaces\\GroupFactoryInterface')) {
    eval('
    namespace Unity\\Groups\\Interfaces;
    
    interface GroupFactoryInterface {
        public function createFromSource(int $sourceId): ?GroupInterface;
    }
    ');
}

if (!interface_exists('Unity\\Groups\\Interfaces\\GroupRepositoryInterface')) {
    eval('
    namespace Unity\\Groups\\Interfaces;
    
    interface GroupRepositoryInterface {
        public function findById(int $id): ?GroupInterface;
        public function findAll(array $args = []): array;
        public function save(GroupInterface $group): bool;
        public function update(GroupInterface $group): bool;
        public function delete(int $id): bool;
    }
    ');
}

/**
 * @covers \TsmlForUnity\TsmlGroupRepository
 */
class TsmlGroupRepositoryTest extends TestCase
{
    private $factory;
    private TsmlGroupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = Mockery::mock('Unity\\Groups\\Interfaces\\GroupFactoryInterface');
        $this->repository = new TsmlGroupRepository($this->factory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_finds_group_by_id_using_factory(): void
    {
        $groupId = 100;
        $expectedGroup = new TsmlGroup(
            id: $groupId,
            title: 'Test Group'
        );

        $this->factory
            ->shouldReceive('createFromSource')
            ->once()
            ->with($groupId)
            ->andReturn($expectedGroup);

        $result = $this->repository->findById($groupId);

        $this->assertSame($expectedGroup, $result);
    }

    /**
     * @test
     */
    public function it_returns_null_when_group_not_found(): void
    {
        $this->factory
            ->shouldReceive('createFromSource')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->repository->findById(999);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function it_finds_all_groups(): void
    {
        $posts = [
            $this->createMockPost(['ID' => 1]),
            $this->createMockPost(['ID' => 2]),
            $this->createMockPost(['ID' => 3]),
        ];

        $group1 = new TsmlGroup(id: 1, title: 'Group 1');
        $group2 = new TsmlGroup(id: 2, title: 'Group 2');
        $group3 = new TsmlGroup(id: 3, title: 'Group 3');

        WP_Mock::userFunction('wp_parse_args')
            ->once()
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn($posts);

        $this->factory
            ->shouldReceive('createFromSource')
            ->with(1)
            ->andReturn($group1);

        $this->factory
            ->shouldReceive('createFromSource')
            ->with(2)
            ->andReturn($group2);

        $this->factory
            ->shouldReceive('createFromSource')
            ->with(3)
            ->andReturn($group3);

        $results = $this->repository->findAll();

        $this->assertCount(3, $results);
        $this->assertSame($group1, $results[0]);
        $this->assertSame($group2, $results[1]);
        $this->assertSame($group3, $results[2]);
    }

    /**
     * @test
     */
    public function it_filters_out_null_groups_in_find_all(): void
    {
        $posts = [
            $this->createMockPost(['ID' => 1]),
            $this->createMockPost(['ID' => 2]),
        ];

        $group1 = new TsmlGroup(id: 1, title: 'Valid Group');

        WP_Mock::userFunction('wp_parse_args')
            ->once()
            ->andReturnUsing(function ($args, $defaults) {
                return array_merge($defaults, $args);
            });

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn($posts);

        $this->factory
            ->shouldReceive('createFromSource')
            ->with(1)
            ->andReturn($group1);

        $this->factory
            ->shouldReceive('createFromSource')
            ->with(2)
            ->andReturn(null);

        $results = $this->repository->findAll();

        $this->assertCount(1, $results);
        $this->assertSame($group1, $results[0]);
    }

    /**
     * @test
     */
    public function it_saves_new_group(): void
    {
        $group = new TsmlGroup(
            id: 0,
            title: 'New Group',
            email: 'new@example.com',
            groupNotes: 'Notes',
            website: 'https://newgroup.org',
            phone: '555-1234',
            venmo: '@NewGroup',
            paypal: 'NewGroup',
            square: '$NewGroup',
            districtId: 10,
            contacts: [
                ['name' => 'John', 'email' => 'john@example.com', 'phone' => '555-5678'],
            ]
        );

        WP_Mock::userFunction('wp_insert_post')
            ->once()
            ->andReturn(42);

        WP_Mock::userFunction('is_wp_error')
            ->once()
            ->with(42)
            ->andReturn(false);

        WP_Mock::userFunction('update_post_meta')
            ->times(11); // email, group_notes, website, phone, venmo, paypal, square, district_id, contact fields

        WP_Mock::userFunction('delete_post_meta')
            ->times(9); // 3 contacts x 3 fields each (clearing existing)

        $result = $this->repository->save($group);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function it_returns_false_when_saving_invalid_group(): void
    {
        $group = new TsmlGroup(
            id: 0,
            title: '' // Invalid - empty title
        );

        $result = $this->repository->save($group);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function it_updates_existing_group(): void
    {
        $group = new TsmlGroup(
            id: 50,
            title: 'Updated Group',
            email: 'updated@example.com',
            groupNotes: 'Updated notes',
            website: 'https://updated.org'
        );

        WP_Mock::userFunction('wp_update_post')
            ->once()
            ->andReturn(50);

        WP_Mock::userFunction('is_wp_error')
            ->once()
            ->with(50)
            ->andReturn(false);

        WP_Mock::userFunction('update_post_meta')
            ->atLeast()
            ->times(1);

        WP_Mock::userFunction('delete_post_meta')
            ->atLeast()
            ->times(1);

        $result = $this->repository->update($group);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function it_returns_false_when_updating_group_with_zero_id(): void
    {
        $group = new TsmlGroup(
            id: 0,
            title: 'Group'
        );

        $result = $this->repository->update($group);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function delete_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Delete is not implemented');

        $this->repository->delete(1);
    }

    /**
     * @test
     */
    public function save_calls_update_for_existing_group(): void
    {
        $group = new TsmlGroup(
            id: 100,
            title: 'Existing Group',
            email: 'existing@example.com'
        );

        WP_Mock::userFunction('wp_update_post')
            ->once()
            ->andReturn(100);

        WP_Mock::userFunction('is_wp_error')
            ->once()
            ->andReturn(false);

        WP_Mock::userFunction('update_post_meta')
            ->atLeast()
            ->times(1);

        WP_Mock::userFunction('delete_post_meta')
            ->atLeast()
            ->times(1);

        $result = $this->repository->save($group);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function it_counts_groups(): void
    {
        $counts = (object) [
            'publish' => 42,
            'draft' => 5,
            'trash' => 2,
        ];

        WP_Mock::userFunction('wp_count_posts')
            ->once()
            ->with(TsmlGroupFields::GROUP_POST_TYPE)
            ->andReturn($counts);

        $result = $this->repository->count();

        $this->assertEquals(42, $result);
    }

    /**
     * @test
     */
    public function find_by_district_adds_meta_query(): void
    {
        WP_Mock::userFunction('wp_parse_args')
            ->once()
            ->andReturnUsing(function ($args, $defaults) {
                // Verify meta_query is set
                $this->assertArrayHasKey('meta_query', $args);
                $this->assertEquals(TsmlGroupFields::DISTRICT_ID, $args['meta_query'][0]['key']);
                $this->assertEquals(42, $args['meta_query'][0]['value']);
                return array_merge($defaults, $args);
            });

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        $results = $this->repository->findByDistrict(42);

        $this->assertIsArray($results);
    }

    /**
     * @test
     */
    public function find_with_contribution_options_adds_meta_query(): void
    {
        WP_Mock::userFunction('wp_parse_args')
            ->once()
            ->andReturnUsing(function ($args, $defaults) {
                // Verify meta_query has OR relation
                $this->assertArrayHasKey('meta_query', $args);
                $this->assertEquals('OR', $args['meta_query']['relation']);
                return array_merge($defaults, $args);
            });

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        $results = $this->repository->findWithContributionOptions();

        $this->assertIsArray($results);
    }

    /**
     * @test
     */
    public function search_by_title_adds_search_param(): void
    {
        WP_Mock::userFunction('wp_parse_args')
            ->once()
            ->andReturnUsing(function ($args, $defaults) {
                // Verify search param is set
                $this->assertArrayHasKey('s', $args);
                $this->assertEquals('Serenity', $args['s']);
                return array_merge($defaults, $args);
            });

        WP_Mock::userFunction('get_posts')
            ->once()
            ->andReturn([]);

        $results = $this->repository->searchByTitle('Serenity');

        $this->assertIsArray($results);
    }

    /**
     * Create a mock WP_Post object
     *
     * @param array $properties Post properties
     * @return object Mock post object
     */
    private function createMockPost(array $properties): object
    {
        return (object) array_merge([
            'ID' => 0,
            'post_title' => '',
            'post_type' => TsmlGroupFields::GROUP_POST_TYPE,
            'post_status' => 'publish',
        ], $properties);
    }
}
