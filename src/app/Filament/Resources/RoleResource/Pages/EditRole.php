<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Filament\Resources\RoleResource;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use GuardsRolePermissions;

    protected static string $resource = RoleResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        return [
            DeleteAction::make()
                ->hidden(fn (): bool => RoleResource::isLocked($record))
                ->after(static fn () => app(AuthorizationServiceInterface::class)->invalidateCache()),
        ];
    }

    /**
     * The name field is disabled for protected roles, the base role of every account and the
     * association member role; a forged Livewire state must not rename them either.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        if (RoleResource::isLocked($record)) {
            unset($data['name']);
        }

        if (! RoleResource::canBeRestricted($record)) {
            unset($data['requires_association_member'], $data['max_holders']);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        $this->guardSubmittedPermissions(
            $record->name,
            array_values($record->permissions()->pluck('permissions.id')->map(strval(...))->all()),
        );
    }

    protected function afterSave(): void
    {
        // The permissions checkbox list writes the pivot directly: every holder of the role is affected
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
