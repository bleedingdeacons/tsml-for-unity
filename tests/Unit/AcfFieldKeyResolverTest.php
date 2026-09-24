<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Brain\Monkey\Functions;
use TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;

/*
 * Tests for AcfFieldKeyResolver
 */

covers(\TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::class);

test('resolve is a no op when acf is unavailable', function () {
    // acf_get_field() is not defined in the test runtime, so resolve()
    // must bail out and return an empty mapping without writing options.
    expect(function_exists('acf_get_field'))->toBeFalse();

    expect(AcfFieldKeyResolver::resolve())->toBe([]);
});

test('get key returns the cached key when present', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])
        ->andReturn([
            TsmlIntergroupMeetingFields::FIELD_ATTENDEES => 'field_cached123',
        ]);

    expect(AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_ATTENDEES))->toBe('field_cached123');
});

test('get key falls back to the hardcoded constant when uncached', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])
        ->andReturn([]);

    expect(AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_DATE))->toBe(TsmlIntergroupMeetingFields::FIELD_KEY_DATE);
});

test('get key returns null for an unknown uncached field', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])
        ->andReturn([]);

    expect(AcfFieldKeyResolver::getKey('a_field_nobody_configured'))->toBeNull();
});

test('is cached reflects whether the option is populated', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])
        ->andReturn(['x' => 'field_1']);

    expect(AcfFieldKeyResolver::isCached())->toBeTrue();
});

test('is cached is false for an empty mapping', function () {
    Functions\expect('get_option')
        ->with('tsml_unity_acf_field_keys', [])
        ->andReturn([]);

    expect(AcfFieldKeyResolver::isCached())->toBeFalse();
});

test('clear deletes the cached option', function () {
    $deleted = null;
    Functions\expect('delete_option')
        ->once()
        ->andReturnUsing(function ($option) use (&$deleted) {
            $deleted = $option;
            return true;
        });

    AcfFieldKeyResolver::clear();

    expect($deleted)->toBe('tsml_unity_acf_field_keys');
});
