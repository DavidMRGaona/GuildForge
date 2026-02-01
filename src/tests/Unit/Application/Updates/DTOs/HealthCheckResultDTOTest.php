<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Updates\DTOs;

use App\Application\Updates\DTOs\HealthCheckResultDTO;
use PHPUnit\Framework\TestCase;

final class HealthCheckResultDTOTest extends TestCase
{
    public function test_it_creates_dto_with_all_properties(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
            errors: [],
            warnings: [],
        );

        $this->assertTrue($dto->providerLoads);
        $this->assertTrue($dto->routesRespond);
        $this->assertTrue($dto->filamentRegisters);
        $this->assertEmpty($dto->errors);
        $this->assertEmpty($dto->warnings);
    }

    public function test_passes_returns_true_when_provider_loads_and_no_errors(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
            errors: [],
            warnings: [],
        );

        $this->assertTrue($dto->passes());
    }

    public function test_passes_returns_false_when_provider_fails_to_load(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: false,
            routesRespond: true,
            filamentRegisters: true,
            errors: ['Service provider failed to load'],
            warnings: [],
        );

        $this->assertFalse($dto->passes());
    }

    public function test_passes_returns_false_when_errors_exist(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: false,
            filamentRegisters: true,
            errors: ['Critical route not responding'],
            warnings: [],
        );

        $this->assertFalse($dto->passes());
    }

    public function test_passes_returns_true_when_provider_loads_and_only_warnings_exist(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: false,
            errors: [],
            warnings: ['Filament resources may not be registered correctly'],
        );

        $this->assertTrue($dto->passes());
    }

    public function test_has_warnings_returns_true_when_warnings_exist(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
            errors: [],
            warnings: ['Some optional feature not available'],
        );

        $this->assertTrue($dto->hasWarnings());
    }

    public function test_has_warnings_returns_false_when_no_warnings(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
            errors: [],
            warnings: [],
        );

        $this->assertFalse($dto->hasWarnings());
    }

    public function test_it_collects_all_errors(): void
    {
        $errors = [
            'Service provider failed to load',
            'Database connection error',
            'Missing required configuration',
        ];

        $dto = new HealthCheckResultDTO(
            providerLoads: false,
            routesRespond: false,
            filamentRegisters: false,
            errors: $errors,
            warnings: [],
        );

        $this->assertCount(3, $dto->errors);
        $this->assertContains('Service provider failed to load', $dto->errors);
        $this->assertContains('Database connection error', $dto->errors);
        $this->assertContains('Missing required configuration', $dto->errors);
    }

    public function test_it_collects_all_warnings(): void
    {
        $warnings = [
            'Module routes may not be responding correctly',
            'Filament resources may not be registered correctly',
        ];

        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: false,
            filamentRegisters: false,
            errors: [],
            warnings: $warnings,
        );

        $this->assertCount(2, $dto->warnings);
        $this->assertContains('Module routes may not be responding correctly', $dto->warnings);
        $this->assertContains('Filament resources may not be registered correctly', $dto->warnings);
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: false,
            filamentRegisters: true,
            errors: ['Route error'],
            warnings: ['Minor warning'],
        );

        $array = $dto->toArray();

        $this->assertTrue($array['provider_loads']);
        $this->assertFalse($array['routes_respond']);
        $this->assertTrue($array['filament_registers']);
        $this->assertEquals(['Route error'], $array['errors']);
        $this->assertEquals(['Minor warning'], $array['warnings']);
        $this->assertFalse($array['passes']);
    }

    public function test_to_array_includes_passes_status(): void
    {
        $passingDto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
            errors: [],
            warnings: [],
        );

        $failingDto = new HealthCheckResultDTO(
            providerLoads: false,
            routesRespond: true,
            filamentRegisters: true,
            errors: ['Error'],
            warnings: [],
        );

        $this->assertTrue($passingDto->toArray()['passes']);
        $this->assertFalse($failingDto->toArray()['passes']);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_default_values_for_optional_arrays(): void
    {
        $dto = new HealthCheckResultDTO(
            providerLoads: true,
            routesRespond: true,
            filamentRegisters: true,
        );

        $this->assertIsArray($dto->errors);
        $this->assertIsArray($dto->warnings);
        $this->assertEmpty($dto->errors);
        $this->assertEmpty($dto->warnings);
    }
}
