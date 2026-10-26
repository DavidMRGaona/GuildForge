<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Filament\Resources\RoleResource;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use GuardsRolePermissions;
    use RecordsRoleHistory;

    protected static string $resource = RoleResource::class;

    /** The role, its permissions and its history entry are saved together or not at all */
    protected ?bool $hasDatabaseTransactions = true;

    protected function beforeCreate(): void
    {
        $this->guardSubmittedPermissions([(string) ($this->data['name'] ?? '')], []);
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

    protected function afterCreate(): void
    {
        /** @var RoleModel $record */
        $record = $this->record;

        $this->recordDefinitionChange($record, RoleAuditEvent::RoleCreated);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
