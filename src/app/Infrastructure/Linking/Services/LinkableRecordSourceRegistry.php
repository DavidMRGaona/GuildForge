<?php

declare(strict_types=1);

namespace App\Infrastructure\Linking\Services;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LinkableRecordSourceRegistry implements LinkableRecordSourceRegistryInterface
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
     * @return array<string, LinkableRecordSourceInterface>
     */
    public function sources(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof LinkableRecordSourceInterface) {
                continue;
            }

            $type = $this->readType($source, $module);

            if ($type === null) {
                continue;
            }

            if (isset($resolved[$type])) {
                Log::warning('[LinkableRecordSourceRegistry] Linkable record type already taken, source skipped', [
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

    public function source(string $type): ?LinkableRecordSourceInterface
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
    private function resolve(string $sourceClass, ?string $module): ?LinkableRecordSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[LinkableRecordSourceRegistry] Failed to resolve linkable record source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof LinkableRecordSourceInterface) {
            Log::warning('[LinkableRecordSourceRegistry] Registered class is not a linkable record source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    private function readType(LinkableRecordSourceInterface $source, ?string $module): ?string
    {
        try {
            $type = $source->type();
        } catch (Throwable $exception) {
            Log::warning('[LinkableRecordSourceRegistry] Failed to read linkable record type', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (preg_match(LinkableRecordDTO::TYPE_PATTERN, $type) !== 1) {
            Log::warning('[LinkableRecordSourceRegistry] Linkable record type is not kebab-case, source skipped', [
                'source' => $source::class,
                'module' => $module,
                'type' => $type,
            ]);

            return null;
        }

        return $type;
    }
}
