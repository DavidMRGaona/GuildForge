<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

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

            $key = $this->key($source, $module);

            if ($key === null) {
                continue;
            }

            if (isset($resolved[$key])) {
                Log::warning('[SearchSourceRegistry] Search source key already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'key' => $key,
                    'takenBy' => $resolved[$key]::class,
                ]);

                continue;
            }

            $resolved[$key] = $source;
        }

        return array_values($resolved);
    }

    private function resolve(string $sourceClass, ?string $module): ?SearchSourceInterface
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

        if (! $source instanceof SearchSourceInterface) {
            Log::warning('[SearchSourceRegistry] Registered class is not a search source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    private function key(SearchSourceInterface $source, ?string $module): ?string
    {
        try {
            return $source->key();
        } catch (Throwable $exception) {
            Log::warning('[SearchSourceRegistry] Failed to read search source key', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
