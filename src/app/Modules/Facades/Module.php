<?php

declare(strict_types=1);

namespace App\Modules\Facades;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Facade;

/**
 * Module Facade for easy access to the ModuleContextService.
 *
 * @method static string|null current()
 * @method static void setCurrent(string $moduleName)
 * @method static void clearCurrent()
 * @method static mixed config(string $key, mixed $default = null)
 * @method static string path(string $path = '')
 * @method static string asset(string $path)
 * @method static string route(string $name, array $parameters = [])
 * @method static string trans(string $key, array $replace = [])
 * @method static View view(string $name, array $data = [])
 * @method static bool isEnabled(string $moduleName)
 * @method static array getEnabled()
 * @method static mixed moduleConfig(string $moduleName, string $key, mixed $default = null)
 * @method static string modulePath(string $moduleName, string $path = '')
 *
 * @see \App\Application\Modules\Services\ModuleContextServiceInterface
 */
final class Module extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleContextServiceInterface::class;
    }
}
