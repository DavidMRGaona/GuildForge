<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use DateTimeImmutable;

final readonly class EloquentModuleRepository implements ModuleRepositoryInterface
{
    public function findById(ModuleId $id): ?Module
    {
        $model = ModuleModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByName(ModuleName $name): ?Module
    {
        $model = ModuleModel::query()->where('name', $name->value)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function all(): ModuleCollection
    {
        $models = ModuleModel::query()->get();
        $modules = $models->map(fn (ModuleModel $m): Module => $this->toDomain($m))->all();

        return new ModuleCollection(...$modules);
    }

    public function enabled(): ModuleCollection
    {
        $models = ModuleModel::query()->where('status', ModuleStatus::Enabled->value)->get();
        $modules = $models->map(fn (ModuleModel $m): Module => $this->toDomain($m))->all();

        return new ModuleCollection(...$modules);
    }

    public function disabled(): ModuleCollection
    {
        $models = ModuleModel::query()->where('status', ModuleStatus::Disabled->value)->get();
        $modules = $models->map(fn (ModuleModel $m): Module => $this->toDomain($m))->all();

        return new ModuleCollection(...$modules);
    }

    public function save(Module $module): void
    {
        ModuleModel::query()->updateOrCreate(
            ['id' => $module->id()->value],
            $this->toArray($module),
        );
    }

    public function delete(Module $module): void
    {
        ModuleModel::query()->where('id', $module->id()->value)->delete();
    }

    public function exists(ModuleName $name): bool
    {
        return ModuleModel::query()->where('name', $name->value)->exists();
    }

    private function toDomain(ModuleModel $model): Module
    {
        return new Module(
            id: new ModuleId($model->id),
            name: new ModuleName($model->name),
            displayName: $model->display_name ?? '',
            description: $model->description ?? '',
            version: ModuleVersion::fromString($model->version),
            author: $model->author ?? '',
            requirements: ModuleRequirements::fromArray($this->normalizeRequirements($model->requires)),
            status: ModuleStatus::from($model->status),
            enabledAt: $model->enabled_at !== null
                ? new DateTimeImmutable($model->enabled_at->toDateTimeString())
                : null,
            installedAt: $model->installed_at !== null
                ? new DateTimeImmutable($model->installed_at->toDateTimeString())
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
            namespace: $model->namespace,
            provider: $model->provider,
            path: $model->path,
            dependencies: $model->dependencies ?? [],
            sourceOwner: $model->source_owner,
            sourceRepo: $model->source_repo,
            latestAvailableVersion: $model->latest_available_version,
            lastUpdateCheckAt: $model->last_update_check_at !== null
                ? new DateTimeImmutable($model->last_update_check_at->toDateTimeString())
                : null,
            latestBlockedVersion: $model->latest_blocked_version,
            latestBlockedIssues: $this->blockedIssues($model->latest_blocked_reason),
        );
    }

    /**
     * Stored JSON is untrusted: anything but a list of issue objects reads as no issues.
     *
     * @return list<CompatibilityIssue>
     */
    private function blockedIssues(mixed $stored): array
    {
        if (! is_array($stored)) {
            return [];
        }

        $issues = [];

        foreach ($stored as $issue) {
            if (is_array($issue)) {
                $issues[] = CompatibilityIssue::fromArray($issue);
            }
        }

        return $issues;
    }

    /**
     * Normalize the stored requires column to the ModuleRequirements::fromArray() format.
     *
     * Two shapes are accepted, the manifest keys winning when both are present:
     * - Manifest keys: ['php' => ..., 'laravel' => ..., 'core' => ..., 'filament' => ..., 'modules' => [...], 'extensions' => [...]]
     * - Database keys (what save() writes): ['php_version' => ..., 'laravel_version' => ..., 'core_version' => ...,
     *   'filament_version' => ..., 'required_modules' => [...], 'required_extensions' => [...]]
     * Rows saved before core/filament existed lack those keys and read as null (core falls back to ^2.0).
     *
     * @param  array<string, mixed>|null  $requires
     * @return array<string, mixed>
     */
    private function normalizeRequirements(?array $requires): array
    {
        if ($requires === null) {
            return [];
        }

        return [
            'php_version' => $requires['php'] ?? $requires['php_version'] ?? null,
            'laravel_version' => $requires['laravel'] ?? $requires['laravel_version'] ?? null,
            'core_version' => $requires['core'] ?? $requires['core_version'] ?? null,
            'filament_version' => $requires['filament'] ?? $requires['filament_version'] ?? null,
            'required_modules' => $requires['modules'] ?? $requires['required_modules'] ?? [],
            'required_extensions' => $requires['extensions'] ?? $requires['required_extensions'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Module $module): array
    {
        return [
            'id' => $module->id()->value,
            'name' => $module->name()->value,
            'display_name' => $module->displayName(),
            'version' => $module->version()->value(),
            'description' => $module->description(),
            'author' => $module->author(),
            'namespace' => $module->namespace(),
            'provider' => $module->provider(),
            'path' => $module->path(),
            'requires' => $module->requirements()->toArray(),
            'dependencies' => $module->dependencies(),
            'source_owner' => $module->sourceOwner(),
            'source_repo' => $module->sourceRepo(),
            'latest_available_version' => $module->latestAvailableVersion(),
            'last_update_check_at' => $module->lastUpdateCheckAt()?->format('Y-m-d H:i:s'),
            'latest_blocked_version' => $module->latestBlockedVersion(),
            'latest_blocked_reason' => $module->latestBlockedVersion() === null
                ? null
                : array_map(static fn (CompatibilityIssue $issue): array => $issue->toArray(), $module->latestBlockedIssues()),
            'status' => $module->status()->value,
            'enabled_at' => $module->enabledAt()?->format('Y-m-d H:i:s'),
            'installed_at' => $module->installedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
