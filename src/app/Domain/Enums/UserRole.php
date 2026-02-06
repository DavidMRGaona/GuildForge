<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Editor => 'Editor',
            self::Member => 'Member',
        };
    }
}
