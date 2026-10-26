<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Authorization\Services\RoleDefinitionChanges;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;

/**
 * Writes the role history entry of a save in the role editor. The definition is read before
 * the save (beforeSave) and after it (afterSave / afterCreate, once the checkbox list has
 * synced the permissions), inside the page's database transaction: when the entry cannot be
 * written, the save is rolled back.
 */
trait RecordsRoleHistory
{
    /** @var list<string> */
    private array $permissionKeysBeforeSave = [];

    /** @var array<string, mixed> */
    private array $fieldsBeforeSave = [];

    private function rememberDefinitionBeforeSave(RoleModel $record): void
    {
        $this->permissionKeysBeforeSave = $this->permissionKeysOf($record);
        $this->fieldsBeforeSave = $record->only(RoleDefinitionChanges::FIELDS);
    }

    /**
     * A new role is always recorded; an edited one only when its permissions, name,
     * association member requirement or holder limit changed.
     */
    private function recordDefinitionChange(RoleModel $record, RoleAuditEvent $event): void
    {
        $fresh = $record->fresh() ?? $record;

        $changes = app(RoleDefinitionChanges::class)->between(
            $this->permissionKeysBeforeSave,
            $this->permissionKeysOf($fresh),
            $this->fieldsBeforeSave,
            $fresh->only(RoleDefinitionChanges::FIELDS),
        );

        if ($event === RoleAuditEvent::RoleUpdated && $changes === []) {
            return;
        }

        app(RoleAuditLoggerInterface::class)->roleChanged($fresh->id, $fresh->name, $event, $changes);
    }

    /**
     * @return list<string>
     */
    private function permissionKeysOf(RoleModel $record): array
    {
        /** @var list<string> */
        return array_values($record->permissions()->pluck('permissions.key')->map(strval(...))->all());
    }
}
