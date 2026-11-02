<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final class KnowledgeCapabilityRegistry implements KnowledgeCapabilityRegistryInterface
{
    /** @var array<string, ?string> Map of capability class to owning module (null for core). */
    private array $capabilities = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $capabilityClass, ?string $module = null): void
    {
        $this->capabilities[$capabilityClass] = $module;
    }

    public function registerMany(array $capabilityClasses, ?string $module = null): void
    {
        foreach ($capabilityClasses as $capabilityClass) {
            $this->register($capabilityClass, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->capabilities = array_filter(
            $this->capabilities,
            fn (?string $capabilityModule): bool => $capabilityModule !== $module
        );
    }

    public function clear(): void
    {
        $this->capabilities = [];
    }

    public function keys(): array
    {
        return array_keys($this->resolved());
    }

    public function evaluate(string $key, object $user): ?bool
    {
        $capability = $this->resolved()[$key] ?? null;

        if ($capability === null) {
            return null;
        }

        try {
            return $capability->passes($user);
        } catch (Throwable $exception) {
            Log::warning('[KnowledgeCapabilityRegistry] Capability threw while being evaluated', [
                'capability' => $capability::class,
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, KnowledgeCapabilityInterface>
     */
    private function resolved(): array
    {
        $resolved = [];

        foreach ($this->capabilities as $capabilityClass => $module) {
            $capability = $this->resolve($capabilityClass, $module);

            if (! $capability instanceof KnowledgeCapabilityInterface) {
                continue;
            }

            try {
                $key = $capability->key();
            } catch (Throwable $exception) {
                Log::warning('[KnowledgeCapabilityRegistry] Failed to read capability key', [
                    'capability' => $capabilityClass,
                    'module' => $module,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if (isset($resolved[$key])) {
                Log::warning('[KnowledgeCapabilityRegistry] Capability key already taken, capability skipped', [
                    'capability' => $capabilityClass,
                    'module' => $module,
                    'key' => $key,
                    'takenBy' => $resolved[$key]::class,
                ]);

                continue;
            }

            $resolved[$key] = $capability;
        }

        return $resolved;
    }

    private function resolve(string $capabilityClass, ?string $module): ?KnowledgeCapabilityInterface
    {
        try {
            $capability = $this->container->make($capabilityClass);
        } catch (Throwable $exception) {
            Log::warning('[KnowledgeCapabilityRegistry] Failed to resolve capability', [
                'capability' => $capabilityClass,
                'module' => $module,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $capability instanceof KnowledgeCapabilityInterface) {
            Log::warning('[KnowledgeCapabilityRegistry] Registered class is not a knowledge capability', [
                'capability' => $capabilityClass,
                'module' => $module,
            ]);

            return null;
        }

        return $capability;
    }
}
