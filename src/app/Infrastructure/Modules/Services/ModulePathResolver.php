<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Where a module's files are: its stored path, else the modules directory.
 *
 * The stored path goes stale when the site moves to another folder, a backup is restored
 * elsewhere or a container mounts the modules at another point; module:discover (run on
 * every container start) stores the new one. A module found nowhere is already rejected
 * by the compatibility check (module.json missing) and shown on the panel banner.
 */
final readonly class ModulePathResolver
{
    /**
     * @param  string|null  $modulesPath  The modules directory; null reads config('modules.path')
     */
    public function __construct(
        private ?string $modulesPath = null,
    ) {}

    /**
     * @throws ModuleNotFoundException
     */
    public function resolve(Module $module): string
    {
        $storedPath = $module->path();

        if (is_dir($storedPath)) {
            return $storedPath;
        }

        $defaultPath = $this->defaultPath($module->name()->value);

        if (! is_dir($defaultPath)) {
            throw ModuleNotFoundException::withName($module->name()->value);
        }

        if ($this->firstReportToday($module->name()->value, $storedPath)) {
            Log::warning("Stored path of module {$module->name()->value} does not exist, using the modules directory", [
                'stored_path' => $storedPath,
                'path' => $defaultPath,
            ]);
        }

        return $defaultPath;
    }

    public function defaultPath(string $moduleName): string
    {
        $modulesPath = $this->modulesPath ?? (string) config('modules.path', base_path('modules'));

        return rtrim($modulesPath, '/').'/'.$moduleName;
    }

    /**
     * The loader resolves every enabled module on every request: without a shared marker
     * a stale path would be logged once per request.
     */
    private function firstReportToday(string $module, string $storedPath): bool
    {
        try {
            return Cache::add('modules.path.fallback_logged.'.$module.'.'.md5($storedPath), true, now()->addDay());
        } catch (Throwable) {
            return true;
        }
    }
}
