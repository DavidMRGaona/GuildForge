<?php

declare(strict_types=1);

namespace App\Application\Updates\DTOs;

/**
 * Result of a module health check after update.
 */
final readonly class HealthCheckResultDTO
{
    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     */
    public function __construct(
        public bool $providerLoads,
        public bool $routesRespond,
        public bool $filamentRegisters,
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    public function passes(): bool
    {
        return $this->providerLoads && empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_loads' => $this->providerLoads,
            'routes_respond' => $this->routesRespond,
            'filament_registers' => $this->filamentRegisters,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'passes' => $this->passes(),
        ];
    }
}
