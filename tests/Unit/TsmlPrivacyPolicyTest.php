<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicy;

/*
 * Tests for TsmlPrivacyPolicy entity
 */

covers(\TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy::class);

it('implements privacy policy interface', function () {
    $policy = new TsmlPrivacyPolicy(id: 1);

    expect($policy)->toBeInstanceOf(PrivacyPolicy::class);
});

it('can be instantiated with minimal values', function () {
    $policy = new TsmlPrivacyPolicy(id: 1);

    expect($policy->getId())->toEqual(1)
        ->and($policy->getTitle())->toEqual('')
        ->and($policy->getPolicy())->toEqual('')
        ->and($policy->getVersion())->toEqual('')
        ->and($policy->isActive())->toBeFalse()
        ->and($policy->getUpdated())->toEqual('');
});

it('can be instantiated with all values', function () {
    $policy = new TsmlPrivacyPolicy(
        id: 42,
        title: 'GDPR Privacy Policy',
        policy: '<p>We respect your privacy.</p>',
        version: '2.1',
        active: true,
        updated: '2026-05-06 12:34:56'
    );

    expect($policy->getId())->toEqual(42)
        ->and($policy->getTitle())->toEqual('GDPR Privacy Policy')
        ->and($policy->getPolicy())->toEqual('<p>We respect your privacy.</p>')
        ->and($policy->getVersion())->toEqual('2.1')
        ->and($policy->isActive())->toBeTrue()
        ->and($policy->getUpdated())->toEqual('2026-05-06 12:34:56');
});

test('active flag can be toggled', function () {
    $activePolicy = new TsmlPrivacyPolicy(id: 1, active: true);
    $inactivePolicy = new TsmlPrivacyPolicy(id: 2, active: false);

    expect($activePolicy->isActive())->toBeTrue()
        ->and($inactivePolicy->isActive())->toBeFalse();
});

it('handles empty strings for optional fields', function () {
    $policy = new TsmlPrivacyPolicy(
        id: 1,
        title: '',
        policy: '',
        version: ''
    );

    expect($policy->getTitle())->toBeEmpty()
        ->and($policy->getPolicy())->toBeEmpty()
        ->and($policy->getVersion())->toBeEmpty();
});

it('preserves html content in policy body', function () {
    $body = '<h1>Policy</h1><p>Lorem <strong>ipsum</strong>.</p>';
    $policy = new TsmlPrivacyPolicy(id: 1, policy: $body);

    expect($policy->getPolicy())->toEqual($body);
});

test('unsaved policy has zero id', function () {
    $policy = new TsmlPrivacyPolicy(id: 0, title: 'Draft');

    expect($policy->getId())->toEqual(0)
        ->and($policy->getTitle())->toEqual('Draft');
});

test('version is stored as string', function () {
    $policy = new TsmlPrivacyPolicy(id: 1, version: '2026-05');

    expect($policy->getVersion())->toBeString()
        ->and($policy->getVersion())->toEqual('2026-05');
});
