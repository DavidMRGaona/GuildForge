<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Authorization\Services\RoleDefinitionChanges;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Events\RolePermissionsChanged;
use App\Infrastructure\Authorization\Services\RoleChangeAnnouncer;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Writes the role history entry of a save in the role editor. The definition is read before
 * the save (beforeSave) and after it (afterSave / afterCreate, once the page has synced the
 * permissions), inside the page's database transaction: when the entry cannot be written, the
 * save is rolled back.
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
     * Tells the modules, after the page's transaction commits, that the permissions of the role
     * changed. Call it after the authorization cache was invalidated, so a listener never reads
     * stale permissions. Nothing is dispatched when the set stayed the same.
     */
    private function announcePermissionChange(RoleModel $record): void
    {
        $fresh = $record->fresh() ?? $record;
        $before = $this->permissionKeysBeforeSave;
        $after = $this->permissionKeysOf($fresh);
        sort($before);
        sort($after);

        if ($before === $after) {
            return;
        }

        $event = new RolePermissionsChanged($fresh->id, $fresh->name, $before, $after);

        DB::afterCommit(static function () use ($event): void {
            Event::dispatch($event);
        });
    }

    /**
     * Tells the modules, after the page's transaction commits, which holders had the role turned
     * on or off because it gained or lost the «Requiere ser socio» requirement. Nothing is
     * dispatched when the requirement stayed the same.
     */
    private function announceRequirementChange(RoleModel $record): void
    {
        $fresh = $record->fresh() ?? $record;
        $before = (bool) ($this->fieldsBeforeSave['requires_association_member'] ?? false);
        $after = $fresh->requires_association_member;

        if ($before === $after) {
            return;
        }

        app(RoleChangeAnnouncer::class)->announceRequirementChange(
            $fresh->name,
            $after,
            app(AssociationMembershipServiceInterface::class)->roleName(),
        );
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
