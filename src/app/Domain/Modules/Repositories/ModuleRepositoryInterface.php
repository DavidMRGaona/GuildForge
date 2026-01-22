<?php

declare(strict_types=1);

namespace App\Domain\Modules\Repositories;

use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;

interface ModuleRepositoryInterface
{
    public function findById(ModuleId $id): ?Module;

    public function findByName(ModuleName $name): ?Module;

    public function all(): ModuleCollection;

    public function enabled(): ModuleCollection;

    public function disabled(): ModuleCollection;

    public function save(Module $module): void;

    public function delete(Module $module): void;

    public function exists(ModuleName $name): bool;
}
