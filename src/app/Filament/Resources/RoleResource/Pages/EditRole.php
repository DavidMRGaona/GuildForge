<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Filament\Resources\RoleResource;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    use GuardsRolePermissions;
    use RecordsRoleHistory;

    protected static string $resource = RoleResource::class;

    /** The role, its permissions and its history entry are saved together or not at all */
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        return [
            RoleResource::deleteAction()
                ->hidden(fn (): bool => RoleResource::isLocked($record)),
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

        $this->rememberDefinitionBeforeSave($record);

        $names = [$record->name];

        // A renamed role is checked under its new name too (a locked role keeps its own)
        if (! RoleResource::isLocked($record) && is_string($this->data['name'] ?? null)) {
            $names[] = $this->data['name'];
        }

        $this->guardSubmittedPermissions(
            $names,
            array_values($record->permissions()->pluck('permissions.id')->map(strval(...))->all()),
        );
    }

    protected function afterSave(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        $this->recordDefinitionChange($record, RoleAuditEvent::RoleUpdated);

        // The permissions checkbox list writes the pivot directly: every holder of the role is affected
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
