<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Pages;

use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Server-side check of the permissions submitted in the role editor. The checkbox list syncs
 * the pivot by itself right after saving, so this runs before that (beforeSave / beforeCreate)
 * and halts: nothing is written when the check fails.
 */
trait GuardsRolePermissions
{
    /**
     * @param  list<string>  $currentIds  permission ids the role holds now
     */
    protected function guardSubmittedPermissions(string $roleName, array $currentIds): void
    {
        $submittedIds = $this->submittedPermissionIds();

        $added = array_diff($submittedIds, $currentIds);
        $removed = array_diff($currentIds, $submittedIds);

        $keysById = $this->permissionKeysById([...$added, ...$removed, ...$submittedIds]);

        $changedKeys = array_values(array_filter(array_map(
            static fn (string $id): ?string => $keysById[$id] ?? null,
            [...$added, ...$removed],
        )));

        $editable = app(RoleGrantPolicy::class)->editablePermissionKeys($this->guardActor());

        if ($editable !== null && array_diff($changedKeys, $editable) !== []) {
            $this->refuse((string) __('filament.roles.notifications.permissionsBeyondYours'));
        }

        $submittedKeys = array_map(static fn (string $id): ?string => $keysById[$id] ?? null, $submittedIds);

        if (in_array('admin.access', $submittedKeys, true)
            && app(AssociationMemberRoleRule::class)->isAssociationMemberRoleName($roleName)) {
            $this->refuse((string) __('filament.roles.notifications.associationRoleNoPanel'));
        }
    }

    /**
     * @return list<string>
     */
    private function submittedPermissionIds(): array
    {
        $raw = $this->data['permissions'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (mixed $id): string => is_scalar($id) ? (string) $id : '',
            $raw,
        )));
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
