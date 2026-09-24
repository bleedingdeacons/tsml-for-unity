<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use Brain\Monkey\Functions;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Contacts\Interfaces\Contact;
use Unity\Groups\Interfaces\Group;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;

covers(\TsmlForUnity\Groups\TsmlGroupFactory::class);

beforeEach(function () {
    // A group's meetings come from the MeetingRepository, so the factory
    // needs one to return anything but an empty list. The contact factory
    // is left to its default on purpose, which exercises that fallback.
    $this->meetingRepository = $this->createMock(MeetingRepository::class);
    $this->factory = new TsmlGroupFactory(null, $this->meetingRepository);
});

it('returns null when post does not exist', function () {
    Functions\expect('get_post')
        ->once()
        ->with(999)
        ->andReturn(null);

    $result = $this->factory->createFromSource(999);

    expect($result)->toBeNull();
});

it('returns null when post is wrong type', function () {
    $post = groupMockPost([
        'ID' => 123,
        'post_type' => 'post', // Wrong type, should be 'tsml_group'
        'post_title' => 'Wrong Post Type',
    ]);

    Functions\expect('get_post')
        ->once()
        ->with(123)
        ->andReturn($post);

    $result = $this->factory->createFromSource(123);

    expect($result)->toBeNull();
});

it('creates group from valid post', function () {
    $postId = 100;
    $post = groupMockPost([
        'ID' => $postId,
        'post_type' => TsmlGroupFields::POST_TYPE,
        'post_title' => 'Test Group',
    ]);

    $meta = [
        TsmlGroupFields::EMAIL => ['group@example.com'],
        TsmlGroupFields::GROUP_NOTES => ['Some notes about the group'],
        TsmlGroupFields::WEBSITE => ['https://testgroup.org'],
        TsmlGroupFields::PHONE => ['555-1234'],
        TsmlGroupFields::VENMO => ['@TestGroup'],
        TsmlGroupFields::PAYPAL => ['TestGroupAA'],
        TsmlGroupFields::SQUARE => ['$TestGroup'],
        TsmlGroupFields::DISTRICT_ID => ['42'],
        'contact_1_name' => ['John Doe'],
        'contact_1_email' => ['john@example.com'],
        'contact_1_phone' => ['555-5678'],
    ];

    Functions\expect('get_post')
        ->once()
        ->with($postId)
        ->andReturn($post);

    Functions\expect('get_post_custom')
        ->once()
        ->with($postId)
        ->andReturn($meta);

    Functions\expect('maybe_unserialize')
        ->andReturnUsing(function ($value) {
            return $value;
        });

    $this->meetingRepository->method('findByGroupId')
        ->with($postId)
        ->willReturn([
            createMeeting(200),
            createMeeting(201),
            createMeeting(202),
        ]);

    Functions\expect('get_permalink')
        ->once()
        ->with($postId)
        ->andReturn('https://example.com/group/test-group');

    $result = $this->factory->createFromSource($postId);

    expect($result)->toBeInstanceOf(Group::class)
        ->and($result->getId())->toEqual($postId)
        ->and($result->getTitle())->toEqual('Test Group')
        ->and($result->getEmail())->toEqual('group@example.com')
        ->and(array_map(static fn (Meeting $meeting): int => $meeting->getId(), $result->getMeetings()))->toEqual([200, 201, 202])
        ->and($result->getLink())->toEqual('https://example.com/group/test-group')
        ->and($result->getGroupNotes())->toEqual('Some notes about the group')
        ->and($result->getWebsite())->toEqual('https://testgroup.org')
        ->and($result->getPhone())->toEqual('555-1234')
        ->and($result->getVenmo())->toEqual('@TestGroup')
        ->and($result->getPaypal())->toEqual('TestGroupAA')
        ->and($result->getSquare())->toEqual('$TestGroup')
        ->and($result->getDistrictId())->toEqual(42);
});

it('extracts multiple contacts', function () {
    $postId = 200;
    $post = groupMockPost([
        'ID' => $postId,
        'post_type' => TsmlGroupFields::POST_TYPE,
        'post_title' => 'Multi-TsmlContact Group',
    ]);

    $meta = [
        'contact_1_name' => ['John Doe'],
        'contact_1_email' => ['john@example.com'],
        'contact_1_phone' => ['555-1111'],
        'contact_2_name' => ['Jane Smith'],
        'contact_2_email' => ['jane@example.com'],
        'contact_2_phone' => ['555-2222'],
        'contact_3_name' => ['Bob Wilson'],
        'contact_3_email' => ['bob@example.com'],
        'contact_3_phone' => ['555-3333'],
    ];

    Functions\expect('get_post')
        ->once()
        ->with($postId)
        ->andReturn($post);

    Functions\expect('get_post_custom')
        ->once()
        ->with($postId)
        ->andReturn($meta);

    Functions\expect('maybe_unserialize')
        ->andReturnUsing(function ($value) {
            return $value;
        });

    Functions\expect('get_permalink')
        ->once()
        ->with($postId)
        ->andReturn('');

    $result = $this->factory->createFromSource($postId);

    expect($result)->toBeInstanceOf(Group::class);

    $contacts = $result->getContacts();
    expect($contacts)->toHaveCount(3);

    expect($contacts[0])->toBeInstanceOf(Contact::class)
        ->and($contacts[0]->getName())->toEqual('John Doe')
        ->and($contacts[0]->getEmail())->toEqual('john@example.com')
        ->and($contacts[0]->getPhone())->toEqual('555-1111');

    expect($contacts[1])->toBeInstanceOf(Contact::class)
        ->and($contacts[1]->getName())->toEqual('Jane Smith')
        ->and($contacts[1]->getEmail())->toEqual('jane@example.com')
        ->and($contacts[1]->getPhone())->toEqual('555-2222');

    expect($contacts[2])->toBeInstanceOf(Contact::class)
        ->and($contacts[2]->getName())->toEqual('Bob Wilson')
        ->and($contacts[2]->getEmail())->toEqual('bob@example.com')
        ->and($contacts[2]->getPhone())->toEqual('555-3333');
});

it('handles empty meta', function () {
    $postId = 300;
    $post = groupMockPost([
        'ID' => $postId,
        'post_type' => TsmlGroupFields::POST_TYPE,
        'post_title' => 'Minimal Group',
    ]);

    Functions\expect('get_post')
        ->once()
        ->with($postId)
        ->andReturn($post);

    Functions\expect('get_post_custom')
        ->once()
        ->with($postId)
        ->andReturn([]);

    Functions\expect('get_permalink')
        ->once()
        ->with($postId)
        ->andReturn('');

    $result = $this->factory->createFromSource($postId);

    expect($result)->toBeInstanceOf(Group::class)
        ->and($result->getId())->toEqual($postId)
        ->and($result->getTitle())->toEqual('Minimal Group')
        ->and($result->getEmail())->toEqual('')
        ->and($result->getMeetings())->toEqual([])
        ->and($result->getGroupNotes())->toEqual('')
        ->and($result->getDistrictId())->toBeNull()
        ->and($result->getContacts())->toEqual([]);
});

it('handles partial contact info', function () {
    $postId = 400;
    $post = groupMockPost([
        'ID' => $postId,
        'post_type' => TsmlGroupFields::POST_TYPE,
        'post_title' => 'Partial TsmlContact Group',
    ]);

    $meta = [
        'contact_1_name' => ['John Doe'],
        // Missing email and phone for contact 1
        'contact_2_email' => ['jane@example.com'],
        // Missing name and phone for contact 2
    ];

    Functions\expect('get_post')
        ->once()
        ->with($postId)
        ->andReturn($post);

    Functions\expect('get_post_custom')
        ->once()
        ->with($postId)
        ->andReturn($meta);

    Functions\expect('maybe_unserialize')
        ->andReturnUsing(function ($value) {
            return $value;
        });

    Functions\expect('get_permalink')
        ->once()
        ->with($postId)
        ->andReturn('');

    $result = $this->factory->createFromSource($postId);

    $contacts = $result->getContacts();
    expect($contacts)->toHaveCount(2);

    // First contact has name only
    expect($contacts[0])->toBeInstanceOf(Contact::class)
        ->and($contacts[0]->getName())->toEqual('John Doe')
        ->and($contacts[0]->getEmail())->toEqual('')
        ->and($contacts[0]->getPhone())->toEqual('');

    // Second contact has email only
    expect($contacts[1])->toBeInstanceOf(Contact::class)
        ->and($contacts[1]->getName())->toEqual('')
        ->and($contacts[1]->getEmail())->toEqual('jane@example.com')
        ->and($contacts[1]->getPhone())->toEqual('');
});

it('handles false permalink', function () {
    $postId = 500;
    $post = groupMockPost([
        'ID' => $postId,
        'post_type' => TsmlGroupFields::POST_TYPE,
        'post_title' => 'Test',
    ]);

    Functions\expect('get_post')
        ->once()
        ->with($postId)
        ->andReturn($post);

    Functions\expect('get_post_custom')
        ->once()
        ->with($postId)
        ->andReturn([]);

    Functions\expect('get_permalink')
        ->once()
        ->with($postId)
        ->andReturn(false);

    $result = $this->factory->createFromSource($postId);

    expect($result)->toBeInstanceOf(Group::class)
        ->and($result->getLink())->toEqual('');
});

/**
 * Create a Meeting that reports the given ID and carries no contacts.
 *
 * @param int $id Meeting ID.
 * @return Meeting&MockObject
 */
function createMeeting(int $id)
{
    $meeting = test()->createMock(Meeting::class);
    $meeting->method('getId')->willReturn($id);
    $meeting->method('getContacts')->willReturn([]);

    return $meeting;
}

/**
 * Create a mock WP_Post object
 *
 * @param array $properties Post properties
 * @return object Mock post object
 */
function groupMockPost(array $properties): object
{
    return (object) array_merge([
        'ID' => 0,
        'post_title' => '',
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_content' => '',
    ], $properties);
}
