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

    /**
     * Sources resolved since the registrations last changed: the sync and the panel ask for the claiming
     * source of every file, so building them on each call would rebuild every source thousands of times.
     *
     * @var array<string, array{source: MediaUsageSourceInterface, module: ?string, folders: list<string>, excluded: list<string>|null}>|null
     */
    private ?array $resolved = null;

    /** @var list<class-string> Classes left out of the sources, found by the same pass that resolves them */
    private array $unresolved = [];

    /** @var array<string, true> Problems already logged, by source class and kind, so each is reported once */
    private array $warned = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $sourceClass, ?string $module = null): void
    {
        $this->sources[$sourceClass] = $module;
        $this->resolved = null;
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
        $this->resolved = null;
    }

    public function clear(): void
    {
        $this->sources = [];
        $this->resolved = null;
    }

    /**
     * @return list<MediaUsageSourceInterface>
     */
    public function sources(): array
    {
        return array_values(array_map(
            static fn (array $entry): MediaUsageSourceInterface => $entry['source'],
            $this->resolveAll(),
        ));
    }

    public function source(string $type): ?MediaUsageSourceInterface
    {
        return $this->resolveAll()[$type]['source'] ?? null;
    }

    /**
     * @return list<class-string>
     */
    public function unresolved(): array
    {
        $this->resolveAll();

        return $this->unresolved;
    }

    public function claimingSource(MediaFileId $file): ?MediaUsageSourceInterface
    {
        $path = $file->value;
        $best = null;
        $bestLength = -1;

        foreach ($this->resolveAll() as $entry) {
            foreach ($entry['folders'] as $folder) {
                if ($folder === '' || ! str_starts_with($path, $folder) || strlen($folder) <= $bestLength) {
                    continue;
                }

                if ($this->isExcluded(substr($path, strlen($folder)), $entry['excluded'])) {
                    continue;
                }

                $best = $entry['source'];
                $bestLength = strlen($folder);
            }
        }

        return $best;
    }

    public function moduleOf(string $type): ?string
    {
        return $this->resolveAll()[$type]['module'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function hiddenFolders(): array
    {
        $folders = [];

        foreach ($this->resolveAll() as $entry) {
            if ($entry['source']->hidden()) {
                array_push($folders, ...$entry['folders']);
            }
        }

        return array_values(array_unique($folders));
    }

    /**
     * Resolved sources by type with their module and folders, skipping (and keeping in $unresolved) the ones
     * that cannot be loaded and any whose type is already taken. Built once until the registrations change.
     *
     * @return array<string, array{source: MediaUsageSourceInterface, module: ?string, folders: list<string>, excluded: list<string>|null}>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resolved = [];
        $unresolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            if (! $source instanceof MediaUsageSourceInterface) {
                $unresolved[] = $sourceClass;

                continue;
            }

            try {
                $type = $source->type();
            } catch (Throwable $exception) {
                $this->warnOnce($sourceClass, 'type', '[MediaSourceRegistry] Failed to read media source type', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'error' => $exception->getMessage(),
                ]);
                $unresolved[] = $sourceClass;

                continue;
            }

            if (isset($resolved[$type])) {
                $this->warnOnce($sourceClass, 'duplicate', '[MediaSourceRegistry] Media source type already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'type' => $type,
                    'takenBy' => $resolved[$type]['source']::class,
                ]);

                $unresolved[] = $sourceClass;

                continue;
            }

            $resolved[$type] = [
                'source' => $source,
                'module' => $module,
                'folders' => $this->folders($source),
                'excluded' => $this->excludedSubfolders($source),
            ];
        }

        $this->unresolved = $unresolved;

        return $this->resolved = $resolved;
    }

    private function resolve(string $sourceClass, ?string $module): ?MediaUsageSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            $this->warnOnce($sourceClass, 'resolve', '[MediaSourceRegistry] Failed to resolve media source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof MediaUsageSourceInterface) {
            $this->warnOnce($sourceClass, 'contract', '[MediaSourceRegistry] Registered class is not a media usage source', [
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
            $this->warnOnce($source::class, 'folders', '[MediaSourceRegistry] Failed to read media source folders', [
                'source' => $source::class,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<string>|null null when they cannot be read
     */
    private function excludedSubfolders(MediaUsageSourceInterface $source): ?array
    {
        try {
            return $source->excludedSubfolders();
        } catch (Throwable $exception) {
            $this->warnOnce($source::class, 'excluded', '[MediaSourceRegistry] Failed to read media source excluded subfolders', [
                'source' => $source::class,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<string>|null  $excluded
     */
    private function isExcluded(string $rest, ?array $excluded): bool
    {
        // Fail closed: do not claim anything we cannot tell is ours
        if ($excluded === null) {
            return true;
        }

        return array_any($excluded, fn ($subfolder) => $subfolder !== '' && str_starts_with($rest, $subfolder));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnOnce(string $sourceClass, string $kind, string $message, array $context): void
    {
        $key = $sourceClass.'|'.$kind;

        if (isset($this->warned[$key])) {
            return;
        }

        $this->warned[$key] = true;
        Log::warning($message, $context);
    }
}
