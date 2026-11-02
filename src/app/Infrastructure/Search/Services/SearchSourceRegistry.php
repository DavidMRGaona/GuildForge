<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\Contracts\SearchSourceInterface;
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
     * @return list<SearchSourceInterface>
     */
    public function sources(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof SearchSourceInterface) {
                continue;
            }

            if ($module !== null) {
                Log::warning('[SearchSourceRegistry] Modules can only offer indexed search sources, live source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                ]);

                continue;
            }

            $key = $this->read(static fn (): string => $source->key(), $source, $module);

            if ($key === null) {
                continue;
            }

            if (isset($resolved[$key])) {
                $this->warnTaken($sourceClass, $module, $key, $resolved[$key]::class);

                continue;
            }

            $resolved[$key] = $source;
        }

        return array_values($resolved);
    }

    /**
     * @return list<IndexedSearchSourceInterface>
     */
    public function indexedSources(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof IndexedSearchSourceInterface) {
                continue;
            }

            $type = $this->read(static fn (): string => $source->type(), $source, $module);

            if ($type === null) {
                continue;
            }

            if (isset($resolved[$type])) {
                $this->warnTaken($sourceClass, $module, $type, $resolved[$type]::class);

                continue;
            }

            $resolved[$type] = $source;
        }

        return array_values($resolved);
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

    private function resolve(string $sourceClass, ?string $module): SearchSourceInterface|IndexedSearchSourceInterface|null
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

        if (! $source instanceof SearchSourceInterface && ! $source instanceof IndexedSearchSourceInterface) {
            Log::warning('[SearchSourceRegistry] Registered class is not a search source', [
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
            Log::warning('[SearchSourceRegistry] Failed to read search source key', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function warnTaken(string $sourceClass, ?string $module, string $key, string $takenBy): void
    {
        Log::warning('[SearchSourceRegistry] Search source key already taken, source skipped', [
            'source' => $sourceClass,
            'module' => $module,
            'key' => $key,
            'takenBy' => $takenBy,
        ]);
    }
}
