<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Meetings\TsmlMeetingViewFactory;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Meetings\Interfaces\MeetingViewFactory;
use Unity\Members\Interfaces\MemberRepository;

/*
 * Tests for TsmlMeetingViewFactory
 */

covers(\TsmlForUnity\Meetings\TsmlMeetingViewFactory::class);

function factory(): TsmlMeetingViewFactory
{
    return new TsmlMeetingViewFactory(
        test()->createMock(MeetingRepository::class),
        test()->createMock(MemberRepository::class),
        test()->createMock(GroupRepository::class)
    );
}

it('implements the factory interface', function () {
    expect(factory())->toBeInstanceOf(MeetingViewFactory::class);
});

/*
 * The factory was never finished: createFrom() deliberately throws rather
 * than silently returning nothing from a non-nullable-in-practice method.
 */
test('create from throws because it is not implemented', function () {
    factory()->createFrom(1);
})->throws(\LogicException::class, 'is not implemented');
