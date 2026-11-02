<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SearchSourceRegistry implements SearchSourceRegistryInterface
{
    /** @var array<class-string, ?string> Map of source class to owning module (null for core). */
    private array $sources = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $sourceClass, ?string $module = null): void
    {
        $this->sources[$sourceClass] = $module;
    }

    /**
     * @param  array<class-string>  $sourceClasses
     */
    public function registerMany(array $sourceClasses, ?string $module = null): void
    {
        foreach ($sourceClasses as $sourceClass) {
            $this->register($sourceClass, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->sources = array_filter(
            $this->sources,
            static fn (?string $sourceModule): bool => $sourceModule !== $module
        );
    }

    public function clear(): void
    {
        $this->sources = [];
    }

    /**
     * @return list<IndexedSearchSourceInterface>
     */
    public function indexedSources(): array
    {
        return $this->resolveIndexed()[0];
    }

    /**
     * @return list<class-string>
     */
    public function unresolved(): array
    {
        return $this->resolveIndexed()[1];
    }

    /**
     * The indexed sources and, from the same pass, the classes that could not be loaded.
     *
     * @return array{list<IndexedSearchSourceInterface>, list<class-string>}
     */
    private function resolveIndexed(): array
    {
        $resolved = [];
        $unresolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof IndexedSearchSourceInterface) {
                $unresolved[] = $sourceClass;

                continue;
            }

            $type = $this->read(static fn (): string => $source->type(), $source, $module);

            if ($type === null) {
                $unresolved[] = $sourceClass;

                continue;
            }

            if (isset($resolved[$type])) {
                $this->warnTaken($sourceClass, $module, $type, $resolved[$type]::class);

                continue;
            }

            $resolved[$type] = $source;
        }

        return [array_values($resolved), $unresolved];
    }

    public function moduleOf(string $type): ?string
    {
        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof IndexedSearchSourceInterface) {
                continue;
            }

            if ($this->read(static fn (): string => $source->type(), $source, $module) === $type) {
                return $module;
            }
        }

        return null;
    }

    private function resolve(string $sourceClass, ?string $module): ?IndexedSearchSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[SearchSourceRegistry] Failed to resolve search source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof IndexedSearchSourceInterface) {
            Log::warning('[SearchSourceRegistry] Registered class is not an indexed search source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    /**
     * @param  callable(): string  $read
     */
    private function read(callable $read, object $source, ?string $module): ?string
    {
        try {
            return $read();
        } catch (Throwable $exception) {
            Log::warning('[SearchSourceRegistry] Failed to read search source type', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function warnTaken(string $sourceClass, ?string $module, string $type, string $takenBy): void
    {
        Log::warning('[SearchSourceRegistry] Search source type already taken, source skipped', [
            'source' => $sourceClass,
            'module' => $module,
            'type' => $type,
            'takenBy' => $takenBy,
        ]);
    }
}
