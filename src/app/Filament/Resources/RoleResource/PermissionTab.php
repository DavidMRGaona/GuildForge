<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

final readonly class PermissionTab
{
    /**
     * @param  list<PermissionSection>  $sections
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $sections,
    ) {}
}
