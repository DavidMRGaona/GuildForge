<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';

    public function canManageContent(): bool
    {
        return match ($this) {
            self::Admin, self::Editor => true,
            self::Member => false,
        };
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canAccessPanel(): bool
    {
        return match ($this) {
            self::Admin, self::Editor => true,
            self::Member => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Editor => 'Editor',
            self::Member => 'Member',
        };
    }
}
