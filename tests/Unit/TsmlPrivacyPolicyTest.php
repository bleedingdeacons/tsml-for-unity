<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicy;

/**
 * Tests for TsmlPrivacyPolicy entity
 *
 * @covers \TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicy
 */
class TsmlPrivacyPolicyTest extends TestCase
{
    /**
     * @test
     */
    public function it_implements_privacy_policy_interface(): void
    {
        $policy = new TsmlPrivacyPolicy(id: 1);

        $this->assertInstanceOf(PrivacyPolicy::class, $policy);
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_minimal_values(): void
    {
        $policy = new TsmlPrivacyPolicy(id: 1);

        $this->assertEquals(1, $policy->getId());
        $this->assertEquals('', $policy->getTitle());
        $this->assertEquals('', $policy->getPolicy());
        $this->assertEquals('', $policy->getVersion());
        $this->assertFalse($policy->isActive());
        $this->assertEquals('', $policy->getUpdated());
    }

    /**
     * @test
     */
    public function it_can_be_instantiated_with_all_values(): void
    {
        $policy = new TsmlPrivacyPolicy(
            id: 42,
            title: 'GDPR Privacy Policy',
            policy: '<p>We respect your privacy.</p>',
            version: '2.1',
            active: true,
            updated: '2026-05-06 12:34:56'
        );

        $this->assertEquals(42, $policy->getId());
        $this->assertEquals('GDPR Privacy Policy', $policy->getTitle());
        $this->assertEquals('<p>We respect your privacy.</p>', $policy->getPolicy());
        $this->assertEquals('2.1', $policy->getVersion());
        $this->assertTrue($policy->isActive());
        $this->assertEquals('2026-05-06 12:34:56', $policy->getUpdated());
    }

    /**
     * @test
     */
    public function active_flag_can_be_toggled(): void
    {
        $activePolicy = new TsmlPrivacyPolicy(id: 1, active: true);
        $inactivePolicy = new TsmlPrivacyPolicy(id: 2, active: false);

        $this->assertTrue($activePolicy->isActive());
        $this->assertFalse($inactivePolicy->isActive());
    }

    /**
     * @test
     */
    public function it_handles_empty_strings_for_optional_fields(): void
    {
        $policy = new TsmlPrivacyPolicy(
            id: 1,
            title: '',
            policy: '',
            version: ''
        );

        $this->assertEmpty($policy->getTitle());
        $this->assertEmpty($policy->getPolicy());
        $this->assertEmpty($policy->getVersion());
    }

    /**
     * @test
     */
    public function it_preserves_html_content_in_policy_body(): void
    {
        $body = '<h1>Policy</h1><p>Lorem <strong>ipsum</strong>.</p>';
        $policy = new TsmlPrivacyPolicy(id: 1, policy: $body);

        $this->assertEquals($body, $policy->getPolicy());
    }

    /**
     * @test
     */
    public function unsaved_policy_has_zero_id(): void
    {
        $policy = new TsmlPrivacyPolicy(id: 0, title: 'Draft');

        $this->assertEquals(0, $policy->getId());
        $this->assertEquals('Draft', $policy->getTitle());
    }

    /**
     * @test
     */
    public function version_is_stored_as_string(): void
    {
        $policy = new TsmlPrivacyPolicy(id: 1, version: '2026-05');

        $this->assertIsString($policy->getVersion());
        $this->assertEquals('2026-05', $policy->getVersion());
    }
}
