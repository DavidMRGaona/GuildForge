<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use Composer\InstalledVersions;

final class HostEnvironmentProvider implements HostEnvironmentProviderInterface
{
    private ?HostEnvironment $current = null;

    public function __construct(
        private readonly CoreVersionServiceInterface $coreVersion,
    ) {
    }

    public function current(): HostEnvironment
    {
        return $this->current ??= new HostEnvironment(
            core: $this->coreVersion->getCurrentVersion(),
            php: new ModuleVersion(PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION),
            laravel: self::normalizeVersion(app()->version()) ?? new ModuleVersion(0, 0, 0),
            filament: self::packageVersion('filament/filament'),
            extensions: array_map('strtolower', get_loaded_extensions()),
        );
    }

    /**
     * "v3.3.56" → 3.3.56; null for anything without a numeric X.Y.Z prefix ("dev-main").
     */
    public static function normalizeVersion(?string $pretty): ?ModuleVersion
    {
        if ($pretty === null || preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', $pretty, $matches) !== 1) {
            return null;
        }

        return new ModuleVersion((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    private static function packageVersion(string $package): ?ModuleVersion
    {
        try {
            // getPrettyVersion() throws for packages that are not installed
            return InstalledVersions::isInstalled($package)
                ? self::normalizeVersion(InstalledVersions::getPrettyVersion($package))
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
