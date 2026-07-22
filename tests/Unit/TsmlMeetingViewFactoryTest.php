<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Meetings\TsmlMeetingViewFactory;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Meetings\Interfaces\MeetingViewFactory;
use Unity\Members\Interfaces\MemberRepository;

/**
 * Tests for TsmlMeetingViewFactory
 *
 * @covers \TsmlForUnity\Meetings\TsmlMeetingViewFactory
 */
class TsmlMeetingViewFactoryTest extends TestCase
{
    private function factory(): TsmlMeetingViewFactory
    {
        return new TsmlMeetingViewFactory(
            $this->createMock(MeetingRepository::class),
            $this->createMock(MemberRepository::class),
            $this->createMock(GroupRepository::class)
        );
    }

    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(MeetingViewFactory::class, $this->factory());
    }

    /**
     * The factory was never finished: createFrom() deliberately throws rather
     * than silently returning nothing from a non-nullable-in-practice method.
     *
     * @test
     */
    public function create_from_throws_because_it_is_not_implemented(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not implemented');

        $this->factory()->createFrom(1);
    }
}
