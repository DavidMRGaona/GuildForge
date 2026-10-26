<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

final readonly class PermissionSection
{
    /**
     * @param  list<PermissionOption>  $permissions
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $permissions,
    ) {}
}
