<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\Exceptions;

use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use DomainException;
use PHPUnit\Framework\TestCase;

final class ModuleMigrationViolationExceptionTest extends TestCase
{
    public function test_it_formats_message_with_module_name_file_and_violations(): void
    {
        $exception = ModuleMigrationViolationException::withViolations(
            moduleName: 'game-tables',
            fileName: '2024_01_15_create_game_tables.php',
            violations: [
                'Uses DB::statement() with raw SQL',
                'Contains DROP TABLE operation',
            ]
        );

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertStringContainsString('game-tables', $exception->getMessage());
        $this->assertStringContainsString('2024_01_15_create_game_tables.php', $exception->getMessage());
        $this->assertStringContainsString('Uses DB::statement() with raw SQL', $exception->getMessage());
        $this->assertStringContainsString('Contains DROP TABLE operation', $exception->getMessage());
        $this->assertSame(
            "Module 'game-tables' migration '2024_01_15_create_game_tables.php' contains prohibited operations: Uses DB::statement() with raw SQL; Contains DROP TABLE operation",
            $exception->getMessage()
        );
    }

    public function test_it_exposes_structured_data(): void
    {
        $violations = [
            'Uses DB::statement() with raw SQL',
            'Contains DROP TABLE operation',
        ];

        $exception = ModuleMigrationViolationException::withViolations(
            moduleName: 'venue-bookings',
            fileName: '2024_06_01_add_venue_columns.php',
            violations: $violations
        );

        $this->assertSame('venue-bookings', $exception->moduleName);
        $this->assertSame('2024_06_01_add_venue_columns.php', $exception->fileName);
        $this->assertSame($violations, $exception->violations);
    }
}
