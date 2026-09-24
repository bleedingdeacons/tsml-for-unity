<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use Exception;
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

/*
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

beforeEach(function () {
    // wp_parse_args is pure; mirror it rather than assert against a stub.
    Functions\expect('wp_parse_args')
        ->andReturnUsing(static fn ($args, $defaults = []) => array_merge($defaults, (array) $args));

    $this->capturedArgs = [];
});

/** Stub get_posts(), capturing the arguments and returning $posts. */
function queryCoverageStubGetPosts(array $posts): void
{
    Functions\expect('get_posts')->andReturnUsing(function ($args) use ($posts) {
        test()->capturedArgs = (array) $args;

        return $posts;
    });
}

function postObjects(int ...$ids): array
{
    return array_map(static fn (int $id): object => (object) ['ID' => $id], $ids);
}

// ══ Group repository ══════════════════════════════════════════════
test('group find by id delegates straight to the factory', function () {
    $group = $this->createMock(Group::class);
    $factory = $this->createMock(GroupFactory::class);
    $factory->expects($this->once())->method('createFromSource')->with(7)->willReturn($group);

    expect((new TsmlGroupRepository($factory))->findById(7))->toBe($group);
});

test('group find all queries published groups and hydrates each post', function () {
    queryCoverageStubGetPosts(postObjects(1, 2));

    $factory = $this->createMock(GroupFactory::class);
    $factory->expects($this->exactly(2))
        ->method('createFromSource')
        ->willReturn($this->createMock(Group::class));

    $groups = (new TsmlGroupRepository($factory))->findAll();

    expect($groups)->toHaveCount(2)
        ->and($this->capturedArgs['post_type'])->toBe(TsmlGroupFields::POST_TYPE)
        ->and($this->capturedArgs['post_status'])->toBe('publish')
        ->and($this->capturedArgs['posts_per_page'])->toBe(-1);
});

test('group find all skips posts without an id', function () {
    // A malformed row must not reach the factory as ID 0.
    queryCoverageStubGetPosts([(object) ['ID' => 0], (object) ['ID' => 3]]);

    $factory = $this->createMock(GroupFactory::class);
    $factory->expects($this->once())
        ->method('createFromSource')
        ->with(3)
        ->willReturn($this->createMock(Group::class));

    expect((new TsmlGroupRepository($factory))->findAll())->toHaveCount(1);
});

test('group find all drops posts the factory rejects', function () {
    queryCoverageStubGetPosts(postObjects(1, 2));

    $factory = $this->createMock(GroupFactory::class);
    $factory->method('createFromSource')
        ->willReturnOnConsecutiveCalls($this->createMock(Group::class), null);

    expect((new TsmlGroupRepository($factory))->findAll())->toHaveCount(1);
});

test('group count forces an ids only query', function () {
    queryCoverageStubGetPosts([1, 2, 3]);

    $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

    // Even asked for full posts and a page size, count() overrides both.
    expect($repository->count(['fields' => 'all', 'posts_per_page' => 10]))->toBe(3)
        ->and($this->capturedArgs['fields'])->toBe('ids')
        ->and($this->capturedArgs['posts_per_page'])->toBe(-1);
});

test('group count is zero when the query returns nothing usable', function () {
    // WordPress always hands back an array; wp-mocks types get_posts() that
    // way, so the old andReturn(null) is no longer expressible — and was
    // never reachable in production either.
    Functions\expect('get_posts')->andReturn([]);

    $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

    expect($repository->count())->toBe(0);
});

test('group delete is explicitly unimplemented', function () {
    $repository = new TsmlGroupRepository($this->createMock(GroupFactory::class));

    $repository->delete(1);
})->throws(Exception::class, 'Delete is not implemented');

// ══ Group factory setters ═════════════════════════════════════════
test('group factory dependencies can be supplied after construction', function () {
    $factory = new TsmlGroupFactory();

    $factory->setContactFactory($this->createMock(ContactFactory::class));
    $factory->setMeetingRepository($this->createMock(MeetingRepository::class));

    expect($factory)->toBeInstanceOf(GroupFactory::class);
});

// ══ Position repository ═══════════════════════════════════════════
test('position find by id delegates straight to the factory', function () {
    $position = $this->createMock(Position::class);
    $factory = $this->createMock(PositionFactory::class);
    $factory->expects($this->once())->method('createFromSource')->with(9)->willReturn($position);

    expect((new TsmlPositionRepository($factory))->findById(9))->toBe($position);
});

test('position find all queries published positions', function () {
    queryCoverageStubGetPosts(postObjects(1, 2, 3));

    $factory = $this->createMock(PositionFactory::class);
    $factory->method('createFromSource')->willReturn($this->createMock(Position::class));

    expect((new TsmlPositionRepository($factory))->findAll())->toHaveCount(3)
        ->and($this->capturedArgs['post_type'])->toBe(TsmlPositionFields::POST_TYPE)
        ->and($this->capturedArgs['post_status'])->toBe('publish');
});

test('position find all drops posts the factory rejects', function () {
    queryCoverageStubGetPosts(postObjects(1, 2));

    $factory = $this->createMock(PositionFactory::class);
    $factory->method('createFromSource')
        ->willReturnOnConsecutiveCalls(null, $this->createMock(Position::class));

    expect((new TsmlPositionRepository($factory))->findAll())->toHaveCount(1);
});

test('position count asks only for ids', function () {
    queryCoverageStubGetPosts([1, 2]);

    $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

    expect($repository->count())->toBe(2)
        ->and($this->capturedArgs['fields'])->toBe('ids');
});

test('position count is zero when the query returns nothing usable', function () {
    // WordPress always hands back an array; wp-mocks types get_posts() that
    // way, so the old andReturn(null) is no longer expressible — and was
    // never reachable in production either.
    Functions\expect('get_posts')->andReturn([]);

    $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

    expect($repository->count())->toBe(0);
});

test('position delete is explicitly unimplemented', function () {
    $repository = new TsmlPositionRepository($this->createMock(PositionFactory::class));

    $repository->delete(1);
})->throws(Exception::class, 'Delete is not implemented');

// ══ Member repository ═════════════════════════════════════════════
test('member count asks only for ids', function () {
    queryCoverageStubGetPosts([1, 2, 3, 4]);

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->count())->toBe(4)
        ->and($this->capturedArgs['post_type'])->toBe(TsmlMemberFields::POST_TYPE)
        ->and($this->capturedArgs['fields'])->toBe('ids');
});

test('member count is zero when the query returns nothing usable', function () {
    // WordPress always hands back an array; wp-mocks types get_posts() that
    // way, so the old andReturn(null) is no longer expressible — and was
    // never reachable in production either.
    Functions\expect('get_posts')->andReturn([]);

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->count())->toBe(0);
});

test('creating a member inserts a post and mirrors the name into acf', function () {
    Functions\expect('wp_insert_post')->andReturn(77);
    Functions\expect('update_field')->andReturn(true);
    // do_action() is not stubbed: Brain Monkey owns it, and overriding it
    // here would take the call out of the container every hook assertion
    // in this suite reads from.
    Functions\expect('get_post')->andReturn(null);

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->create('Anonymous Alex'))->toBe(77);
});

test('a failed member insert reports zero', function () {
    Functions\expect('wp_insert_post')->andReturn(new \WP_Error('db_error', 'the write failed'));

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->create('Anonymous Alex'))->toBe(0);
});

test('deleting a member forces a permanent delete', function () {
    $captured = [];
    Functions\expect('wp_delete_post')->andReturnUsing(
        function ($id, $force = false) use (&$captured) {
            $captured = [$id, $force];

            return (object) ['ID' => $id];
        }
    );

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->delete(5))->toBeTrue()
        ->and($captured)->toBe([5, true], 'Members are hard-deleted, not trashed.');
});

test('a failed member delete reports false', function () {
    Functions\expect('wp_delete_post')->andReturn(false);

    $repository = new TsmlMemberRepository($this->createMock(MemberFactory::class));

    expect($repository->delete(5))->toBeFalse();
});

// ══ Member factory ════════════════════════════════════════════════
test('create new builds a member from explicit values', function () {
    $member = (new TsmlMemberFactory())->createNew(
        id: 42,
        anonymousName: 'Anonymous Alex',
        showAnonymousName: true,
        personalEmail: 'alex@example.test',
        telephoneResponder: true,
        responderCertification: ResponderCertification::Certified,
    );

    expect($member)->toBeInstanceOf(Member::class)
        ->and($member->getId())->toBe(42)
        ->and($member->getAnonymousName())->toBe('Anonymous Alex')
        ->and($member->showAnonymousName())->toBeTrue()
        ->and($member->getPersonalEmail())->toBe('alex@example.test')
        ->and($member->isTelephoneResponder())->toBeTrue()
        ->and($member->getResponderCertification())->toBe(ResponderCertification::Certified);
});

test('create new defaults every optional value', function () {
    $member = (new TsmlMemberFactory())->createNew(id: 1);

    expect($member->getAnonymousName())->toBe('')
        ->and($member->showAnonymousName())->toBeFalse()
        ->and($member->isTwelfthStepper())->toBeFalse()
        ->and($member->isGdprAccepted())->toBeFalse();
    // A member with no certification recorded sits at None.
    expect($member->getResponderCertification())->toBe(ResponderCertification::None);
});
