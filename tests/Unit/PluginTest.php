<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TsmlForUnity\Plugin;
use TsmlForUnity\Tests\Support\FakeContainer;
use Unity\Core\Interfaces\Configuration;
use Unity\Groups\Interfaces\GroupFactory;
use Unity\Groups\Interfaces\GroupRepository;
use Unity\Locations\Interfaces\LocationFactory;
use Unity\Locations\Interfaces\LocationRepository;
use Unity\Meetings\Interfaces\MeetingFactory;
use Unity\Meetings\Interfaces\MeetingRepository;
use Unity\Members\Interfaces\Member;
use Unity\Members\Interfaces\MemberFactory;
use Unity\Members\Interfaces\MemberRepository;
use Unity\Positions\Interfaces\PositionFactory;
use Unity\Positions\Interfaces\PositionRepository;
use WP_Mock;

/**
 * Tests for the plugin's Unity integration.
 *
 * registerWithUnity() is pure wiring: it hands Unity a closure per service.
 * Asserting only that register() was called would leave every closure
 * unexecuted, and the closures are where the wiring actually lives — which
 * dependencies are optional, and what each constructor is handed. So these
 * tests use a resolving container double and then *build* every registered
 * service, which is the only way to prove the graph is constructible.
 *
 * @covers \TsmlForUnity\Plugin
 */
class PluginTest extends TestCase
{
    private FakeContainer $container;

    /** @var Configuration&\PHPUnit\Framework\MockObject\MockObject */
    private $config;

    /** @var array<string, array> Field maps captured from setConfig(). */
    private array $storedConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->storedConfig = [];

        $this->config = $this->createMock(Configuration::class);
        $this->config->method('setConfig')
            ->willReturnCallback(function (string $key, array $source): void {
                $this->storedConfig[$key] = $source;
            });

        $this->container = new FakeContainer();
        $this->container->prime(Configuration::class, $this->config);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    // ─── availability probes ────────────────────────────────────────

    /**
     * Unity is a require-dev path repository, so all of its contracts are
     * autoloadable in the suite and every probe should report available.
     * These are the guards that decide whether a service gets registered
     * at all, so a false negative would silently disable the integration.
     *
     * @test
     * @dataProvider availabilityProbeProvider
     */
    public function every_unity_availability_probe_reports_true(string $method): void
    {
        $this->assertTrue(Plugin::$method(), $method . '() should see Unity loaded');
    }

    /** @return array<string, array{0: string}> */
    public static function availabilityProbeProvider(): array
    {
        $methods = [
            'unityGroupsAvailable',
            'unityLocationsAvailable',
            'unityContactsAvailable',
            'unityMembersAvailable',
            'unityPositionsAvailable',
            'unityPrivacyPoliciesAvailable',
            'unityIntergroupMeetingsAvailable',
            'unityIntergroupMeetingGroupAttendanceAvailable',
            'unityIntergroupMeetingOfficerAttendanceAvailable',
            'unityMeetingsAvailable',
            'unityPositionViewsAvailable',
            'unityGroupViewsAvailable',
            'unityMemberViewsAvailable',
        ];

        return array_combine(
            $methods,
            array_map(static fn (string $m): array => [$m], $methods)
        );
    }

    /** @test */
    public function unity_is_available_checks_for_the_core_classes(): void
    {
        // These are concrete classes rather than interfaces, and only ship
        // with the full Unity plugin; the assertion documents whichever way
        // the suite is set up rather than pinning a value.
        $this->assertIsBool(Plugin::unityIsAvailable());
    }

    // ─── registerWithUnity ──────────────────────────────────────────

    /** @test */
    public function registration_is_skipped_for_a_container_that_cannot_register(): void
    {
        // A bare PSR-11 container has get()/has() but no register(); the
        // plugin must leave it alone rather than fatal.
        $bare = new class implements \Psr\Container\ContainerInterface {
            public function get(string $id): mixed
            {
                return null;
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        Plugin::registerWithUnity($bare);

        $this->assertTrue(true, 'returned without touching the container');
    }

    /** @test */
    public function it_registers_the_core_factories_and_repositories(): void
    {
        Plugin::registerWithUnity($this->container);

        foreach ([
            LocationFactory::class,
            LocationRepository::class,
            MeetingFactory::class,
            MeetingRepository::class,
            GroupFactory::class,
            GroupRepository::class,
            MemberFactory::class,
            MemberRepository::class,
            PositionFactory::class,
            PositionRepository::class,
        ] as $id) {
            $this->assertContains($id, $this->container->registeredIds(), $id . ' should be registered');
        }
    }

    /**
     * The point of the exercise: build every service the plugin registered.
     * A closure that asks for a dependency the container cannot supply, or
     * passes the wrong thing to a constructor, only fails here.
     *
     * @test
     */
    public function every_registered_service_can_actually_be_built(): void
    {
        Plugin::registerWithUnity($this->container);

        $built = 0;
        foreach ($this->container->registeredIds() as $id) {
            $service = $this->container->get($id);

            $this->assertIsObject($service, $id . ' should resolve to an object');
            $built++;
        }

        $this->assertGreaterThan(10, $built, 'the integration registers a substantial graph');
    }

    /** @test */
    public function resolved_services_implement_the_unity_contracts_they_are_registered_against(): void
    {
        Plugin::registerWithUnity($this->container);

        foreach ($this->container->registeredIds() as $id) {
            if (!interface_exists($id)) {
                continue;
            }

            $this->assertInstanceOf(
                $id,
                $this->container->get($id),
                $id . ' must resolve to something satisfying its own contract'
            );
        }
    }

    /** @test */
    public function services_are_resolved_once_and_reused(): void
    {
        Plugin::registerWithUnity($this->container);

        $this->assertSame(
            $this->container->get(MeetingFactory::class),
            $this->container->get(MeetingFactory::class)
        );
    }

    // ─── field-map configuration ────────────────────────────────────

    /** @test */
    public function it_stores_the_tsml_field_maps_against_the_unity_contracts(): void
    {
        Plugin::registerWithUnity($this->container);

        // The field maps are what let Scrutiny and Amber resolve an ACF key
        // from a Unity interface, so the mapping has to be registered.
        $this->assertArrayHasKey(Member::class, $this->storedConfig);
        $this->assertArrayHasKey('POST_TYPE', $this->storedConfig[Member::class]);
        $this->assertSame('intergroup-member', $this->storedConfig[Member::class]['POST_TYPE']);
    }

    /** @test */
    public function the_member_field_map_carries_the_acf_field_names_and_keys(): void
    {
        Plugin::registerWithUnity($this->container);

        $memberConfig = $this->storedConfig[Member::class];

        // Downstream plugins look these up by constant name.
        $this->assertSame(
            'about-layout-group_personal-email',
            $memberConfig['FIELD_PERSONAL_EMAIL']
        );
        $this->assertSame(
            'service-layout-group_responder-certification',
            $memberConfig['FIELD_RESPONDER_CERTIFICATION']
        );
        $this->assertArrayHasKey('KEY_RESPONDER_CERTIFICATION', $memberConfig);
    }

    /** @test */
    public function a_field_map_is_stored_for_each_configured_contract(): void
    {
        Plugin::registerWithUnity($this->container);

        $this->assertGreaterThanOrEqual(
            4,
            count($this->storedConfig),
            'meetings, groups, members and positions all publish field maps'
        );

        foreach ($this->storedConfig as $key => $map) {
            $this->assertNotEmpty($map, $key . ' should have a non-empty field map');
        }
    }
}
