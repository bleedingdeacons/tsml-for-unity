<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use TsmlForUnity\Groups\TsmlGroupFactory;
use TsmlForUnity\Groups\TsmlGroupFields;
use Unity\Contacts\Interfaces\Contact;
use Unity\Meetings\Interfaces\Meeting;
use Unity\Meetings\Interfaces\MeetingRepository;
use WP_Mock;

/**
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
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupFactory
 */
class TsmlGroupFactoryMeetingsTest extends TestCase
{
    private const GROUP_ID = 42;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        WP_Mock::userFunction('get_post')->andReturn((object) [
            'ID'          => self::GROUP_ID,
            'post_title'  => 'Tuesday Group',
            'post_type'   => TsmlGroupFields::POST_TYPE,
            'post_status' => 'publish',
            'post_content' => '',
        ]);
        WP_Mock::userFunction('get_post_custom')->andReturn([]);
        WP_Mock::userFunction('get_permalink')->andReturn('https://example.test/group/42');
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    private function contact(string $name, string $email = '', string $phone = ''): Contact
    {
        $contact = $this->createMock(Contact::class);
        $contact->method('getName')->willReturn($name);
        $contact->method('getEmail')->willReturn($email);
        $contact->method('getPhone')->willReturn($phone);

        return $contact;
    }

    /** @param Contact[] $contacts */
    private function meeting(array $contacts): Meeting
    {
        $meeting = $this->createMock(Meeting::class);
        $meeting->method('getId')->willReturn(7);
        $meeting->method('getContacts')->willReturn($contacts);

        return $meeting;
    }

    private function factoryWith(MeetingRepository $repository): TsmlGroupFactory
    {
        return new TsmlGroupFactory(null, $repository);
    }

    /** @test */
    public function contacts_from_meetings_are_added_to_the_group(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->method('findByGroupId')->willReturn([
            $this->meeting([$this->contact('Alex', 'alex@example.test', '0117')]),
        ]);

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $this->assertNotNull($group);
        $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
        $this->assertContains('Alex', $names);
    }

    /** @test */
    public function the_same_contact_on_two_meetings_appears_once(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->method('findByGroupId')->willReturn([
            $this->meeting([$this->contact('Alex', 'alex@example.test', '0117')]),
            // Same person, differently cased and padded — still the same key.
            $this->meeting([$this->contact('  ALEX ', 'Alex@Example.test', '0117')]),
        ]);

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
        $this->assertCount(1, $names, 'Matching contacts collapse to one entry.');
    }

    /** @test */
    public function an_entirely_empty_meeting_contact_is_skipped(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->method('findByGroupId')->willReturn([
            $this->meeting([
                $this->contact('', '', ''),
                $this->contact('Sam', 'sam@example.test'),
            ]),
        ]);

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $names = array_map(static fn (Contact $c): string => $c->getName(), $group->getContacts());
        $this->assertSame(['Sam'], $names, 'A blank contact row is not a contact.');
    }

    /** @test */
    public function distinct_meeting_contacts_are_all_kept(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->method('findByGroupId')->willReturn([
            $this->meeting([
                $this->contact('Alex', 'alex@example.test'),
                $this->contact('Sam', 'sam@example.test'),
            ]),
        ]);

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $this->assertCount(2, $group->getContacts());
    }

    /** @test */
    public function a_group_is_still_built_without_a_meeting_repository(): void
    {
        // No repository at all: the group has no meetings, but must exist.
        $group = (new TsmlGroupFactory())->createFromSource(self::GROUP_ID);

        $this->assertNotNull($group);
        $this->assertSame([], $group->getMeetings());
    }

    /** @test */
    public function a_failing_meeting_lookup_leaves_the_group_without_meetings(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->method('findByGroupId')->willThrowException(new Exception('repository down'));

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $this->assertNotNull($group, 'One bad lookup must not take the group with it.');
        $this->assertSame([], $group->getMeetings());
    }

    /** @test */
    public function meetings_returned_by_the_repository_are_attached_to_the_group(): void
    {
        $repository = $this->createMock(MeetingRepository::class);
        $repository->expects($this->once())
            ->method('findByGroupId')
            ->with(self::GROUP_ID)
            ->willReturn([$this->meeting([])]);

        $group = $this->factoryWith($repository)->createFromSource(self::GROUP_ID);

        $this->assertCount(1, $group->getMeetings());
    }
}
