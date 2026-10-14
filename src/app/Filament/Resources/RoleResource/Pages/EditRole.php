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
                ->hidden(fn (): bool => $record->is_protected || RoleResource::isAssociationMemberRole($record))
                ->after(static fn () => app(AuthorizationServiceInterface::class)->invalidateCache()),
        ];
    }

    /**
     * The name field is disabled for protected roles and for the association member role;
     * a forged Livewire state must not rename them either.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        if ($record->is_protected || RoleResource::isAssociationMemberRole($record)) {
            unset($data['name']);
        }

        return $data;
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
