<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver;
use TsmlForUnity\Positions\TsmlPositionView;
use Unity\Positions\Interfaces\Position;
use WP_Mock;

/**
 * Tests for resolving ACF field names to their generated keys.
 *
 * ACF addresses fields by an opaque generated key rather than by name, so
 * the plugin resolves name → key once at activation and caches the result
 * in an option. resolve() therefore has to be safe to call when ACF is not
 * loaded, and must not overwrite a good cached mapping with an empty one —
 * doing so would leave every downstream lookup falling back to hardcoded
 * constants.
 *
 * @covers \TsmlForUnity\IntergroupMeetings\AcfFieldKeyResolver
 * @covers \TsmlForUnity\Positions\TsmlPositionView
 */
class AcfFieldKeyResolverResolveTest extends TestCase
{
    /** Options written by the stubbed update_option(). */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->written = [];
        WP_Mock::userFunction('update_option')
            ->andReturnUsing(function (string $name, $value, $autoload = null): bool {
                $this->written[$name] = $value;

                return true;
            });
        WP_Mock::userFunction('get_option')->andReturnUsing(
            fn (string $name, $default = false) => $this->written[$name] ?? $default
        );
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Each ACF test runs in its own process. WP_Mock defines
     * acf_get_field() for the lifetime of the process, and
     * AcfFieldKeyResolverTest asserts that it is *absent* to cover the
     * ACF-unavailable branch — so defining it here would break that test.
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function resolving_caches_a_key_for_every_field_acf_knows(): void
    {
        // ACF answers with a field array carrying the generated key.
        WP_Mock::userFunction('acf_get_field')
            ->andReturnUsing(static fn (string $name): array => ['key' => 'field_' . md5($name), 'name' => $name]);

        $mapping = AcfFieldKeyResolver::resolve();

        $this->assertNotEmpty($mapping);
        foreach ($mapping as $name => $key) {
            $this->assertStringStartsWith('field_', $key, $name . ' should resolve to an ACF key');
        }
        // The mapping is cached so later lookups avoid touching ACF.
        $this->assertNotEmpty($this->written, 'a resolved mapping should be stored');
    }

    /**
     * Each ACF test runs in its own process. WP_Mock defines
     * acf_get_field() for the lifetime of the process, and
     * AcfFieldKeyResolverTest asserts that it is *absent* to cover the
     * ACF-unavailable branch — so defining it here would break that test.
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function a_resolved_key_is_returned_by_get_key(): void
    {
        WP_Mock::userFunction('acf_get_field')
            ->andReturnUsing(static fn (string $name): array => ['key' => 'field_resolved', 'name' => $name]);

        $mapping = AcfFieldKeyResolver::resolve();
        $fieldName = array_key_first($mapping);

        $this->assertSame('field_resolved', AcfFieldKeyResolver::getKey($fieldName));
    }

    /**
     * Each ACF test runs in its own process. WP_Mock defines
     * acf_get_field() for the lifetime of the process, and
     * AcfFieldKeyResolverTest asserts that it is *absent* to cover the
     * ACF-unavailable branch — so defining it here would break that test.
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function fields_acf_cannot_resolve_are_left_out_of_the_mapping(): void
    {
        // A field ACF does not know returns null; one with no key is useless.
        WP_Mock::userFunction('acf_get_field')->andReturn(null);

        $mapping = AcfFieldKeyResolver::resolve();

        $this->assertSame([], $mapping);
    }

    /**
     * Each ACF test runs in its own process. WP_Mock defines
     * acf_get_field() for the lifetime of the process, and
     * AcfFieldKeyResolverTest asserts that it is *absent* to cover the
     * ACF-unavailable branch — so defining it here would break that test.
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function nothing_is_cached_when_no_field_resolves(): void
    {
        WP_Mock::userFunction('acf_get_field')->andReturn(['name' => 'x']); // no 'key'

        AcfFieldKeyResolver::resolve();

        $this->assertSame(
            [],
            $this->written,
            'An empty mapping must not overwrite a previously good cache.'
        );
    }

    /** @test */
    public function a_position_view_exposes_the_position_it_wraps(): void
    {
        $position = $this->createMock(Position::class);
        $position->method('getShortDescription')->willReturn('Treasurer');

        $view = new TsmlPositionView($position);

        $this->assertSame($position, $view->getPosition());
    }
}
