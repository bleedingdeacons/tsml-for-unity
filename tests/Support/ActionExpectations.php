<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Support;

use Mockery;
use WP_Mock;

/**
 * Negative expectations for WordPress actions.
 *
 * WP_Mock ships expectAction for "this fired" but has no counterpart for
 * "this must not fire", which several of the change/create branches turn on.
 *
 * Note that WP_Mock keys its argument matching on scalar values; every object
 * collapses to the same key. Scalar arguments are therefore matched exactly,
 * while object arguments only match on position, so pass the real values and
 * read these as assertions about the hook firing, not about its payload.
 */
trait ActionExpectations
{
    /**
     * Assert that an action never fires with the given argument shape.
     *
     * @param string $hook Action name.
     * @param mixed ...$args Arguments the action would carry if it fired.
     * @return void
     */
    protected function expectActionNotFired(string $hook, ...$args): void
    {
        $intercept = Mockery::mock('intercept');
        $intercept->shouldReceive('intercepted')->never();

        WP_Mock::onAction($hook)
            ->with(...$args)
            ->perform([$intercept, 'intercepted']);
    }
}
