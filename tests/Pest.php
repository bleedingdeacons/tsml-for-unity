<?php

declare(strict_types=1);

// Pest configuration.
//
// Every spec in this suite runs on TsmlForUnity\Tests\TestCase, which wraps
// wp-mocks': Brain Monkey's lifecycle, Mockery integration and the hook
// assertions all come from there. Unlike Scrutiny and Trusted there is no
// pure-PHP half to split off — every file used to extend that TestCase, and
// Brain Monkey owns add_action(), add_filter(), do_action() and
// apply_filters(), defining them only inside its setUp(). A spec that
// reached WordPress-registering code without it would find none of them
// defined, so the whole directory is bound here.
//
// Two files stay PHPUnit classes rather than Pest closures, because they need
// #[RunInSeparateProcess] and Pest refuses process isolation outright:
//
//   - AcfFieldKeyResolverResolveTest: setting a Brain Monkey expectation on
//     acf_get_field() defines the function, and a defined function stays
//     defined for the life of the process — which would break
//     AcfFieldKeyResolverTest, which covers the ACF-unavailable branch by
//     asserting the function is absent.
//   - HasLoggerTest: the same, for wp_log(), whose absence the rest of the
//     suite relies on.
//
// Each also holds a test or two that need no isolation; they stay with the
// file rather than splitting it. Pest runs both classes as they are. See
// tests/bootstrap.php for the one thing that takes: PHPUNIT_COMPOSER_INSTALL.
//
// All the specs share one namespace, TsmlForUnity\Tests\Unit, so their
// file-level helper functions and constants share it too. A name that recurs
// across files has been given a file-specific one (stubGroupPostTypeGuard,
// stubMemberPostTypeGuard, ...); a new helper needs a name no other spec uses.

use TsmlForUnity\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');
