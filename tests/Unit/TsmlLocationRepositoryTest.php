<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Locations\TsmlLocation;
use TsmlForUnity\Locations\TsmlLocationRepository;
use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\LocationRepository;

/*
 * Tests for TsmlLocationRepository.
 *
 * The repository is read-only: reads delegate to the factory (findById) or
 * combine get_posts with the factory (findAll and its filtered variants),
 * while the write methods deliberately throw.
 */

covers(\TsmlForUnity\Locations\TsmlLocationRepository::class);

beforeEach(function () {
    // wp_parse_args merges the caller args over the defaults.
    Functions\expect('wp_parse_args')->andReturnUsing(
        fn ($args, $defaults) => array_merge($defaults, $args)
    );

    $this->factory = $this->createMock(LocationFactory::class);
    $this->repository = new TsmlLocationRepository($this->factory);
});

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(LocationRepository::class);
});

test('find by id delegates to the factory', function () {
    $location = new TsmlLocation(id: 5, name: 'Hall');
    $this->factory->expects($this->once())
        ->method('createFromSource')->with(5)->willReturn($location);

    expect($this->repository->findById(5))->toBe($location);
});

test('find all maps every post through the factory', function () {
    Functions\expect('get_posts')->once()->andReturn([
        (object) ['ID' => 1],
        (object) ['ID' => 2],
    ]);

    $a = new TsmlLocation(id: 1, name: 'A');
    $b = new TsmlLocation(id: 2, name: 'B');
    $this->factory->method('createFromSource')
        ->willReturnMap([[1, $a], [2, $b]]);

    expect($this->repository->findAll())->toBe([$a, $b]);
});

test('find by city queries all and returns the matches', function () {
    Functions\expect('get_posts')->once()->andReturn([(object) ['ID' => 3]]);

    $location = new TsmlLocation(id: 3, name: 'City Hall', city: 'London');
    $this->factory->method('createFromSource')->with(3)->willReturn($location);

    expect($this->repository->findByCity('London'))->toBe([$location]);
});

test('find by region queries all and returns the matches', function () {
    Functions\expect('get_posts')->once()->andReturn([(object) ['ID' => 4]]);

    $location = new TsmlLocation(id: 4, name: 'Regional', region: 'South');
    $this->factory->method('createFromSource')->with(4)->willReturn($location);

    expect($this->repository->findByRegion('South'))->toBe([$location]);
});

test('find all returns empty when there are no posts', function () {
    Functions\expect('get_posts')->once()->andReturn([]);

    expect($this->repository->findAll())->toBe([]);
});

test('save is not implemented', function () {
    $this->repository->save(new TsmlLocation(id: 1, name: 'X'));
})->throws(\Exception::class);

test('update is not implemented', function () {
    $this->repository->update(new TsmlLocation(id: 1, name: 'X'));
})->throws(\Exception::class);

test('delete is not implemented', function () {
    $this->repository->delete(1);
})->throws(\Exception::class);
