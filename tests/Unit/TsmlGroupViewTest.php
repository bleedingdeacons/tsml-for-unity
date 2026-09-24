<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroupView;
use TsmlForUnity\Members\TsmlMember;
use Unity\Groups\Interfaces\GroupView;

/*
 * Tests for TsmlGroupView
 */

covers(\TsmlForUnity\Groups\TsmlGroupView::class);

it('implements group view interface', function () {
    expect(new TsmlGroupView())->toBeInstanceOf(GroupView::class);
});

it('defaults to an empty view', function () {
    $view = new TsmlGroupView();

    expect($view->getId())->toBe(0)
        ->and($view->getTitle())->toBe('')
        ->and($view->getEmail())->toBe('')
        ->and($view->getMeetings())->toBe([])
        ->and($view->getLink())->toBe('')
        ->and($view->getContacts())->toBe([])
        ->and($view->getMembers())->toBe([]);
});

it('exposes every field passed to the constructor', function () {
    $contact = new TsmlContact('Jane', 'jane@example.com');
    $member = new TsmlMember(id: 1, anonymousName: 'John D.');

    $view = new TsmlGroupView(
        id: 10,
        title: 'Tuesday Group',
        email: 'group@example.com',
        meetings: ['m1'],
        link: 'https://example.com/group',
        contacts: [$contact],
        members: [$member]
    );

    expect($view->getId())->toBe(10)
        ->and($view->getTitle())->toBe('Tuesday Group')
        ->and($view->getEmail())->toBe('group@example.com')
        ->and($view->getMeetings())->toBe(['m1'])
        ->and($view->getLink())->toBe('https://example.com/group')
        ->and($view->getContacts())->toBe([$contact])
        ->and($view->getMembers())->toBe([$member]);
});
