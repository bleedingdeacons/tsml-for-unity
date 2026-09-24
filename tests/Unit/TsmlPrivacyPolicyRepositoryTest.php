<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use function Brain\Monkey\Actions\expectDone;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFields;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyRepository;
use TsmlForUnity\Tests\Support\ActionExpectations;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyRepository;

/*
 * Tests for TsmlPrivacyPolicyRepository.
 */

covers(\TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyRepository::class);

uses(ActionExpectations::class);

beforeEach(function () {
    $this->factory = $this->createMock(PrivacyPolicyFactory::class);
    $this->repository = new TsmlPrivacyPolicyRepository($this->factory);
});

function policy(int $id = 0, string $title = 'Policy'): TsmlPrivacyPolicy
{
    return new TsmlPrivacyPolicy($id, $title, 'body', '1.0', true, '');
}

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(PrivacyPolicyRepository::class);
});

// ─── findById ───────────────────────────────────────────────────
test('find by id returns null for a missing post', function () {
    Functions\expect('get_post')->with(9)->andReturn(null);

    expect($this->repository->findById(9))->toBeNull();
});

test('find by id returns null for the wrong post type', function () {
    Functions\expect('get_post')->with(9)->andReturn((object) ['post_type' => 'page']);

    expect($this->repository->findById(9))->toBeNull();
});

test('find by id delegates to the factory for a matching post', function () {
    Functions\expect('get_post')->with(5)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );

    $expected = policy(5);
    $this->factory->expects($this->once())
        ->method('createFromSource')->with(5)->willReturn($expected);

    expect($this->repository->findById(5))->toBe($expected);
});

// ─── findActive / findAll / count ───────────────────────────────
test('find active returns null when no active policy exists', function () {
    Functions\expect('get_posts')->once()->andReturn([]);

    expect($this->repository->findActive())->toBeNull();
});

test('find active reads back the first matching policy', function () {
    Functions\expect('get_posts')->once()->andReturn([new \WP_Post(['ID' => 5])]);
    Functions\expect('get_post')->with(5)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );

    $active = policy(5);
    $this->factory->method('createFromSource')->with(5)->willReturn($active);

    expect($this->repository->findActive())->toBe($active);
});

test('find all maps posts through find by id', function () {
    Functions\expect('get_posts')->once()->andReturn([
        new \WP_Post(['ID' => 1]),
        new \WP_Post(['ID' => 2]),
    ]);
    Functions\expect('get_post')->andReturnUsing(
        fn ($id) => (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );

    $a = policy(1);
    $b = policy(2);
    $this->factory->method('createFromSource')->willReturnMap([[1, $a], [2, $b]]);

    expect($this->repository->findAll())->toBe([$a, $b]);
});

test('count returns the number of ids', function () {
    Functions\expect('get_posts')->once()->andReturn([10, 11, 12]);

    expect($this->repository->count())->toBe(3);
});

test('count is zero when the query finds nothing', function () {
    // Was "returns a non-array", stubbed as null. WordPress always hands
    // back an array and wp-mocks types get_posts() that way, so the null
    // this used to simulate was never reachable in production.
    Functions\expect('get_posts')->once()->andReturn([]);

    expect($this->repository->count())->toBe(0);
});

// ─── save / create / update / delete ────────────────────────────
test('save inserts a new policy and fires created', function () {
    Functions\expect('wp_insert_post')->once()->andReturn(77);
    Functions\expect('update_field')->andReturn(true);
    Functions\expect('get_post')->with(77)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );

    $created = policy(77);
    $this->factory->method('createFromSource')->with(77)->willReturn($created);

    expectDone('unity/privacy_policy_created')->once()->with($created);

    expect($this->repository->save(policy(0)))->toBeTrue();
});

test('save returns false when the insert fails', function () {
    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_insert_post')->once()->andReturn($error);

    expect($this->repository->save(policy(0)))->toBeFalse();
});

test('save with an existing id delegates to update', function () {
    // update() path: no wp_insert_post, uses wp_update_post instead.
    Functions\expect('get_post')->with(5)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );
    Functions\expect('wp_update_post')->once()->andReturn(5);
    Functions\expect('update_field')->andReturn(true);

    $persisted = policy(5);
    $this->factory->method('createFromSource')->with(5)->willReturn($persisted);

    // Both before/after snapshots resolve to the same re-read instance.
    expectDone('unity/privacy_policy_changing')->once()->with($persisted, $persisted);

    expect($this->repository->save(policy(5)))->toBeTrue();
});

test('create inserts a titled post and returns its id', function () {
    Functions\expect('wp_insert_post')->once()->andReturn(88);
    Functions\expect('get_post')->with(88)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );
    $created = policy(88);
    $this->factory->method('createFromSource')->with(88)->willReturn($created);

    expectDone('unity/privacy_policy_created')->once()->with($created);

    expect($this->repository->create('New Policy'))->toBe(88);
});

test('create returns zero when the insert fails', function () {
    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_insert_post')->once()->andReturn($error);

    expect($this->repository->create('New Policy'))->toBe(0);
});

test('update returns false for a zero id', function () {
    expect($this->repository->update(policy(0)))->toBeFalse();
});

test('update returns false when wp update post fails', function () {
    Functions\expect('get_post')->with(5)->andReturn(
        (object) ['post_type' => TsmlPrivacyPolicyFields::POST_TYPE]
    );
    $this->factory->method('createFromSource')->with(5)->willReturn(policy(5));

    $error = new \WP_Error('db_error', 'the write failed');
    Functions\expect('wp_update_post')->once()->andReturn($error);

    expect($this->repository->update(policy(5)))->toBeFalse();
});

test('delete force deletes the post', function () {
    Functions\expect('wp_delete_post')->once()->with(5, true)->andReturn((object) ['ID' => 5]);

    expect($this->repository->delete(5))->toBeTrue();
});

test('delete returns false when the post cannot be removed', function () {
    Functions\expect('wp_delete_post')->once()->with(5, true)->andReturn(false);

    expect($this->repository->delete(5))->toBeFalse();
});
