<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use TsmlForUnity\Tests\TestCase;

/**
 * Tests for AcfFieldKeyResolver
 */
#[CoversClass(\TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver::class)]
class AcfFieldKeyResolverTest extends TestCase
{
    #[Test]
    public function resolve_is_a_no_op_when_acf_is_unavailable(): void
    {
        // acf_get_field() is not defined in the test runtime, so resolve()
        // must bail out and return an empty mapping without writing options.
        $this->assertFalse(function_exists('acf_get_field'));

        $this->assertSame([], AcfFieldKeyResolver::resolve());
    }

    #[Test]
    public function get_key_returns_the_cached_key_when_present(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([
                TsmlIntergroupMeetingFields::FIELD_ATTENDEES => 'field_cached123',
            ]);

        $this->assertSame(
            'field_cached123',
            AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_ATTENDEES)
        );
    }

    #[Test]
    public function get_key_falls_back_to_the_hardcoded_constant_when_uncached(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertSame(
            TsmlIntergroupMeetingFields::FIELD_KEY_DATE,
            AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_DATE)
        );
    }

    #[Test]
    public function get_key_returns_null_for_an_unknown_uncached_field(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertNull(AcfFieldKeyResolver::getKey('a_field_nobody_configured'));
    }

    #[Test]
    public function is_cached_reflects_whether_the_option_is_populated(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn(['x' => 'field_1']);

        $this->assertTrue(AcfFieldKeyResolver::isCached());
    }

    #[Test]
    public function is_cached_is_false_for_an_empty_mapping(): void
    {
        expect('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertFalse(AcfFieldKeyResolver::isCached());
    }

    #[Test]
    public function clear_deletes_the_cached_option(): void
    {
        $deleted = null;
        expect('delete_option')
            ->once()
            ->andReturnUsing(function ($option) use (&$deleted) {
                $deleted = $option;
                return true;
            });

        AcfFieldKeyResolver::clear();

        $this->assertSame('tsml_unity_acf_field_keys', $deleted);
    }
}
