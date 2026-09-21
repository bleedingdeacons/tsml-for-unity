<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TsmlForUnity\Groups\TsmlGroupFields;
use TsmlForUnity\Meetings\TsmlMeetingFields;
use TsmlForUnity\Tests\TestCase;

/**
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
#[CoversClass(TsmlMeetingFields::class)]
class TsmlMeetingFieldsTest extends TestCase
{
    #[Test]
    public function it_publishes_the_group_post_type_amber_joins_on(): void
    {
        $this->assertSame(
            TsmlGroupFields::POST_TYPE,
            TsmlMeetingFields::GROUP_POST_TYPE,
            'GROUP_POST_TYPE must name the post type GROUP_META_KEY points at.'
        );
    }

    #[Test]
    public function the_published_config_carries_every_key_consumers_read(): void
    {
        $published = (new ReflectionClass(TsmlMeetingFields::class))->getConstants();

        foreach (['POST_TYPE', 'GROUP_META_KEY', 'GROUP_POST_TYPE'] as $key) {
            $this->assertArrayHasKey(
                $key,
                $published,
                $key . ' is read out of the Meeting config, so it has to be a constant here.'
            );
            $this->assertNotSame('', $published[$key], $key . ' must not publish an empty value.');
        }
    }
}
