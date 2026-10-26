<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use GuardsRolePermissions;

    protected static string $resource = RoleResource::class;

    protected function beforeCreate(): void
    {
        $this->guardSubmittedPermissions((string) ($this->data['name'] ?? ''), []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! app(RoleRestrictionRule::class)->canBeRestricted((string) ($data['name'] ?? ''))) {
            unset($data['requires_association_member'], $data['max_holders']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
