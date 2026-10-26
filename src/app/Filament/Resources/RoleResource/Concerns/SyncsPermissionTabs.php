<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource\Concerns;

use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;

/**
 * The role editor ticks permissions in one checkbox list per tab and section
 * (`permissions_by_section.<tab>.<section>`) and admin.access through the `panel_access`
 * switch. None of them is bound to the relationship: the page joins them into one list of ids,
 * checks it and syncs the pivot itself.
 */
trait SyncsPermissionTabs
{
    private const string ADMIN_ACCESS = 'admin.access';

    /** @var list<string>|null ids to sync once the role is saved, set by the check before saving */
    private ?array $permissionIdsToSync = null;

    /**
     * Initial state of the tabs and of the panel access switch for the role.
     *
     * @return array{permissions_by_section: array<string, array<string, list<string>>>, panel_access: bool}
     */
    protected function fillPermissionTabs(RoleModel $role): array
    {
        $held = $this->currentPermissionIds($role);
        $state = [];

        foreach (app(PermissionCatalog::class)->tabs() as $tab) {
            foreach ($tab->sections as $section) {
                $state[$tab->key][$section->key] = array_values(array_filter(
                    array_map(static fn ($option): string => $option->id, $section->permissions),
                    static fn (string $id): bool => in_array($id, $held, true),
                ));
            }
        }

        $adminAccessId = $this->adminAccessId();

        return [
            'permissions_by_section' => $state,
            'panel_access' => $adminAccessId !== null && in_array($adminAccessId, $held, true),
        ];
    }

    /**
     * Every permission id the role ends up with: the ticked boxes of every tab, admin.access when
     * the switch is on, and what the role holds that the form cannot change. That is, the
     * permissions shown in no tab (disabled modules, permissions no definition knows) and those
     * the actor may not edit, whose disabled boxes are never sent back by the browser.
     *
     * @param  array<string, mixed>  $data  the form state
     * @return list<string>
     */
    protected function collectPermissionIds(array $data, ?RoleModel $role): array
    {
        $ids = [];

        $bySection = $data['permissions_by_section'] ?? [];
        foreach (is_array($bySection) ? $bySection : [] as $sections) {
            foreach (is_array($sections) ? $sections : [] as $ticked) {
                foreach (is_array($ticked) ? $ticked : [] as $id) {
                    if (is_scalar($id) && (string) $id !== '') {
                        $ids[] = (string) $id;
                    }
                }
            }
        }

        $adminAccessId = $this->adminAccessId();
        if ($adminAccessId !== null && (bool) ($data['panel_access'] ?? false)) {
            $ids[] = $adminAccessId;
        }

        if ($role instanceof RoleModel) {
            $ids = [...$ids, ...$this->permissionIdsOutsideTheForm($role, $adminAccessId)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Called by the check before saving, so the ids that were checked are the ones synced.
     *
     * @param  list<string>  $ids
     */
    protected function rememberPermissionIdsToSync(array $ids): void
    {
        $this->permissionIdsToSync = $ids;
    }

    protected function syncPermissionTabs(RoleModel $role): void
    {
        // Nothing checked, nothing written
        if ($this->permissionIdsToSync === null) {
            return;
        }

        $role->permissions()->sync($this->permissionIdsToSync);
    }

    /**
     * @return list<string>
     */
    protected function currentPermissionIds(RoleModel $role): array
    {
        return array_values($role->permissions()->pluck('permissions.id')->map(strval(...))->all());
    }

    /**
     * @return list<string>
     */
    private function permissionIdsOutsideTheForm(RoleModel $role, ?string $adminAccessId): array
    {
        $shown = [];
        foreach (app(PermissionCatalog::class)->tabs() as $tab) {
            foreach ($tab->sections as $section) {
                foreach ($section->permissions as $option) {
                    $shown[$option->id] = $option->key;
                }
            }
        }
        if ($adminAccessId !== null) {
            $shown[$adminAccessId] = self::ADMIN_ACCESS;
        }

        $actor = auth()->user();
        // Without a signed-in actor nothing may be edited: everything the role holds stays
        $editable = $actor !== null ? app(RoleGrantPolicy::class)->editablePermissionKeys($actor) : [];

        return array_values(array_filter(
            $this->currentPermissionIds($role),
            static fn (string $id): bool => ! isset($shown[$id])
                || ($editable !== null && ! in_array($shown[$id], $editable, true)),
        ));
    }

    private function adminAccessId(): ?string
    {
        $id = PermissionModel::query()->where('key', self::ADMIN_ACCESS)->value('id');

        return is_scalar($id) ? (string) $id : null;
    }
}
