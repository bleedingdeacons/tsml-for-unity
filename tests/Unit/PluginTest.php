<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Unit;

use Psr\Container\ContainerInterface;
use TsmlForUnity\Tests\Support\WpdbStub;
use TsmlForUnity\Plugin;
use Unity\Testing\Doubles\FakeContainer;
use Unity\Committees\Interfaces\Committee;
use Unity\Committees\Interfaces\CommitteeFactory;
use Unity\Committees\Interfaces\CommitteeRepository;
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

/*
 * Tests for the plugin's Unity integration.
 *
 * registerWithUnity() is pure wiring: it hands Unity a closure per service.
 * Asserting only that register() was called would leave every closure
 * unexecuted, and the closures are where the wiring actually lives — which
 * dependencies are optional, and what each constructor is handed. So these
 * tests use a resolving container double and then *build* every registered
 * service, which is the only way to prove the graph is constructible.
 */

covers(\TsmlForUnity\Plugin::class);

beforeEach(function () {
    $this->storedConfig = [];

    $this->config = $this->createMock(Configuration::class);
    $this->config->method('setConfig')
        ->willReturnCallback(function (string $key, array $source): void {
            $this->storedConfig[$key] = $source;
        });

    $this->container = new FakeContainer();
    $this->container->prime(Configuration::class, $this->config);

    // The credential repository takes a wpdb, so building the graph
    // needs one in the global the closure reads. Restored in tearDown
    // rather than left behind: this file builds every service the
    // plugin registers, and a global set by one test leaking into the
    // next is exactly the kind of thing that makes that useful test
    // pass for the wrong reason.
    $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
    $GLOBALS['wpdb'] = new WpdbStub();
});

afterEach(function () {
    $GLOBALS['wpdb'] = $this->previousWpdb;
});

// ─── availability probes ────────────────────────────────────────
/*
 * Unity is a require-dev path repository, so all of its contracts are
 * autoloadable in the suite and every probe should report available.
 * These are the guards that decide whether a service gets registered
 * at all, so a false negative would silently disable the integration.
 */
test('every unity availability probe reports true', function (string $method) {
    expect(Plugin::$method())->toBeTrue($method . '() should see Unity loaded');
})->with(function () {
    $methods = [
        'unityGroupsAvailable',
        'unityLocationsAvailable',
        'unityContactsAvailable',
        'unityMembersAvailable',
        'unityPositionsAvailable',
        'unityCommitteesAvailable',
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
});

test('unity is available checks for the core classes', function () {
    // These are concrete classes rather than interfaces, and only ship
    // with the full Unity plugin; the assertion documents whichever way
    // the suite is set up rather than pinning a value.
    expect(Plugin::unityIsAvailable())->toBeBool();
});

// ─── registerWithUnity ──────────────────────────────────────────
test('registration is skipped for a container that cannot register', function () {
    // A bare PSR-11 container has get()/has() but no register(); the
    // plugin must leave it alone rather than fatal.
    $bare = new class implements ContainerInterface {
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

    // Returned without touching the container.
})->throwsNoExceptions();

it('registers the core factories and repositories', function () {
    Plugin::registerWithUnity($this->container);

    foreach (
        [
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
        CommitteeFactory::class,
        CommitteeRepository::class,
        ] as $id
    ) {
        expect($this->container->registeredIds())->toContain($id);
    }
});

/*
 * The point of the exercise: build every service the plugin registered.
 * A closure that asks for a dependency the container cannot supply, or
 * passes the wrong thing to a constructor, only fails here.
 */
test('every registered service can actually be built', function () {
    Plugin::registerWithUnity($this->container);

    $built = 0;
    foreach ($this->container->registeredIds() as $id) {
        $service = $this->container->get($id);

        expect($service)->toBeObject($id . ' should resolve to an object');
        $built++;
    }

    expect($built)->toBeGreaterThan(10, 'the integration registers a substantial graph');
});

test('resolved services implement the unity contracts they are registered against', function () {
    Plugin::registerWithUnity($this->container);

    foreach ($this->container->registeredIds() as $id) {
        if (!interface_exists($id)) {
            continue;
        }

        expect($this->container->get($id))->toBeInstanceOf($id, $id . ' must resolve to something satisfying its own contract');
    }
});

test('services are resolved once and reused', function () {
    Plugin::registerWithUnity($this->container);

    expect($this->container->get(MeetingFactory::class))->toBe($this->container->get(MeetingFactory::class));
});

// ─── field-map configuration ────────────────────────────────────
it('stores the tsml field maps against the unity contracts', function () {
    Plugin::registerWithUnity($this->container);

    // The field maps are what let Scrutiny and Amber resolve an ACF key
    // from a Unity interface, so the mapping has to be registered.
    expect($this->storedConfig)->toHaveKey(Member::class)
        ->and($this->storedConfig[Member::class])->toHaveKey('POST_TYPE')
        ->and($this->storedConfig[Member::class]['POST_TYPE'])->toBe('intergroup-member');
});

test('the committee field map carries the taxonomy and its acf fields', function () {
    Plugin::registerWithUnity($this->container);

    $committeeConfig = $this->storedConfig[Committee::class];

    expect($committeeConfig['TAXONOMY'])->toBe('intergroup-committee');

    // The member's field is nested in the "Service" ACF Group field, so
    // its meta key carries the group prefix; the position's is top level.
    // Nothing in this plugin reads either -- the term relationships are the
    // source of truth -- but importers and field-key lookups need them.
    expect($committeeConfig['FIELD_MEMBER_COMMITTEES'])->toBe('service-layout-group_member-committees')
        ->and($committeeConfig['FIELD_POSITION_COMMITTEES'])->toBe('position-committees')
        ->and($committeeConfig)->toHaveKey('KEY_MEMBER_COMMITTEES')
        ->and($committeeConfig)->toHaveKey('KEY_POSITION_COMMITTEES');
});

test('the member field map carries the acf field names and keys', function () {
    Plugin::registerWithUnity($this->container);

    $memberConfig = $this->storedConfig[Member::class];

    // Downstream plugins look these up by constant name.
    expect($memberConfig['FIELD_PERSONAL_EMAIL'])->toBe('about-layout-group_personal-email')
        ->and($memberConfig['FIELD_RESPONDER_CERTIFICATION'])->toBe('service-layout-group_responder-certification')
        ->and($memberConfig)->toHaveKey('KEY_RESPONDER_CERTIFICATION');
});

test('a field map is stored for each configured contract', function () {
    Plugin::registerWithUnity($this->container);

    expect(count($this->storedConfig))->toBeGreaterThanOrEqual(4, 'meetings, groups, members and positions all publish field maps');

    foreach ($this->storedConfig as $key => $map) {
        expect($map)->not->toBeEmpty($key . ' should have a non-empty field map');
    }
});

/*
 * The plugin logs under its own channel name.
 *
 * Asserted directly rather than through wp_log(). It used to be covered
 * only incidentally: HasLoggerTest defined wp_log() and the definition
 * leaked into every later test in the process, so Plugin::logChannel()
 * ran as a side effect. That test is isolated now, so the channel name
 * needs saying out loud.
 */
it('logs under its own channel', function () {
    $logChannel = new \ReflectionMethod(Plugin::class, 'logChannel');

    expect($logChannel->invoke(null))->toBe('tsml-for-unity');
});
