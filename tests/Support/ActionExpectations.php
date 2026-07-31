<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Support;

use Brain\Monkey\Actions;

/**
 * Negative expectations for WordPress actions.
 *
 * Brain Monkey says "this must not fire" directly, so this is now a thin
 * wrapper — kept because the name reads better at the call sites than
 * Actions\expectDone(...)->never() does, and because there are a lot of them.
 *
 * Unlike the WP_Mock version this replaces, arguments are matched for real.
 * WP_Mock keyed its matching on scalar values and collapsed every object to
 * the same key, so an object argument only ever matched on position; Mockery
 * compares them, falling back to == for objects that are not the same
 * instance.
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
        $expectation = Actions\expectDone($hook)->never();

        if ($args !== []) {
            $expectation->with(...$args);
        }
    }
}
