<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

final class EditorTextSourceRegistry implements EditorTextSourceRegistryInterface
{
    /** @var array<class-string, ?string> Map of source class to owning module (null for core). */
    private array $sources = [];

    /** @var array<string, EditorTextSourceInterface>|null Sources by type, built once until the registrations change */
    private ?array $resolved = null;

    /** @var list<class-string> Classes left out of the sources, found by the same pass that resolves them */
    private array $unresolved = [];

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
     * @return list<EditorTextSourceInterface>
     */
    public function sources(): array
    {
        return array_values($this->resolveAll());
    }

    public function source(string $type): ?EditorTextSourceInterface
    {
        return $this->resolveAll()[$type] ?? null;
    }

    /**
     * @return list<class-string>
     */
    public function unresolved(): array
    {
        $this->resolveAll();

        return $this->unresolved;
    }

    /**
     * @return array<string, EditorTextSourceInterface>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resolved = [];
        $unresolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            try {
                $source = $this->container->make($sourceClass);

                if (! $source instanceof EditorTextSourceInterface) {
                    throw new UnexpectedValueException('Not an editor text source');
                }

                $type = $source->type();
            } catch (Throwable $exception) {
                Log::warning('[EditorTextSourceRegistry] Editor text source left out', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'error' => $exception->getMessage(),
                ]);
                $unresolved[] = $sourceClass;

                continue;
            }

            if (isset($resolved[$type])) {
                Log::warning('[EditorTextSourceRegistry] Editor text source type already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'type' => $type,
                    'takenBy' => $resolved[$type]::class,
                ]);
                $unresolved[] = $sourceClass;

                continue;
            }

            $resolved[$type] = $source;
        }

        $this->unresolved = $unresolved;

        return $this->resolved = $resolved;
    }
}
