<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroup;
use Unity\Groups\Interfaces\Group;

/*
 * Tests for TsmlGroup entity
 */

covers(\TsmlForUnity\Groups\TsmlGroup::class);

it('implements group interface', function () {
    expect(new TsmlGroup(id: 1))->toBeInstanceOf(Group::class);
});

it('defaults every optional field', function () {
    $group = new TsmlGroup(id: 7);

    expect($group->getId())->toBe(7)
        ->and($group->getTitle())->toBe('')
        ->and($group->getEmail())->toBe('')
        ->and($group->getMeetings())->toBe([])
        ->and($group->getLink())->toBe('')
        ->and($group->getGroupNotes())->toBe('')
        ->and($group->getWebsite())->toBe('')
        ->and($group->getPhone())->toBe('')
        ->and($group->getVenmo())->toBe('')
        ->and($group->getPaypal())->toBe('')
        ->and($group->getSquare())->toBe('')
        ->and($group->getDistrictId())->toBeNull()
        ->and($group->getLastContact())->toBeNull()
        ->and($group->getContacts())->toBe([])
        ->and($group->getUpdated())->toBe('');
});

it('exposes every field passed to the constructor', function () {
    $contact = new TsmlContact('Jane', 'jane@example.com', '0700', '2026-01-01');

    $group = new TsmlGroup(
        id: 42,
        title: 'Tuesday Group',
        email: 'group@example.com',
        meetings: ['m1', 'm2'],
        link: 'https://example.com/group',
        groupNotes: 'Meets weekly',
        website: 'https://group.example.com',
        phone: '01234 567890',
        venmo: '@group',
        paypal: 'grouppaypal',
        square: '$group',
        districtId: 5,
        lastContact: '2026-05-01',
        contacts: [$contact],
        updated: '2026-06-01 10:00:00'
    );

    expect($group->getId())->toBe(42)
        ->and($group->getTitle())->toBe('Tuesday Group')
        ->and($group->getEmail())->toBe('group@example.com')
        ->and($group->getMeetings())->toBe(['m1', 'm2'])
        ->and($group->getLink())->toBe('https://example.com/group')
        ->and($group->getGroupNotes())->toBe('Meets weekly')
        ->and($group->getWebsite())->toBe('https://group.example.com')
        ->and($group->getPhone())->toBe('01234 567890')
        ->and($group->getVenmo())->toBe('@group')
        ->and($group->getPaypal())->toBe('grouppaypal')
        ->and($group->getSquare())->toBe('$group')
        ->and($group->getDistrictId())->toBe(5)
        ->and($group->getLastContact())->toBe('2026-05-01')
        ->and($group->getContacts())->toBe([$contact])
        ->and($group->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('is valid requires a title', function () {
    expect((new TsmlGroup(id: 0))->isValid())->toBeFalse()
        ->and((new TsmlGroup(id: 99, title: ''))->isValid())->toBeFalse();
    // Validity covers the data, not persistence: an unsaved group (id 0)
    // with a title is still valid.
    expect((new TsmlGroup(id: 0, title: 'Named'))->isValid())->toBeTrue()
        ->and((new TsmlGroup(id: 3, title: 'Named'))->isValid())->toBeTrue();
});

test('has contribution options is true when any handle is set', function () {
    expect((new TsmlGroup(id: 1))->hasContributionOptions())->toBeFalse()
        ->and((new TsmlGroup(id: 1, venmo: '@g'))->hasContributionOptions())->toBeTrue()
        ->and((new TsmlGroup(id: 1, paypal: 'g'))->hasContributionOptions())->toBeTrue()
        ->and((new TsmlGroup(id: 1, square: '$g'))->hasContributionOptions())->toBeTrue();
});
