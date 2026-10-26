<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Server-side check of the permissions submitted in the role editor: the ids the page will sync
 * (every tab, the panel access switch and what the form cannot change). It runs before saving
 * (beforeSave / beforeCreate) and halts: nothing is written when the check fails.
 */
trait GuardsRolePermissions
{
    /**
     * @param  list<string>  $roleNames  the current name and the submitted one
     * @param  list<string>  $currentIds  permission ids the role holds now
     * @param  list<string>  $submittedIds  permission ids the role would hold after saving
     */
    protected function guardSubmittedPermissions(array $roleNames, array $currentIds, array $submittedIds): void
    {
        $currentIds = array_map(strval(...), $currentIds);
        $submittedIds = array_values(array_unique(array_map(strval(...), $submittedIds)));

        $added = array_diff($submittedIds, $currentIds);
        $removed = array_diff($currentIds, $submittedIds);

        $keysById = $this->permissionKeysById([...$submittedIds, ...$currentIds]);

        // Fails closed: an id that is not a known permission is never "no change"
        if (array_any($submittedIds, static fn (string $id): bool => ! isset($keysById[$id]))) {
            $this->refuse((string) __('filament.roles.notifications.unknownPermission'));
        }

        $changedKeys = array_map(
            static fn (string $id): string => $keysById[$id] ?? $id,
            [...$added, ...$removed],
        );

        $editable = app(RoleGrantPolicy::class)->editablePermissionKeys($this->guardActor());

        if ($editable !== null && array_diff($changedKeys, $editable) !== []) {
            $this->refuse((string) __('filament.roles.notifications.permissionsBeyondYours'));
        }

        $submittedKeys = array_map(static fn (string $id): string => $keysById[$id] ?? $id, $submittedIds);

        if (in_array('admin.access', $submittedKeys, true)) {
            $association = app(AssociationMemberRoleRule::class);
            $base = app(BaseRoleRule::class);

            foreach ($roleNames as $roleName) {
                if ($association->isAssociationMemberRoleName($roleName) || $base->isBaseRoleName($roleName)) {
                    $this->refuse((string) __('filament.roles.notifications.associationRoleNoPanel'));
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<string, string>
     */
    private function permissionKeysById(array $ids): array
    {
        /** @var array<string, string> */
        return PermissionModel::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('key', 'id')
            ->all();
    }

    private function guardActor(): object
    {
        return auth()->user() ?? throw new RuntimeException('No signed-in user.');
    }

    private function refuse(string $message): void
    {
        Notification::make()
            ->danger()
            ->title($message)
            ->send();

        $this->halt();
    }
}
