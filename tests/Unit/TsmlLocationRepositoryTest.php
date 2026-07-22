<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Locations\TsmlLocation;
use TsmlForUnity\Locations\TsmlLocationRepository;
use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\LocationRepository;
use WP_Mock;

/**
 * Tests for TsmlLocationRepository.
 *
 * The repository is read-only: reads delegate to the factory (findById) or
 * combine get_posts with the factory (findAll and its filtered variants),
 * while the write methods deliberately throw.
 *
 * @covers \TsmlForUnity\Locations\TsmlLocationRepository
 */
class TsmlLocationRepositoryTest extends TestCase
{
    /** @var LocationFactory&\PHPUnit\Framework\MockObject\MockObject */
    private $factory;

    private TsmlLocationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        // wp_parse_args merges the caller args over the defaults.
        WP_Mock::userFunction('wp_parse_args')->andReturnUsing(
            fn ($args, $defaults) => array_merge($defaults, $args)
        );

        $this->factory = $this->createMock(LocationFactory::class);
        $this->repository = new TsmlLocationRepository($this->factory);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(LocationRepository::class, $this->repository);
    }

    /**
     * @test
     */
    public function find_by_id_delegates_to_the_factory(): void
    {
        $location = new TsmlLocation(id: 5, name: 'Hall');
        $this->factory->expects($this->once())
            ->method('createFromSource')->with(5)->willReturn($location);

        $this->assertSame($location, $this->repository->findById(5));
    }

    /**
     * @test
     */
    public function find_all_maps_every_post_through_the_factory(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([
            (object) ['ID' => 1],
            (object) ['ID' => 2],
        ]);

        $a = new TsmlLocation(id: 1, name: 'A');
        $b = new TsmlLocation(id: 2, name: 'B');
        $this->factory->method('createFromSource')
            ->willReturnMap([[1, $a], [2, $b]]);

        $this->assertSame([$a, $b], $this->repository->findAll());
    }

    /**
     * @test
     */
    public function find_by_city_queries_all_and_returns_the_matches(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([(object) ['ID' => 3]]);

        $location = new TsmlLocation(id: 3, name: 'City Hall', city: 'London');
        $this->factory->method('createFromSource')->with(3)->willReturn($location);

        $this->assertSame([$location], $this->repository->findByCity('London'));
    }

    /**
     * @test
     */
    public function find_by_region_queries_all_and_returns_the_matches(): void
    {
        WP_Mock::userFunction('get_posts')->once()->andReturn([(object) ['ID' => 4]]);

        $location = new TsmlLocation(id: 4, name: 'Regional', region: 'South');
        $this->factory->method('createFromSource')->with(4)->willReturn($location);

        $this->assertSame([$location], $this->repository->findByRegion('South'));
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
    public function save_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->save(new TsmlLocation(id: 1, name: 'X'));
    }

    /**
     * @test
     */
    public function update_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->update(new TsmlLocation(id: 1, name: 'X'));
    }

    /**
     * @test
     */
    public function delete_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->delete(1);
    }
}
