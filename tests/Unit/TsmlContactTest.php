<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\Contacts\TsmlContact;
use TsmlForUnity\Contacts\TsmlContactFactory;
use Unity\Contacts\Interfaces\Contact;

/*
 * Tests for TsmlContact and TsmlContactFactory
 */

covers(\TsmlForUnity\Contacts\TsmlContact::class, \TsmlForUnity\Contacts\TsmlContactFactory::class);

test('contact implements the interface', function () {
    expect(new TsmlContact())->toBeInstanceOf(Contact::class);
});

test('contact defaults to empty strings', function () {
    $contact = new TsmlContact();

    expect($contact->getName())->toBe('')
        ->and($contact->getEmail())->toBe('')
        ->and($contact->getPhone())->toBe('')
        ->and($contact->getUpdated())->toBe('');
});

test('contact exposes constructor values', function () {
    $contact = new TsmlContact('Jane Doe', 'jane@example.com', '0700 123456', '2026-06-01');

    expect($contact->getName())->toBe('Jane Doe')
        ->and($contact->getEmail())->toBe('jane@example.com')
        ->and($contact->getPhone())->toBe('0700 123456')
        ->and($contact->getUpdated())->toBe('2026-06-01');
});

test('factory creates from a source array', function () {
    $contact = (new TsmlContactFactory())->createFromSource([
        'name'  => 'John Smith',
        'email' => 'john@example.com',
        'phone' => '0800 999',
    ]);

    expect($contact)->toBeInstanceOf(TsmlContact::class)
        ->and($contact->getName())->toBe('John Smith')
        ->and($contact->getEmail())->toBe('john@example.com')
        ->and($contact->getPhone())->toBe('0800 999');
});

test('factory tolerates missing source keys', function () {
    $contact = (new TsmlContactFactory())->createFromSource([]);

    expect($contact->getName())->toBe('')
        ->and($contact->getEmail())->toBe('')
        ->and($contact->getPhone())->toBe('');
});

test('factory create builds from explicit arguments', function () {
    $contact = (new TsmlContactFactory())->create('Al', 'al@example.com', '111');

    expect($contact->getName())->toBe('Al')
        ->and($contact->getEmail())->toBe('al@example.com')
        ->and($contact->getPhone())->toBe('111');
});

test('factory create defaults to an empty contact', function () {
    $contact = (new TsmlContactFactory())->create();

    expect($contact->getName())->toBe('')
        ->and($contact->getEmail())->toBe('')
        ->and($contact->getPhone())->toBe('');
});
