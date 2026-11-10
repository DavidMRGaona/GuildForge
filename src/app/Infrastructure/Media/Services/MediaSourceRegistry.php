<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MediaSourceRegistry implements MediaSourceRegistryInterface
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
     * @return list<MediaUsageSourceInterface>
     */
    public function sources(): array
    {
        return array_values(array_map(
            static fn (array $entry): MediaUsageSourceInterface => $entry[0],
            $this->resolveAll(),
        ));
    }

    public function source(string $type): ?MediaUsageSourceInterface
    {
        return $this->resolveAll()[$type][0] ?? null;
    }

    public function claimingSource(MediaFileId $file): ?MediaUsageSourceInterface
    {
        $path = $file->value;
        $best = null;
        $bestLength = -1;

        foreach ($this->resolveAll() as [$source]) {
            foreach ($this->folders($source) as $folder) {
                if ($folder === '' || ! str_starts_with($path, $folder) || strlen($folder) <= $bestLength) {
                    continue;
                }

                if ($this->isExcluded(substr($path, strlen($folder)), $source)) {
                    continue;
                }

                $best = $source;
                $bestLength = strlen($folder);
            }
        }

        return $best;
    }

    public function moduleOf(string $type): ?string
    {
        return $this->resolveAll()[$type][1] ?? null;
    }

    /**
     * @return list<string>
     */
    public function hiddenFolders(): array
    {
        $folders = [];

        foreach ($this->resolveAll() as [$source]) {
            if ($source->hidden()) {
                array_push($folders, ...$this->folders($source));
            }
        }

        return array_values(array_unique($folders));
    }

    /**
     * Resolved sources by type with their module, skipping the ones that cannot be loaded
     * and any whose type is already taken.
     *
     * @return array<string, array{MediaUsageSourceInterface, ?string}>
     */
    private function resolveAll(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof MediaUsageSourceInterface) {
                continue;
            }

            try {
                $type = $source->type();
            } catch (Throwable $exception) {
                Log::warning('[MediaSourceRegistry] Failed to read media source type', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if (isset($resolved[$type])) {
                Log::warning('[MediaSourceRegistry] Media source type already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'type' => $type,
                    'takenBy' => $resolved[$type][0]::class,
                ]);

                continue;
            }

            $resolved[$type] = [$source, $module];
        }

        return $resolved;
    }

    private function resolve(string $sourceClass, ?string $module): ?MediaUsageSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[MediaSourceRegistry] Failed to resolve media source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof MediaUsageSourceInterface) {
            Log::warning('[MediaSourceRegistry] Registered class is not a media usage source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    /**
     * @return list<string>
     */
    private function folders(MediaUsageSourceInterface $source): array
    {
        try {
            return $source->folders();
        } catch (Throwable $exception) {
            Log::warning('[MediaSourceRegistry] Failed to read media source folders', [
                'source' => $source::class,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function isExcluded(string $rest, MediaUsageSourceInterface $source): bool
    {
        try {
            $excluded = $source->excludedSubfolders();
        } catch (Throwable $exception) {
            Log::warning('[MediaSourceRegistry] Failed to read media source excluded subfolders', [
                'source' => $source::class,
                'error' => $exception->getMessage(),
            ]);

            // Fail closed: do not claim anything we cannot tell is ours
            return true;
        }

        return array_any($excluded, fn ($subfolder) => $subfolder !== '' && str_starts_with($rest, $subfolder));
    }
}
