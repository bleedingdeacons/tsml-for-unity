<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use ReflectionClass;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Meetings\TsmlMeetingFields;

/*
 * The Meeting config Unity publishes is literally this class's constants --
 * Plugin::registerServices() hands setConfig() a ReflectionClass::getConstants()
 * of it. Consumers therefore depend on the constant *names*, and a missing one
 * is not a fatal: it is an undefined array key that lands in SQL as ''.
 *
 * That is what happened to GROUP_POST_TYPE. Amber's meeting-list search joins
 * the group post to search its title and reads that key; without it the join
 * ran as `group_post.post_type = ''` and could never match. It went unnoticed
 * because meetings are titled after their groups, so the meeting's own title
 * matched first and the results looked right.
 */

covers(TsmlMeetingFields::class);

it('publishes the group post type amber joins on', function () {
    expect(TsmlMeetingFields::GROUP_POST_TYPE)->toBe(TsmlGroupFields::POST_TYPE, 'GROUP_POST_TYPE must name the post type GROUP_META_KEY points at.');
});

test('the published config carries every key consumers read', function () {
    $published = (new ReflectionClass(TsmlMeetingFields::class))->getConstants();

    foreach (['POST_TYPE', 'GROUP_META_KEY', 'GROUP_POST_TYPE'] as $key) {
        expect($published)->toHaveKey($key, message: $key . ' is read out of the Meeting config, so it has to be a constant here.')
            ->and($published[$key])->not->toBe('', $key . ' must not publish an empty value.');
    }
});
