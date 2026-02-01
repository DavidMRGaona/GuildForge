<?php

declare(strict_types=1);

namespace App\Domain\Updates\Enums;

/**
 * Status of an update operation.
 */
enum UpdateStatus: string
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Verifying = 'verifying';
    case BackingUp = 'backing_up';
    case Applying = 'applying';
    case Migrating = 'migrating';
    case Seeding = 'seeding';
    case HealthChecking = 'health_checking';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::RolledBack => true,
            default => false,
        };
    }

    public function isInProgress(): bool
    {
        return ! $this->isTerminal() && $this !== self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Downloading => 'Descargando',
            self::Verifying => 'Verificando',
            self::BackingUp => 'Creando backup',
            self::Applying => 'Aplicando',
            self::Migrating => 'Ejecutando migraciones',
            self::Seeding => 'Ejecutando seeders',
            self::HealthChecking => 'Verificando estado',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
            self::RolledBack => 'Revertido',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Downloading => 'heroicon-o-arrow-down-tray',
            self::Verifying => 'heroicon-o-shield-check',
            self::BackingUp => 'heroicon-o-archive-box',
            self::Applying => 'heroicon-o-cog',
            self::Migrating => 'heroicon-o-circle-stack',
            self::Seeding => 'heroicon-o-squares-plus',
            self::HealthChecking => 'heroicon-o-heart',
            self::Completed => 'heroicon-o-check-circle',
            self::Failed => 'heroicon-o-x-circle',
            self::RolledBack => 'heroicon-o-arrow-uturn-left',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Downloading, self::Verifying, self::BackingUp, self::Applying, self::Migrating, self::Seeding, self::HealthChecking => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::RolledBack => 'warning',
        };
    }
}
