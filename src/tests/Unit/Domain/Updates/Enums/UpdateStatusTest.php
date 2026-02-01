<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Updates\Enums;

use App\Domain\Updates\Enums\UpdateStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpdateStatusTest extends TestCase
{
    public function test_it_has_expected_cases(): void
    {
        $cases = UpdateStatus::cases();

        $this->assertCount(11, $cases);
        $this->assertContains(UpdateStatus::Pending, $cases);
        $this->assertContains(UpdateStatus::Downloading, $cases);
        $this->assertContains(UpdateStatus::Verifying, $cases);
        $this->assertContains(UpdateStatus::BackingUp, $cases);
        $this->assertContains(UpdateStatus::Applying, $cases);
        $this->assertContains(UpdateStatus::Migrating, $cases);
        $this->assertContains(UpdateStatus::Seeding, $cases);
        $this->assertContains(UpdateStatus::HealthChecking, $cases);
        $this->assertContains(UpdateStatus::Completed, $cases);
        $this->assertContains(UpdateStatus::Failed, $cases);
        $this->assertContains(UpdateStatus::RolledBack, $cases);
    }

    #[DataProvider('terminalStatusesProvider')]
    public function test_is_terminal_returns_true_for_terminal_statuses(UpdateStatus $status): void
    {
        $this->assertTrue($status->isTerminal());
    }

    public static function terminalStatusesProvider(): array
    {
        return [
            'completed' => [UpdateStatus::Completed],
            'failed' => [UpdateStatus::Failed],
            'rolled_back' => [UpdateStatus::RolledBack],
        ];
    }

    #[DataProvider('nonTerminalStatusesProvider')]
    public function test_is_terminal_returns_false_for_non_terminal_statuses(UpdateStatus $status): void
    {
        $this->assertFalse($status->isTerminal());
    }

    public static function nonTerminalStatusesProvider(): array
    {
        return [
            'pending' => [UpdateStatus::Pending],
            'downloading' => [UpdateStatus::Downloading],
            'verifying' => [UpdateStatus::Verifying],
            'backing_up' => [UpdateStatus::BackingUp],
            'applying' => [UpdateStatus::Applying],
            'migrating' => [UpdateStatus::Migrating],
            'seeding' => [UpdateStatus::Seeding],
            'health_checking' => [UpdateStatus::HealthChecking],
        ];
    }

    #[DataProvider('inProgressStatusesProvider')]
    public function test_is_in_progress_returns_true_for_in_progress_statuses(UpdateStatus $status): void
    {
        $this->assertTrue($status->isInProgress());
    }

    public static function inProgressStatusesProvider(): array
    {
        return [
            'downloading' => [UpdateStatus::Downloading],
            'verifying' => [UpdateStatus::Verifying],
            'backing_up' => [UpdateStatus::BackingUp],
            'applying' => [UpdateStatus::Applying],
            'migrating' => [UpdateStatus::Migrating],
            'seeding' => [UpdateStatus::Seeding],
            'health_checking' => [UpdateStatus::HealthChecking],
        ];
    }

    #[DataProvider('notInProgressStatusesProvider')]
    public function test_is_in_progress_returns_false_for_pending_and_terminal_statuses(UpdateStatus $status): void
    {
        $this->assertFalse($status->isInProgress());
    }

    public static function notInProgressStatusesProvider(): array
    {
        return [
            'pending' => [UpdateStatus::Pending],
            'completed' => [UpdateStatus::Completed],
            'failed' => [UpdateStatus::Failed],
            'rolled_back' => [UpdateStatus::RolledBack],
        ];
    }

    #[DataProvider('labelsProvider')]
    public function test_label_returns_correct_spanish_labels(UpdateStatus $status, string $expectedLabel): void
    {
        $this->assertEquals($expectedLabel, $status->label());
    }

    public static function labelsProvider(): array
    {
        return [
            'pending' => [UpdateStatus::Pending, 'Pendiente'],
            'downloading' => [UpdateStatus::Downloading, 'Descargando'],
            'verifying' => [UpdateStatus::Verifying, 'Verificando'],
            'backing_up' => [UpdateStatus::BackingUp, 'Creando backup'],
            'applying' => [UpdateStatus::Applying, 'Aplicando'],
            'migrating' => [UpdateStatus::Migrating, 'Ejecutando migraciones'],
            'seeding' => [UpdateStatus::Seeding, 'Ejecutando seeders'],
            'health_checking' => [UpdateStatus::HealthChecking, 'Verificando estado'],
            'completed' => [UpdateStatus::Completed, 'Completado'],
            'failed' => [UpdateStatus::Failed, 'Fallido'],
            'rolled_back' => [UpdateStatus::RolledBack, 'Revertido'],
        ];
    }

    #[DataProvider('iconsProvider')]
    public function test_icon_returns_correct_heroicon_names(UpdateStatus $status, string $expectedIcon): void
    {
        $this->assertEquals($expectedIcon, $status->icon());
    }

    public static function iconsProvider(): array
    {
        return [
            'pending' => [UpdateStatus::Pending, 'heroicon-o-clock'],
            'downloading' => [UpdateStatus::Downloading, 'heroicon-o-arrow-down-tray'],
            'verifying' => [UpdateStatus::Verifying, 'heroicon-o-shield-check'],
            'backing_up' => [UpdateStatus::BackingUp, 'heroicon-o-archive-box'],
            'applying' => [UpdateStatus::Applying, 'heroicon-o-cog'],
            'migrating' => [UpdateStatus::Migrating, 'heroicon-o-circle-stack'],
            'seeding' => [UpdateStatus::Seeding, 'heroicon-o-squares-plus'],
            'health_checking' => [UpdateStatus::HealthChecking, 'heroicon-o-heart'],
            'completed' => [UpdateStatus::Completed, 'heroicon-o-check-circle'],
            'failed' => [UpdateStatus::Failed, 'heroicon-o-x-circle'],
            'rolled_back' => [UpdateStatus::RolledBack, 'heroicon-o-arrow-uturn-left'],
        ];
    }

    #[DataProvider('colorsProvider')]
    public function test_color_returns_correct_filament_color_names(UpdateStatus $status, string $expectedColor): void
    {
        $this->assertEquals($expectedColor, $status->color());
    }

    public static function colorsProvider(): array
    {
        return [
            'pending' => [UpdateStatus::Pending, 'gray'],
            'downloading' => [UpdateStatus::Downloading, 'info'],
            'verifying' => [UpdateStatus::Verifying, 'info'],
            'backing_up' => [UpdateStatus::BackingUp, 'info'],
            'applying' => [UpdateStatus::Applying, 'info'],
            'migrating' => [UpdateStatus::Migrating, 'info'],
            'seeding' => [UpdateStatus::Seeding, 'info'],
            'health_checking' => [UpdateStatus::HealthChecking, 'info'],
            'completed' => [UpdateStatus::Completed, 'success'],
            'failed' => [UpdateStatus::Failed, 'danger'],
            'rolled_back' => [UpdateStatus::RolledBack, 'warning'],
        ];
    }

    public function test_values_are_correct_string_representations(): void
    {
        $this->assertEquals('pending', UpdateStatus::Pending->value);
        $this->assertEquals('downloading', UpdateStatus::Downloading->value);
        $this->assertEquals('verifying', UpdateStatus::Verifying->value);
        $this->assertEquals('backing_up', UpdateStatus::BackingUp->value);
        $this->assertEquals('applying', UpdateStatus::Applying->value);
        $this->assertEquals('migrating', UpdateStatus::Migrating->value);
        $this->assertEquals('seeding', UpdateStatus::Seeding->value);
        $this->assertEquals('health_checking', UpdateStatus::HealthChecking->value);
        $this->assertEquals('completed', UpdateStatus::Completed->value);
        $this->assertEquals('failed', UpdateStatus::Failed->value);
        $this->assertEquals('rolled_back', UpdateStatus::RolledBack->value);
    }
}
