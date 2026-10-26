<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Illuminate\Support\Str;

/**
 * Read-only catalog of the permissions the role editor offers, as tabs of sections of options.
 *
 * Core permissions go to fixed tabs; each enabled module gets its own tab. `admin.access` is
 * left out (the editor shows it as a switch), as are permissions of disabled or unknown modules
 * and permissions missing from the `permissions` table (they cannot be ticked).
 */
final readonly class PermissionCatalog
{
    private const string ADMIN_ACCESS = 'admin.access';

    /** @var array<string, list<string>> core tab key => resources, in display order */
    private const array CORE_TABS = [
        'content' => ['events', 'articles', 'galleries', 'hero_slides', 'tags', 'menu_items', 'pages'],
        'users' => ['users', 'roles'],
        'site' => ['settings', 'updates', 'mail', 'modules', 'dashboard'],
    ];

    public function __construct(
        private PermissionRegistryInterface $registry,
        private ModuleManagerServiceInterface $modules,
    ) {}

    /**
     * @return list<PermissionTab>
     */
    public function tabs(): array
    {
        $ids = PermissionModel::query()->pluck('id', 'key')->all();

        /** @var array<string, list<PermissionOption>> $core */
        $core = [];
        /** @var array<string, list<array{0: PermissionDefinitionDTO, 1: PermissionOption}>> $byPrefix */
        $byPrefix = [];

        foreach ($this->registry->all() as $definition) {
            if ($definition->key === self::ADMIN_ACCESS || ! isset($ids[$definition->key])) {
                continue;
            }

            $option = new PermissionOption(
                id: (string) $ids[$definition->key],
                key: $definition->key,
                label: $definition->label,
                description: $definition->description,
                suggestedFor: array_values($definition->defaultRoles),
            );

            if ($definition->module === null) {
                $core[$definition->resource][] = $option;
            } else {
                $byPrefix[$definition->module][] = [$definition, $option];
            }
        }

        return [...$this->coreTabs($core), ...$this->moduleTabs($byPrefix)];
    }

    /**
     * @param  array<string, list<PermissionOption>>  $byResource
     * @return list<PermissionTab>
     */
    private function coreTabs(array $byResource): array
    {
        $known = array_merge(...array_values(self::CORE_TABS));
        $tabs = [];

        foreach (self::CORE_TABS as $tabKey => $resources) {
            if ($tabKey === 'site') {
                // Core resources without a tab of their own end up here, so none goes unseen.
                $resources = [...$resources, ...array_values(array_diff(array_keys($byResource), $known))];
            }

            $sections = [];
            foreach ($resources as $resource) {
                if (isset($byResource[$resource])) {
                    $sections[] = new PermissionSection($resource, $this->resourceLabel($resource), $byResource[$resource]);
                }
            }

            $tabs[] = new PermissionTab($tabKey, __('authorization.tabs.'.$tabKey), $sections);
        }

        return $tabs;
    }

    /**
     * @param  array<string, list<array{0: PermissionDefinitionDTO, 1: PermissionOption}>>  $byPrefix
     * @return list<PermissionTab>
     */
    private function moduleTabs(array $byPrefix): array
    {
        $tabs = [];

        foreach ($this->modules->enabled()->all() as $module) {
            $prefix = $this->matchingPrefix($module, array_map(strval(...), array_keys($byPrefix)));
            if ($prefix === null) {
                continue;
            }

            /** @var array<string, list<PermissionOption>> $grouped */
            $grouped = [];
            /** @var array<string, string> $firstResource */
            $firstResource = [];
            foreach ($byPrefix[$prefix] as [$definition, $option]) {
                $label = $definition->group ?? $definition->resource;
                $grouped[$label][] = $option;
                $firstResource[$label] ??= $definition->resource;
            }

            // Keys come from untranslated data (the first resource), never from the label.
            $sections = [];
            $used = [];
            foreach ($grouped as $label => $options) {
                $label = (string) $label;
                $base = Str::slug($firstResource[$label]);
                if ($base === '') {
                    $base = 'section-'.(count($sections) + 1);
                }
                $key = $base;
                for ($n = 2; isset($used[$key]); $n++) {
                    $key = $base.'-'.$n;
                }
                $used[$key] = true;
                $sections[] = new PermissionSection($key, $label, $options);
            }

            $tabs[] = new PermissionTab('module-'.$prefix, $module->displayName(), $sections);
        }

        usort($tabs, fn (PermissionTab $a, PermissionTab $b): int => strcasecmp(Str::ascii($a->label), Str::ascii($b->label)));

        return $tabs;
    }

    /**
     * A module's permission prefix is its name, the name without hyphens or with underscores.
     *
     * @param  list<string>  $prefixes
     */
    private function matchingPrefix(Module $module, array $prefixes): ?string
    {
        $name = $module->name()->value;

        foreach ([$name, str_replace('-', '', $name), str_replace('-', '_', $name)] as $candidate) {
            if (in_array($candidate, $prefixes, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resourceLabel(string $resource): string
    {
        $key = 'authorization.resources.'.$resource;
        $label = __($key);

        return $label === $key ? $resource : $label;
    }
}
