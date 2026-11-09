<?php

declare(strict_types=1);

namespace App\Infrastructure\Publications\Services;

use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PublicationSourceRegistry implements PublicationSourceRegistryInterface
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

    /**
     * @return array<class-string>
     */
    public function all(): array
    {
        return array_keys($this->sources);
    }

    /**
     * @return array<string, PublicationSourceInterface>
     */
    public function sources(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof PublicationSourceInterface) {
                continue;
            }

            $type = $this->readType($source, $module);

            if ($type === null) {
                continue;
            }

            if (isset($resolved[$type])) {
                Log::warning('[PublicationSourceRegistry] Publication type already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'type' => $type,
                    'takenBy' => $resolved[$type]::class,
                ]);

                continue;
            }

            $resolved[$type] = $source;
        }

        return $resolved;
    }

    public function source(string $type): ?PublicationSourceInterface
    {
        return $this->sources()[$type] ?? null;
    }

    public function unregisterModule(string $module): void
    {
        $this->sources = array_filter(
            $this->sources,
            fn (?string $sourceModule): bool => $sourceModule !== $module
        );
    }

    public function clear(): void
    {
        $this->sources = [];
    }

    /**
     * @param  class-string  $sourceClass
     */
    private function resolve(string $sourceClass, ?string $module): ?PublicationSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[PublicationSourceRegistry] Failed to resolve publication source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof PublicationSourceInterface) {
            Log::warning('[PublicationSourceRegistry] Registered class is not a publication source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    private function readType(PublicationSourceInterface $source, ?string $module): ?string
    {
        try {
            $type = $source->type();
        } catch (Throwable $exception) {
            Log::warning('[PublicationSourceRegistry] Failed to read publication type', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (preg_match(PublicationDTO::TYPE_PATTERN, $type) !== 1) {
            Log::warning('[PublicationSourceRegistry] Publication type is not kebab-case, source skipped', [
                'source' => $source::class,
                'module' => $module,
                'type' => $type,
            ]);

            return null;
        }

        return $type;
    }
}
