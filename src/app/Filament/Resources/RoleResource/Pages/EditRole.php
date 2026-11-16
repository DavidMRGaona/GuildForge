<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Concerns\SyncsPermissionTabs;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRole extends EditRecord
{
    use GuardsRolePermissions;
    use RecordsRoleHistory;
    use SyncsPermissionTabs;

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var RoleModel $record */
        $record = $this->record;

        return [...$data, ...$this->fillPermissionTabs($record)];
    }

    /**
     * The name field is disabled for protected roles, the base role of every account and the
     * association member role; a forged Livewire state must not rename them either. Nor may it
     * put a requirement on a role that cannot carry it, or make a role the actor holds require
     * two-factor authentication while they have it off (core#772).
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

        // Cleared, not just left out: a mark written there by other means can still be taken away
        if (! RoleResource::canRequireTwoFactor($record)) {
            $data['requires_two_factor'] = false;
        }

        // Also when the field's own rule was skipped: nobody pauses a role they hold by mistake
        if (array_key_exists('requires_two_factor', $data) && RoleResource::refusesTwoFactorMark($record, (bool) $data['requires_two_factor'])) {
            throw ValidationException::withMessages([
                'data.requires_two_factor' => (string) __('filament.roles.fields.twoFactorOwnRole'),
            ]);
        }

        // Synced after saving (afterSave), never written to the role itself
        unset($data['permissions_by_section'], $data['panel_access']);

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

        $ids = $this->collectPermissionIds($this->data ?? [], $record);

        $this->guardSubmittedPermissions($names, $this->currentPermissionIds($record), $ids);
        $this->rememberPermissionIdsToSync($ids);
    }

    protected function afterSave(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        $this->syncPermissionTabs($record);
        $this->recordDefinitionChange($record, RoleAuditEvent::RoleUpdated);

        // Every holder of the role is affected by its permissions
        app(AuthorizationServiceInterface::class)->invalidateCache();

        $this->announcePermissionChange($record);
        $this->announceRequirementChange($record);
        $this->announceTwoFactorRequirementChange($record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
