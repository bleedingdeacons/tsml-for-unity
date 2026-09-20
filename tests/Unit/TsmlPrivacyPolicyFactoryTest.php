<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use function Brain\Monkey\Functions\expect;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory;
use TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFields;
use TsmlForUnity\Tests\TestCase;
use Unity\PrivacyPolicies\Interfaces\PrivacyPolicyFactory;

/**
 * Tests for TsmlPrivacyPolicyFactory
 */
#[CoversClass(\TsmlForUnity\PrivacyPolicies\TsmlPrivacyPolicyFactory::class)]
class TsmlPrivacyPolicyFactoryTest extends TestCase
{
    private TsmlPrivacyPolicyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TsmlPrivacyPolicyFactory();
    }

    #[Test]
    public function it_implements_the_factory_interface(): void
    {
        $this->assertInstanceOf(PrivacyPolicyFactory::class, $this->factory);
    }

    #[Test]
    public function create_from_source_reads_the_title_from_the_post_and_fields_from_acf(): void
    {
        expect('get_post')->with(5)->andReturn((object) [
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
        expect('get_field')->andReturnUsing(
            static fn (string $field, int $postId): mixed => match ($field) {
                TsmlPrivacyPolicyFields::FIELD_POLICY  => 'The policy text',
                TsmlPrivacyPolicyFields::FIELD_VERSION => '2.1',
                TsmlPrivacyPolicyFields::FIELD_ACTIVE  => true,
                default                                => null,
            }
        );

        $policy = $this->factory->createFromSource(5);

        $this->assertSame(5, $policy->getId());
        // Title comes from post_title with entities decoded.
        $this->assertSame('Privacy & Cookies', $policy->getTitle());
        $this->assertSame('The policy text', $policy->getPolicy());
        $this->assertSame('2.1', $policy->getVersion());
        $this->assertTrue($policy->isActive());
        $this->assertSame('2026-06-01 10:00:00', $policy->getUpdated());
    }

    #[Test]
    public function create_from_source_defaults_gracefully_when_the_post_is_missing(): void
    {
        expect('get_post')->with(9)->andReturn(null);
        expect('get_field')->andReturn(null);

        $policy = $this->factory->createFromSource(9);

        $this->assertSame(9, $policy->getId());
        $this->assertSame('', $policy->getTitle());
        $this->assertSame('', $policy->getPolicy());
        $this->assertSame('', $policy->getVersion());
        $this->assertFalse($policy->isActive());
        $this->assertSame('', $policy->getUpdated());
    }

    #[Test]
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

    #[Test]
    public function create_new_applies_empty_defaults(): void
    {
        $policy = $this->factory->createNew(4);

        $this->assertSame(4, $policy->getId());
        $this->assertSame('', $policy->getTitle());
        $this->assertFalse($policy->isActive());
    }
}
