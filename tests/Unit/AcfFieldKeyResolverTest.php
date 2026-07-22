<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver;
use TsmlForUnity\IntergroupMeetings\TsmlIntergroupMeetingFields;
use WP_Mock;

/**
 * Tests for AcfFieldKeyResolver
 *
 * @covers \TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver
 */
class AcfFieldKeyResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function resolve_is_a_no_op_when_acf_is_unavailable(): void
    {
        // acf_get_field() is not defined in the test runtime, so resolve()
        // must bail out and return an empty mapping without writing options.
        $this->assertFalse(function_exists('acf_get_field'));

        $this->assertSame([], AcfFieldKeyResolver::resolve());
    }

    /**
     * @test
     */
    public function get_key_returns_the_cached_key_when_present(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([
                TsmlIntergroupMeetingFields::FIELD_ATTENDEES => 'field_cached123',
            ]);

        $this->assertSame(
            'field_cached123',
            AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_ATTENDEES)
        );
    }

    /**
     * @test
     */
    public function get_key_falls_back_to_the_hardcoded_constant_when_uncached(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertSame(
            TsmlIntergroupMeetingFields::FIELD_KEY_DATE,
            AcfFieldKeyResolver::getKey(TsmlIntergroupMeetingFields::FIELD_DATE)
        );
    }

    /**
     * @test
     */
    public function get_key_returns_null_for_an_unknown_uncached_field(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertNull(AcfFieldKeyResolver::getKey('a_field_nobody_configured'));
    }

    /**
     * @test
     */
    public function is_cached_reflects_whether_the_option_is_populated(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn(['x' => 'field_1']);

        $this->assertTrue(AcfFieldKeyResolver::isCached());
    }

    /**
     * @test
     */
    public function is_cached_is_false_for_an_empty_mapping(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('tsml_unity_acf_field_keys', [])
            ->andReturn([]);

        $this->assertFalse(AcfFieldKeyResolver::isCached());
    }

    /**
     * @test
     */
    public function clear_deletes_the_cached_option(): void
    {
        $deleted = null;
        WP_Mock::userFunction('delete_option')
            ->once()
            ->andReturnUsing(function ($option) use (&$deleted) {
                $deleted = $option;
                return true;
            });

        AcfFieldKeyResolver::clear();

        $this->assertSame('tsml_unity_acf_field_keys', $deleted);
    }
}
