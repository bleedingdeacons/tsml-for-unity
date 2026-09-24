<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use Exception;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Contacts\Interfaces\Contact;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;

/*
 * Tests for how a group acquires its meetings and their contacts.
 *
 * A group's contact list is the union of its own contacts and those of
 * every meeting it holds, deduplicated on name|email|phone. That matters
 * because the same trusted servant is usually listed on both the group and
 * its meetings; without the dedup the group screen shows them repeatedly.
 *
 * The meeting lookup is also allowed to fail — the repository is optional
 * and may throw — and a group must still be built rather than the whole
 * page dying because one lookup went wrong.
 */

covers(\TsmlForUnity\Groups\TsmlGroupFactory::class);

const GROUP_ID = 42;

beforeEach(function () {
    Functions\expect('get_post')->andReturn((object) [
        'ID'          => GROUP_ID,
        'post_title'  => 'Tuesday Group',
        'post_type'   => TsmlGroupFields::POST_TYPE,
        'post_status' => 'publish',
        'post_content' => '',
    ]);
    Functions\expect('get_post_custom')->andReturn([]);
    Functions\expect('get_permalink')->andReturn('https://example.test/group/42');
});

function factoryContact(string $name, string $email = '', string $phone = ''): Contact
{
    $contact = test()->createMock(Contact::class);
    $contact->method('getName')->willReturn($name);
    $contact->method('getEmail')->willReturn($email);
    $contact->method('getPhone')->willReturn($phone);

    return $contact;
}

/** @param Contact[] $contacts */
function factoryMeeting(array $contacts): Meeting
{
    $meeting = test()->createMock(Meeting::class);
    $meeting->method('getId')->willReturn(7);
    $meeting->method('getContacts')->willReturn($contacts);

    return $meeting;
}

function groupFactoryWith(MeetingRepository $repository): TsmlGroupFactory
{
    return new TsmlGroupFactory(null, $repository);
}

test('contacts from meetings are added to the group', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->method('findByGroupId')->willReturn([
        factoryMeeting([factoryContact('Alex', 'alex@example.test', '0117')]),
    ]);

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    expect($group)->not->toBeNull();
    $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
    expect($names)->toContain('Alex');
});

test('the same contact on two meetings appears once', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->method('findByGroupId')->willReturn([
        factoryMeeting([factoryContact('Alex', 'alex@example.test', '0117')]),
        // Same person, differently cased and padded — still the same key.
        factoryMeeting([factoryContact('  ALEX ', 'Alex@Example.test', '0117')]),
    ]);

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
    expect($names)->toHaveCount(1, 'Matching contacts collapse to one entry.');
});

test('an entirely empty meeting contact is skipped', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->method('findByGroupId')->willReturn([
        factoryMeeting([
            factoryContact('', '', ''),
            factoryContact('Sam', 'sam@example.test'),
        ]),
    ]);

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
    expect($names)->toBe(['Sam'], 'A blank contact row is not a contact.');
});

test('distinct meeting contacts are all kept', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->method('findByGroupId')->willReturn([
        factoryMeeting([
            factoryContact('Alex', 'alex@example.test'),
            factoryContact('Sam', 'sam@example.test'),
        ]),
    ]);

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    expect($group->getContacts())->toHaveCount(2);
});

test('a group is still built without a meeting repository', function () {
    // No repository at all: the group has no meetings, but must exist.
    $group = (new TsmlGroupFactory())->createFromSource(GROUP_ID);

    expect($group)->not->toBeNull()
        ->and($group->getMeetings())->toBe([]);
});

test('a failing meeting lookup leaves the group without meetings', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->method('findByGroupId')->willThrowException(new Exception('repository down'));

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    expect($group)->not->toBeNull('One bad lookup must not take the group with it.')
        ->and($group->getMeetings())->toBe([]);
});

test('meetings returned by the repository are attached to the group', function () {
    $repository = $this->createMock(MeetingRepository::class);
    $repository->expects($this->once())
        ->method('findByGroupId')
        ->with(GROUP_ID)
        ->willReturn([factoryMeeting([])]);

    $group = groupFactoryWith($repository)->createFromSource(GROUP_ID);

    expect($group->getMeetings())->toHaveCount(1);
});
