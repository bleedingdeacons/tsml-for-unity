<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeeting;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingFactory;
use Unity\IntergroupMeetings\Interfaces\IntergroupMeetingRepository;

/*
 * Tests for TsmlIntergroupMeetingRepository.
 */

covers(\TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingRepository::class);

beforeEach(function () {
    $this->factory = $this->createMock(IntergroupMeetingFactory::class);
    $this->repository = new TsmlIntergroupMeetingRepository($this->factory);
});

function meetingPost(): object
{
    return (object) ['post_type' => TsmlIntergroupMeetingFields::POST_TYPE];
}

it('implements the repository interface', function () {
    expect($this->repository)->toBeInstanceOf(IntergroupMeetingRepository::class);
});

test('find by id returns null for a missing post', function () {
    Functions\expect('get_post')->with(9)->andReturn(null);

    expect($this->repository->findById(9))->toBeNull();
});

test('find by id returns null for the wrong post type', function () {
    Functions\expect('get_post')->with(9)->andReturn((object) ['post_type' => 'page']);

    expect($this->repository->findById(9))->toBeNull();
});

test('find by id delegates to the factory', function () {
    Functions\expect('get_post')->with(5)->andReturn(meetingPost());

    $meeting = new TsmlIntergroupMeeting(id: 5, title: 'July');
    $this->factory->expects($this->once())
        ->method('createFromSource')->with(5)->willReturn($meeting);

    expect($this->repository->findById(5))->toBe($meeting);
});

test('find all maps posts through find by id', function () {
    Functions\expect('get_posts')->once()->andReturn([
        (object) ['ID' => 1],
        (object) ['ID' => 2],
    ]);
    Functions\expect('get_post')->andReturn(meetingPost());

    $a = new TsmlIntergroupMeeting(id: 1);
    $b = new TsmlIntergroupMeeting(id: 2);
    $this->factory->method('createFromSource')->willReturnMap([[1, $a], [2, $b]]);

    expect($this->repository->findAll())->toBe([$a, $b]);
});

test('find all returns empty when there are no posts', function () {
    Functions\expect('get_posts')->once()->andReturn([]);

    expect($this->repository->findAll())->toBe([]);
});

test('count returns the number of ids', function () {
    Functions\expect('get_posts')->once()->andReturn([10, 11]);

    expect($this->repository->count())->toBe(2);
});

test('count translates pagination args without error', function () {
    // posts_per_page → numberposts and paged → offset are handled in
    // buildQueryArgs; the count path then forces numberposts -1.
    Functions\expect('get_posts')->once()->andReturn([1, 2, 3]);

    expect($this->repository->count([
        'posts_per_page' => 10,
        'paged' => 2,
    ]))->toBe(3);
});

test('save writes both relationship fields by key', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])->andReturn([]);

    $writes = [];
    Functions\expect('update_field')->andReturnUsing(
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

    expect($this->repository->save($meeting))->toBeTrue();

    // Field keys come from the resolver's hardcoded fallbacks.
    expect($writes[TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDEES])->toBe([1, 2])
        ->and($writes[TsmlIntergroupMeetingFields::FIELD_KEY_ATTENDING_OFFICERS])->toBe([3]);
});

test('delete force deletes the post', function () {
    Functions\expect('wp_delete_post')->once()->with(5, true)->andReturn((object) ['ID' => 5]);

    expect($this->repository->delete(5))->toBeTrue();
});

test('delete returns false when removal fails', function () {
    Functions\expect('wp_delete_post')->once()->with(5, true)->andReturn(false);

    expect($this->repository->delete(5))->toBeFalse();
});
