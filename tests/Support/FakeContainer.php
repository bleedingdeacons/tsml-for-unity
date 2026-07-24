<?php

declare(strict_types=1);

namespace TsmlForUnity\Tests\Support;

use Unity\Core\Interfaces\Container;

/**
 * A resolving stand-in for Unity's dependency container.
 *
 * Plugin::registerWithUnity() does not build anything itself — it hands the
 * container a closure per service. Asserting only that register() was called
 * would leave every one of those closures unexecuted, and they are where the
 * wiring actually lives (which dependencies are optional, what gets passed to
 * each constructor).
 *
 * So this double stores the factories and resolves them on demand, exactly as
 * the real container does, including caching the built instance. A test can
 * then ask for each registered id and get the object the plugin would really
 * have produced.
 *
 * Pre-seeded entries (see prime()) let a test supply a dependency the plugin
 * expects Unity itself to provide, such as Configuration.
 */
final class FakeContainer implements Container
{
    /** @var array<string, callable> Registered factories, by id. */
    private array $factories = [];

    /** @var array<string, mixed> Resolved instances and pre-seeded values. */
    private array $instances = [];

    /** Ids registered, in registration order. */
    public array $registered = [];

    /** Seed a ready-made instance, as Unity would for its own services. */
    public function prime(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function register(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->registered[] = $id;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new class ("Service not registered: {$id}") extends \RuntimeException implements
                \Psr\Container\NotFoundExceptionInterface {
            };
        }

        // Resolve once and remember, matching the real container's behaviour.
        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    /** Ids that have a registered factory. */
    public function registeredIds(): array
    {
        return array_keys($this->factories);
    }
}
