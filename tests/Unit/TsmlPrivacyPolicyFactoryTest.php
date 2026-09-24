<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFields;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;

/*
 * Tests for TsmlPrivacyPolicyFactory
 */

covers(\TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory::class);

beforeEach(function () {
    $this->factory = new TsmlPrivacyPolicyFactory();
});

it('implements the factory interface', function () {
    expect($this->factory)->toBeInstanceOf(PrivacyPolicyFactory::class);
});

test('create from source reads the title from the post and fields from acf', function () {
    Functions\expect('get_post')->with(5)->andReturn((object) [
        'post_title'        => 'Privacy &amp; Cookies',
        'post_modified_gmt' => '2026-06-01 10:00:00',
    ]);

    // One expectation, dispatching on the field name.
    //
    // Stacking three Functions\expect('get_field')->with(...) calls does
    // not work the way the WP_Mock equivalent did: Brain Monkey keeps one
    // stub per function per test, and the first one registered answers
    // every call whatever its ->with() says. The result is silent — every
    // field comes back as the policy text — so the mapping is explicit.
    Functions\expect('get_field')->andReturnUsing(
        static fn (string $field, int $postId): mixed => match ($field) {
            TsmlPrivacyPolicyFields::FIELD_POLICY  => 'The policy text',
            TsmlPrivacyPolicyFields::FIELD_VERSION => '2.1',
            TsmlPrivacyPolicyFields::FIELD_ACTIVE  => true,
            default                                => null,
        }
    );

    $policy = $this->factory->createFromSource(5);

    expect($policy->getId())->toBe(5);
    // Title comes from post_title with entities decoded.
    expect($policy->getTitle())->toBe('Privacy & Cookies')
        ->and($policy->getPolicy())->toBe('The policy text')
        ->and($policy->getVersion())->toBe('2.1')
        ->and($policy->isActive())->toBeTrue()
        ->and($policy->getUpdated())->toBe('2026-06-01 10:00:00');
});

test('create from source defaults gracefully when the post is missing', function () {
    Functions\expect('get_post')->with(9)->andReturn(null);
    Functions\expect('get_field')->andReturn(null);

    $policy = $this->factory->createFromSource(9);

    expect($policy->getId())->toBe(9)
        ->and($policy->getTitle())->toBe('')
        ->and($policy->getPolicy())->toBe('')
        ->and($policy->getVersion())->toBe('')
        ->and($policy->isActive())->toBeFalse()
        ->and($policy->getUpdated())->toBe('');
});

test('create new builds a policy from explicit values', function () {
    $policy = $this->factory->createNew(
        3,
        'Data Policy',
        'Body',
        '1.0',
        true,
        '2026-01-01 00:00:00'
    );

    expect($policy->getId())->toBe(3)
        ->and($policy->getTitle())->toBe('Data Policy')
        ->and($policy->getPolicy())->toBe('Body')
        ->and($policy->getVersion())->toBe('1.0')
        ->and($policy->isActive())->toBeTrue()
        ->and($policy->getUpdated())->toBe('2026-01-01 00:00:00');
});

test('create new applies empty defaults', function () {
    $policy = $this->factory->createNew(4);

    expect($policy->getId())->toBe(4)
        ->and($policy->getTitle())->toBe('')
        ->and($policy->isActive())->toBeFalse();
});
