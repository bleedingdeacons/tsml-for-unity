<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Groups\TsmlGroup;
use Unity\Groups\Interfaces\Group;

/**
 * Tests for TsmlGroup entity
 *
 * @covers \TsmlForUnity\Groups\TsmlGroup
 */
class TsmlGroupTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_group_interface(): void
    {
        $this->assertInstanceOf(Group::class, new TsmlGroup(id: 1));
    }

    /**
     * @test
     */
    public function it_defaults_every_optional_field(): void
    {
        $group = new TsmlGroup(id: 7);

        $this->assertSame(7, $group->getId());
        $this->assertSame('', $group->getTitle());
        $this->assertSame('', $group->getEmail());
        $this->assertSame([], $group->getMeetings());
        $this->assertSame('', $group->getLink());
        $this->assertSame('', $group->getGroupNotes());
        $this->assertSame('', $group->getWebsite());
        $this->assertSame('', $group->getPhone());
        $this->assertSame('', $group->getVenmo());
        $this->assertSame('', $group->getPaypal());
        $this->assertSame('', $group->getSquare());
        $this->assertNull($group->getDistrictId());
        $this->assertNull($group->getLastContact());
        $this->assertSame([], $group->getContacts());
        $this->assertSame('', $group->getUpdated());
    }

    /**
     * @test
     */
    public function it_exposes_every_field_passed_to_the_constructor(): void
    {
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

        $this->assertSame(42, $group->getId());
        $this->assertSame('Tuesday Group', $group->getTitle());
        $this->assertSame('group@example.com', $group->getEmail());
        $this->assertSame(['m1', 'm2'], $group->getMeetings());
        $this->assertSame('https://example.com/group', $group->getLink());
        $this->assertSame('Meets weekly', $group->getGroupNotes());
        $this->assertSame('https://group.example.com', $group->getWebsite());
        $this->assertSame('01234 567890', $group->getPhone());
        $this->assertSame('@group', $group->getVenmo());
        $this->assertSame('grouppaypal', $group->getPaypal());
        $this->assertSame('$group', $group->getSquare());
        $this->assertSame(5, $group->getDistrictId());
        $this->assertSame('2026-05-01', $group->getLastContact());
        $this->assertSame([$contact], $group->getContacts());
        $this->assertSame('2026-06-01 10:00:00', $group->getUpdated());
    }

    /**
     * @test
     */
    public function is_valid_requires_a_title(): void
    {
        $this->assertFalse((new TsmlGroup(id: 0))->isValid());
        $this->assertFalse((new TsmlGroup(id: 99, title: ''))->isValid());
        // Validity covers the data, not persistence: an unsaved group (id 0)
        // with a title is still valid.
        $this->assertTrue((new TsmlGroup(id: 0, title: 'Named'))->isValid());
        $this->assertTrue((new TsmlGroup(id: 3, title: 'Named'))->isValid());
    }

    /**
     * @test
     */
    public function has_contribution_options_is_true_when_any_handle_is_set(): void
    {
        $this->assertFalse((new TsmlGroup(id: 1))->hasContributionOptions());
        $this->assertTrue((new TsmlGroup(id: 1, venmo: '@g'))->hasContributionOptions());
        $this->assertTrue((new TsmlGroup(id: 1, paypal: 'g'))->hasContributionOptions());
        $this->assertTrue((new TsmlGroup(id: 1, square: '$g'))->hasContributionOptions());
    }
}
