<?php

declare(strict_types=1);

namespace App\Domain\Modules\Collections;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleName;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<string, Module>
 */
final class ModuleCollection implements IteratorAggregate, Countable
{
    /** @var array<string, Module> Keyed by module name */
    private array $modules = [];

    public function __construct(Module ...$modules)
    {
        foreach ($modules as $module) {
            $this->modules[$module->name()->value] = $module;
        }
    }

    public function add(Module $module): void
    {
        $this->modules[$module->name()->value] = $module;
    }

    public function get(ModuleName $name): ?Module
    {
        return $this->modules[$name->value] ?? null;
    }

    public function has(ModuleName $name): bool
    {
        return isset($this->modules[$name->value]);
    }

    public function findByName(ModuleName $name): ?Module
    {
        return $this->modules[$name->value] ?? null;
    }

    /**
     * Alias for all() for backward compatibility.
     *
     * @return array<Module>
     */
    public function items(): array
    {
        return $this->all();
    }

    public function remove(ModuleName $name): void
    {
        unset($this->modules[$name->value]);
    }

    public function enabled(): self
    {
        $enabled = array_filter(
            $this->modules,
            fn (Module $m): bool => $m->status() === ModuleStatus::Enabled
        );

        return new self(...array_values($enabled));
    }

    public function disabled(): self
    {
        $disabled = array_filter(
            $this->modules,
            fn (Module $m): bool => $m->status() === ModuleStatus::Disabled
        );

        return new self(...array_values($disabled));
    }

    /**
     * @return array<Module>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    /**
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->modules);
    }

    public function isEmpty(): bool
    {
        return empty($this->modules);
    }

    /**
     * @param callable(Module): void $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->modules as $module) {
            $callback($module);
        }
    }

    /**
     * Filter the collection using a callback.
     *
     * @param callable(Module): bool $callback
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->modules, $callback);

        return new self(...array_values($filtered));
    }

    /**
     * Map over the collection.
     *
     * @template T
     * @param callable(Module): T $callback
     * @return array<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->modules);
    }

    /**
     * Get the first module matching a condition, or the first module if no callback provided.
     *
     * @param (callable(Module): bool)|null $callback
     */
    public function first(?callable $callback = null): ?Module
    {
        if ($callback === null) {
            return empty($this->modules) ? null : reset($this->modules);
        }

        foreach ($this->modules as $module) {
            if ($callback($module)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Alias for enabled() for semantic clarity.
     */
    public function getEnabled(): self
    {
        return $this->enabled();
    }

    /**
     * Alias for disabled() for semantic clarity.
     */
    public function getDisabled(): self
    {
        return $this->disabled();
    }

    /**
     * Alias for get() using string name for convenience.
     */
    public function getByName(string $name): ?Module
    {
        return $this->modules[$name] ?? null;
    }

    public function count(): int
    {
        return count($this->modules);
    }

    /**
     * @return Traversable<string, Module>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->modules);
    }
}
