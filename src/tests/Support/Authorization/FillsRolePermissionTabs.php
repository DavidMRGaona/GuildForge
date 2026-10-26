<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use App\Filament\Resources\RoleResource\PermissionCatalog;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;

/**
 * The role editor ticks permissions in one checkbox list per tab and section, and admin.access
 * through the panel access switch. These helpers turn a list of permission ids into that state.
 */
trait FillsRolePermissionTabs
{
    /**
     * Replaces every ticked permission of the role editor with the given ones.
     *
     * @param  Testable<Component>  $page
     * @param  array<int, mixed>  $permissionIds
     * @return Testable<Component>
     */
    protected function tickPermissions(Testable $page, array $permissionIds): Testable
    {
        $state = $this->permissionTabsState($permissionIds);

        return $page
            ->set('data.permissions_by_section', $state['permissions_by_section'])
            ->set('data.panel_access', $state['panel_access']);
    }

    /**
     * @param  array<int, mixed>  $permissionIds
     * @return array{permissions_by_section: array<string, array<string, list<string>>>, panel_access: bool}
     */
    protected function permissionTabsState(array $permissionIds): array
    {
        $ids = array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $permissionIds);
        $adminAccessId = PermissionModel::query()->where('key', 'admin.access')->value('id');
        $adminAccessId = is_scalar($adminAccessId) ? (string) $adminAccessId : null;

        $state = [];
        $placed = $adminAccessId !== null ? [$adminAccessId] : [];

        foreach (app(PermissionCatalog::class)->tabs() as $tab) {
            foreach ($tab->sections as $section) {
                $ticked = [];
                foreach ($section->permissions as $option) {
                    if (in_array($option->id, $ids, true)) {
                        $ticked[] = $option->id;
                    }
                    $placed[] = $option->id;
                }
                $state[$tab->key][$section->key] = $ticked;
            }
        }

        $unplaced = array_diff($ids, $placed);
        if ($unplaced !== []) {
            $this->fail('These permissions have no place in the role editor: '.implode(', ', $unplaced));
        }

        return [
            'permissions_by_section' => $state,
            'panel_access' => $adminAccessId !== null && in_array($adminAccessId, $ids, true),
        ];
    }
}
