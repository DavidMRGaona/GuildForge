<?php

declare(strict_types=1);

namespace App\Domain\Modules\Enums;

enum RequirementType: string
{
    case Core = 'core';
    case Php = 'php';
    case Laravel = 'laravel';
    case Filament = 'filament';
    case Extension = 'extension';
    case Manifest = 'manifest';

    /**
     * Name used in English log and exception messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Core => 'core',
            self::Php => 'PHP',
            self::Laravel => 'Laravel',
            self::Filament => 'Filament',
            self::Extension => 'extension',
            self::Manifest => 'module.json',
        };
    }
}
