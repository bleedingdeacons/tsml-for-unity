<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Meetings\TsmlMeetingRepository;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use WP_Post;

/*
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
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingRepository::class);

beforeEach(function () {
    $this->factory = $this->createMock(MeetingFactory::class);
    $this->repository = new TsmlMeetingRepository($this->factory);
    $this->capturedArgs = [];
});

function meetingRepositoryPost(int $id = 1, string $type = TsmlMeetingFields::POST_TYPE): WP_Post
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
function meetingRepositoryStubGetPosts(array $posts): void
{
    Functions\expect('get_posts')->andReturnUsing(function (array $args) use ($posts): array {
        test()->capturedArgs = $args;

        return $posts;
    });
}

function stubPostMeta(array $meta = []): void
{
    Functions\expect('get_post_meta')->andReturn($meta);
}

function repositoryMeeting(bool $online = false): Meeting
{
    $meeting = test()->createMock(Meeting::class);
    $meeting->method('isOnline')->willReturn($online);

    return $meeting;
}

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(MeetingRepository::class);
});

// ─── findById ───────────────────────────────────────────────────
test('find by id rejects a non positive id without touching wordpress', function () {
    expect($this->repository->findById(0))->toBeNull()
        ->and($this->repository->findById(-1))->toBeNull();
});

test('find by id builds a meeting from the post', function () {
    Functions\expect('get_post')->andReturn(meetingRepositoryPost(7));
    stubPostMeta(['day' => ['2'], 'group_id' => ['99']]);

    $expected = repositoryMeeting();
    $this->factory->expects($this->once())
        ->method('createFromSource')
        ->with($this->callback(function (array $source): bool {
            // Meta is both nested under 'meta' and flattened to the top
            // level, so callers can reach a value either way.
            expect($source['id'])->toBe(7)
                ->and($source['name'])->toBe('Tuesday Meeting')
                ->and($source['slug'])->toBe('tuesday-meeting')
                ->and($source['location_id'])->toBe(55)
                ->and($source['day'])->toBe('2')
                ->and($source['meta']['day'])->toBe(['2']);

            return true;
        }))
        ->willReturn($expected);

    expect($this->repository->findById(7))->toBe($expected);
});

test('find by id returns null when the post is missing', function () {
    Functions\expect('get_post')->andReturn(null);

    expect($this->repository->findById(7))->toBeNull();
});

test('find by id returns null for a post of the wrong type', function () {
    Functions\expect('get_post')->andReturn(meetingRepositoryPost(7, 'page'));

    expect($this->repository->findById(7))->toBeNull();
});

it('caches nothing of its own', function () {
    // It used to hold an hour-long cache that nothing ever invalidated,
    // so an edited meeting kept serving its old day and time once an
    // object cache made entries outlive the request. Caching now lives in
    // Unity's CachingMeetingRepository, which wraps this one and is
    // cleared by PostTypeCacheInvalidator.
    Functions\expect('get_post')->twice()->andReturn(meetingRepositoryPost(7));
    stubPostMeta();
    $this->factory->method('createFromSource')->willReturn(repositoryMeeting());

    $this->repository->findById(7);
    $this->repository->findById(7);
});

// ─── findAll ────────────────────────────────────────────────────
test('find all applies the documented defaults', function () {
    meetingRepositoryStubGetPosts([]);

    expect($this->repository->findAll())->toBe([]);

    expect($this->capturedArgs['post_type'])->toBe(TsmlMeetingFields::POST_TYPE)
        ->and($this->capturedArgs['post_status'])->toBe('publish')
        ->and($this->capturedArgs['posts_per_page'])->toBe(100)
        ->and($this->capturedArgs['orderby'])->toBe('title')
        ->and($this->capturedArgs['order'])->toBe('ASC');
});

test('caller arguments override the defaults', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findAll(['posts_per_page' => 5, 'order' => 'DESC']);

    expect($this->capturedArgs['posts_per_page'])->toBe(5)
        ->and($this->capturedArgs['order'])->toBe('DESC');
});

test('find all builds a meeting for every post', function () {
    meetingRepositoryStubGetPosts([meetingRepositoryPost(1), meetingRepositoryPost(2)]);
    stubPostMeta();
    $this->factory->method('createFromSource')->willReturn(repositoryMeeting());

    expect($this->repository->findAll())->toHaveCount(2);
});

test('posts the factory rejects are skipped', function () {
    meetingRepositoryStubGetPosts([meetingRepositoryPost(1), meetingRepositoryPost(2)]);
    stubPostMeta();
    // The second post yields nothing; the result should close over the gap.
    $this->factory->method('createFromSource')
        ->willReturnOnConsecutiveCalls(repositoryMeeting(), null);

    expect($this->repository->findAll())->toHaveCount(1);
});

// ─── findByDay ──────────────────────────────────────────────────
test('find by day adds a day meta query', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findByDay(2);

    expect($this->capturedArgs['meta_query'][0])->toBe(['key' => 'day', 'value' => '2', 'compare' => '='], 'The day is compared as a string, matching how WordPress stores meta.');
});

test('find by day ands itself onto an existing meta query', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findByDay(2, [
        'meta_query' => [['key' => 'region', 'value' => 'north']],
    ]);

    expect($this->capturedArgs['meta_query']['relation'])->toBe('AND')
        ->and($this->capturedArgs['meta_query'])->toHaveCount(3, 'existing clause + relation + day');
});

test('find by day leaves an explicit relation alone', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findByDay(2, [
        'meta_query' => ['relation' => 'OR', ['key' => 'region', 'value' => 'north']],
    ]);

    expect($this->capturedArgs['meta_query']['relation'])->toBe('OR');
});

// ─── online / in person ─────────────────────────────────────────
test('find online keeps only online meetings', function () {
    meetingRepositoryStubGetPosts([meetingRepositoryPost(1), meetingRepositoryPost(2), meetingRepositoryPost(3)]);
    stubPostMeta();
    $this->factory->method('createFromSource')->willReturnOnConsecutiveCalls(
        repositoryMeeting(true),
        repositoryMeeting(false),
        repositoryMeeting(true),
    );

    $online = $this->repository->findOnline();

    expect($online)->toHaveCount(2);
    // Re-indexed, so callers can rely on a list rather than a sparse array.
    expect(array_keys($online))->toBe([0, 1]);
});

test('find in person keeps only the meetings that are not online', function () {
    meetingRepositoryStubGetPosts([meetingRepositoryPost(1), meetingRepositoryPost(2)]);
    stubPostMeta();
    $this->factory->method('createFromSource')->willReturnOnConsecutiveCalls(
        repositoryMeeting(true),
        repositoryMeeting(false),
    );

    $inPerson = $this->repository->findInPerson();

    expect($inPerson)->toHaveCount(1)
        ->and(array_keys($inPerson))->toBe([0]);
});

// ─── findByGroupId / findByLocationId ───────────────────────────
test('find by group id adds a group meta query', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findByGroupId(99);

    expect($this->capturedArgs['meta_query'][0])->toBe(['key' => 'group_id', 'value' => 99, 'compare' => '=']);
});

test('find by group id rejects a non positive id', function () {
    expect($this->repository->findByGroupId(0))->toBe([])
        ->and($this->repository->findByGroupId(-5))->toBe([]);
});

test('find by location id adds a location meta query', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->findByLocationId(55);

    expect($this->capturedArgs['meta_query'][0])->toBe(['key' => 'location_id', 'value' => 55, 'compare' => '=']);
});

test('find by location id rejects a non positive id', function () {
    expect($this->repository->findByLocationId(0))->toBe([])
        ->and($this->repository->findByLocationId(-5))->toBe([]);
});

// ─── search ─────────────────────────────────────────────────────
test('search passes the keyword through as a post search', function () {
    meetingRepositoryStubGetPosts([]);

    $this->repository->search('serenity');

    expect($this->capturedArgs['s'])->toBe('serenity');
});

test('an empty search returns nothing without querying', function () {
    expect($this->repository->search(''))->toBe([]);
});

// ─── count ──────────────────────────────────────────────────────
test('count asks only for ids and returns the total', function () {
    meetingRepositoryStubGetPosts([1, 2, 3, 4]);

    expect($this->repository->count())->toBe(4);

    expect($this->capturedArgs['fields'])->toBe('ids', 'Counting should not hydrate posts.')
        ->and($this->capturedArgs['posts_per_page'])->toBe(-1);
});

test('count honours caller arguments', function () {
    meetingRepositoryStubGetPosts([1]);

    expect($this->repository->count(['post_status' => 'draft']))->toBe(1);

    expect($this->capturedArgs['post_status'])->toBe('draft');
});
