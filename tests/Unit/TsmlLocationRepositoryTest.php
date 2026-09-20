<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\Locations\TsmlLocation;
use TsmlForUnity\Locations\TsmlLocationRepository;
use TsmlForUnity\Tests\TestCase;
use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\LocationRepository;

/**
 * Tests for TsmlLocationRepository.
 *
 * The repository is read-only: reads delegate to the factory (findById) or
 * combine get_posts with the factory (findAll and its filtered variants),
 * while the write methods deliberately throw.
 */
#[CoversClass(\TsmlForUnity\Locations\TsmlLocationRepository::class)]
class TsmlLocationRepositoryTest extends TestCase
{
    /** @var LocationFactory&MockObject */
    private $factory;

    private TsmlLocationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // wp_parse_args merges the caller args over the defaults.
        expect('wp_parse_args')->andReturnUsing(
            fn ($args, $defaults) => array_merge($defaults, $args)
        );

        $this->factory = $this->createMock(LocationFactory::class);
        $this->repository = new TsmlLocationRepository($this->factory);
    }

    #[Test]
    public function it_implements_the_repository_interface(): void
    {
        $this->assertInstanceOf(LocationRepository::class, $this->repository);
    }

    #[Test]
    public function find_by_id_delegates_to_the_factory(): void
    {
        $location = new TsmlLocation(id: 5, name: 'Hall');
        $this->factory->expects($this->once())
            ->method('createFromSource')->with(5)->willReturn($location);

        $this->assertSame($location, $this->repository->findById(5));
    }

    #[Test]
    public function find_all_maps_every_post_through_the_factory(): void
    {
        expect('get_posts')->once()->andReturn([
            (object) ['ID' => 1],
            (object) ['ID' => 2],
        ]);

        $a = new TsmlLocation(id: 1, name: 'A');
        $b = new TsmlLocation(id: 2, name: 'B');
        $this->factory->method('createFromSource')
            ->willReturnMap([[1, $a], [2, $b]]);

        $this->assertSame([$a, $b], $this->repository->findAll());
    }

    #[Test]
    public function find_by_city_queries_all_and_returns_the_matches(): void
    {
        expect('get_posts')->once()->andReturn([(object) ['ID' => 3]]);

        $location = new TsmlLocation(id: 3, name: 'City Hall', city: 'London');
        $this->factory->method('createFromSource')->with(3)->willReturn($location);

        $this->assertSame([$location], $this->repository->findByCity('London'));
    }

    #[Test]
    public function find_by_region_queries_all_and_returns_the_matches(): void
    {
        expect('get_posts')->once()->andReturn([(object) ['ID' => 4]]);

        $location = new TsmlLocation(id: 4, name: 'Regional', region: 'South');
        $this->factory->method('createFromSource')->with(4)->willReturn($location);

        $this->assertSame([$location], $this->repository->findByRegion('South'));
    }

    #[Test]
    public function find_all_returns_empty_when_there_are_no_posts(): void
    {
        expect('get_posts')->once()->andReturn([]);

        $this->assertSame([], $this->repository->findAll());
    }

    #[Test]
    public function save_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->save(new TsmlLocation(id: 1, name: 'X'));
    }

    #[Test]
    public function update_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->update(new TsmlLocation(id: 1, name: 'X'));
    }

    #[Test]
    public function delete_is_not_implemented(): void
    {
        $this->expectException(\Exception::class);
        $this->repository->delete(1);
    }
}
