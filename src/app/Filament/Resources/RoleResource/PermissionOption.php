<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

final readonly class PermissionOption
{
    /**
     * @param  list<string>  $suggestedFor  Roles the definition suggests this permission for
     */
    public function __construct(
        public string $id,
        public string $key,
        public string $label,
        public ?string $description,
        public array $suggestedFor,
    ) {}
}
