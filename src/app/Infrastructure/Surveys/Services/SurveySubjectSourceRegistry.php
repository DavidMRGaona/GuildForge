<?php

declare(strict_types=1);

namespace App\Infrastructure\Surveys\Services;

use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SurveySubjectSourceRegistry implements SurveySubjectSourceRegistryInterface
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
     * @return array<string, SurveySubjectSourceInterface>
     */
    public function sources(): array
    {
        $resolved = [];

        foreach ($this->sources as $sourceClass => $module) {
            $source = $this->resolve($sourceClass, $module);

            $sourceType = $source === null ? null : $this->sourceType($source, $module);

            if ($source === null || $sourceType === null) {
                continue;
            }

            if (isset($resolved[$sourceType])) {
                Log::warning('[SurveySubjectSourceRegistry] Survey subject type already taken, source skipped', [
                    'source' => $sourceClass,
                    'module' => $module,
                    'sourceType' => $sourceType,
                    'takenBy' => $resolved[$sourceType]::class,
                ]);

                continue;
            }

            $resolved[$sourceType] = $source;
        }

        return $resolved;
    }

    public function source(string $sourceType): ?SurveySubjectSourceInterface
    {
        return $this->sources()[$sourceType] ?? null;
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
    private function resolve(string $sourceClass, ?string $module): ?SurveySubjectSourceInterface
    {
        try {
            $source = $this->container->make($sourceClass);
        } catch (Throwable $exception) {
            Log::warning('[SurveySubjectSourceRegistry] Failed to resolve survey subject source', [
                'source' => $sourceClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $source instanceof SurveySubjectSourceInterface) {
            Log::warning('[SurveySubjectSourceRegistry] Registered class is not a survey subject source', [
                'source' => $sourceClass,
                'module' => $module,
            ]);

            return null;
        }

        return $source;
    }

    private function sourceType(SurveySubjectSourceInterface $source, ?string $module): ?string
    {
        try {
            return $source->sourceType();
        } catch (Throwable $exception) {
            Log::warning('[SurveySubjectSourceRegistry] Failed to read survey subject type', [
                'source' => $source::class,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
