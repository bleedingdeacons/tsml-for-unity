<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests;

use BleedingDeacons\WpMocks\TestCase as WpMocksTestCase;

/**
 * Base TestCase for the tsml-for-unity suite.
 *
 * Brain Monkey's lifecycle, Mockery integration, the WordPress stubs and the
 * hook assertions all come from bleedingdeacons/wp-mocks, shared across the
 * plugin suite. All 59 test files used to open and close WP_Mock by hand in
 * their own setUp()/tearDown(); that now happens in one place.
 *
 * Brain Monkey owns add_action(), add_filter(), do_action() and
 * apply_filters(), and defines them inside its setUp(), so every test that
 * reaches WordPress-registering code has to come through here.
 */
abstract class TestCase extends WpMocksTestCase
{
}
