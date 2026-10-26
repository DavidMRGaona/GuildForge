<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Concerns\SyncsPermissionTabs;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use GuardsRolePermissions;
    use RecordsRoleHistory;
    use SyncsPermissionTabs;

    protected static string $resource = RoleResource::class;

    /** The role, its permissions and its history entry are saved together or not at all */
    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeCreate(): void
    {
        $ids = $this->collectPermissionIds($this->data ?? [], null);

        $this->guardSubmittedPermissions([(string) ($this->data['name'] ?? '')], [], $ids);
        $this->rememberPermissionIdsToSync($ids);
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

        // Synced after creating (afterCreate), never written to the role itself
        unset($data['permissions_by_section'], $data['panel_access']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        $this->syncPermissionTabs($record);
        $this->recordDefinitionChange($record, RoleAuditEvent::RoleCreated);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
