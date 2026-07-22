<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroupView;
use TsmlForUnity\Members\TsmlMember;
use Unity\Groups\Interfaces\GroupView;

/**
 * Tests for TsmlGroupView
 *
 * @covers \TsmlForUnity\Groups\TsmlGroupView
 */
class TsmlGroupViewTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_group_view_interface(): void
    {
        $this->assertInstanceOf(GroupView::class, new TsmlGroupView());
    }

    /**
     * @test
     */
    public function it_defaults_to_an_empty_view(): void
    {
        $view = new TsmlGroupView();

        $this->assertSame(0, $view->getId());
        $this->assertSame('', $view->getTitle());
        $this->assertSame('', $view->getEmail());
        $this->assertSame([], $view->getMeetings());
        $this->assertSame('', $view->getLink());
        $this->assertSame([], $view->getContacts());
        $this->assertSame([], $view->getMembers());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_passed_to_the_constructor(): void
    {
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

        $this->assertSame(10, $view->getId());
        $this->assertSame('Tuesday Group', $view->getTitle());
        $this->assertSame('group@example.com', $view->getEmail());
        $this->assertSame(['m1'], $view->getMeetings());
        $this->assertSame('https://example.com/group', $view->getLink());
        $this->assertSame([$contact], $view->getContacts());
        $this->assertSame([$member], $view->getMembers());
    }
}
