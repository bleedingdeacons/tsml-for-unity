<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFields;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;
use WP_Mock;

/**
 * Tests for TsmlPrivacyPolicyFactory
 *
 * @covers \TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory
 */
class TsmlPrivacyPolicyFactoryTest extends TestCase
{
    private TsmlPrivacyPolicyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
        $this->factory = new TsmlPrivacyPolicyFactory();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(PrivacyPolicyFactory::class, $this->factory);
    }

    /**
     * @test
     */
    public function create_from_source_reads_the_title_from_the_post_and_fields_from_acf(): void
    {
        WP_Mock::userFunction('get_post')->with(5)->andReturn((object) [
            'post_title'        => 'Privacy &amp; Cookies',
            'post_modified_gmt' => '2026-06-01 10:00:00',
        ]);

        WP_Mock::userFunction('get_field')
            ->with(TsmlPrivacyPolicyFields::FIELD_POLICY, 5)->andReturn('The policy text');
        WP_Mock::userFunction('get_field')
            ->with(TsmlPrivacyPolicyFields::FIELD_VERSION, 5)->andReturn('2.1');
        WP_Mock::userFunction('get_field')
            ->with(TsmlPrivacyPolicyFields::FIELD_ACTIVE, 5)->andReturn(true);

        $policy = $this->factory->createFromSource(5);

        $this->assertSame(5, $policy->getId());
        // Title comes from post_title with entities decoded.
        $this->assertSame('Privacy & Cookies', $policy->getTitle());
        $this->assertSame('The policy text', $policy->getPolicy());
        $this->assertSame('2.1', $policy->getVersion());
        $this->assertTrue($policy->isActive());
        $this->assertSame('2026-06-01 10:00:00', $policy->getUpdated());
    }

    /**
     * @test
     */
    public function create_from_source_defaults_gracefully_when_the_post_is_missing(): void
    {
        WP_Mock::userFunction('get_post')->with(9)->andReturn(null);
        WP_Mock::userFunction('get_field')->andReturn(null);

        $policy = $this->factory->createFromSource(9);

        $this->assertSame(9, $policy->getId());
        $this->assertSame('', $policy->getTitle());
        $this->assertSame('', $policy->getPolicy());
        $this->assertSame('', $policy->getVersion());
        $this->assertFalse($policy->isActive());
        $this->assertSame('', $policy->getUpdated());
    }

    /**
     * @test
     */
    public function create_new_builds_a_policy_from_explicit_values(): void
    {
        $policy = $this->factory->createNew(
            3,
            'Data Policy',
            'Body',
            '1.0',
            true,
            '2026-01-01 00:00:00'
        );

        $this->assertSame(3, $policy->getId());
        $this->assertSame('Data Policy', $policy->getTitle());
        $this->assertSame('Body', $policy->getPolicy());
        $this->assertSame('1.0', $policy->getVersion());
        $this->assertTrue($policy->isActive());
        $this->assertSame('2026-01-01 00:00:00', $policy->getUpdated());
    }

    /**
     * @test
     */
    public function create_new_applies_empty_defaults(): void
    {
        $policy = $this->factory->createNew(4);

        $this->assertSame(4, $policy->getId());
        $this->assertSame('', $policy->getTitle());
        $this->assertFalse($policy->isActive());
    }
}
