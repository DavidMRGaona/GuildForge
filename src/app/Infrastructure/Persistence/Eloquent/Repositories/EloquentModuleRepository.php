<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
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
        );
    }

    /**
     * Normalize database requires format to ModuleRequirements format.
     *
     * Database format: ['modules' => [...], 'php' => '...']
     * ModuleRequirements format: ['required_modules' => [...], 'php_version' => '...']
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
            'status' => $module->status()->value,
            'enabled_at' => $module->enabledAt()?->format('Y-m-d H:i:s'),
            'installed_at' => $module->installedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
