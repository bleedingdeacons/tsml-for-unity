<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Contacts\TsmlContactFactory;
use Unity\Contacts\Interfaces\Contact;

/**
 * Tests for TsmlContact and TsmlContactFactory
 *
 * @covers \TsmlForUnity\Contacts\TsmlContact
 * @covers \TsmlForUnity\Contacts\TsmlContactFactory
 */
class TsmlContactTest extends TestCase
{
    /**
     * @test
     */
    public function contact_implements_the_interface(): void
    {
        $this->assertInstanceOf(Contact::class, new TsmlContact());
    }

    /**
     * @test
     */
    public function contact_defaults_to_empty_strings(): void
    {
        $contact = new TsmlContact();

        $this->assertSame('', $contact->getName());
        $this->assertSame('', $contact->getEmail());
        $this->assertSame('', $contact->getPhone());
        $this->assertSame('', $contact->getUpdated());
    }

    /**
     * @test
     */
    public function contact_exposes_constructor_values(): void
    {
        $contact = new TsmlContact('Jane Doe', 'jane@example.com', '0700 123456', '2026-06-01');

        $this->assertSame('Jane Doe', $contact->getName());
        $this->assertSame('jane@example.com', $contact->getEmail());
        $this->assertSame('0700 123456', $contact->getPhone());
        $this->assertSame('2026-06-01', $contact->getUpdated());
    }

    /**
     * @test
     */
    public function factory_creates_from_a_source_array(): void
    {
        $contact = (new TsmlContactFactory())->createFromSource([
            'name'  => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '0800 999',
        ]);

        $this->assertInstanceOf(TsmlContact::class, $contact);
        $this->assertSame('John Smith', $contact->getName());
        $this->assertSame('john@example.com', $contact->getEmail());
        $this->assertSame('0800 999', $contact->getPhone());
    }

    /**
     * @test
     */
    public function factory_tolerates_missing_source_keys(): void
    {
        $contact = (new TsmlContactFactory())->createFromSource([]);

        $this->assertSame('', $contact->getName());
        $this->assertSame('', $contact->getEmail());
        $this->assertSame('', $contact->getPhone());
    }

    /**
     * @test
     */
    public function factory_create_builds_from_explicit_arguments(): void
    {
        $contact = (new TsmlContactFactory())->create('Al', 'al@example.com', '111');

        $this->assertSame('Al', $contact->getName());
        $this->assertSame('al@example.com', $contact->getEmail());
        $this->assertSame('111', $contact->getPhone());
    }

    /**
     * @test
     */
    public function factory_create_defaults_to_an_empty_contact(): void
    {
        $contact = (new TsmlContactFactory())->create();

        $this->assertSame('', $contact->getName());
        $this->assertSame('', $contact->getEmail());
        $this->assertSame('', $contact->getPhone());
    }
}
