<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ModuleDiscoveryService
{
    public function __construct(
        private string $modulesPath,
        private LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * Discovers modules from the modules directory.
     *
     * @return array<ModuleManifestDTO>
     */
    public function discover(): array
    {
        if (! is_dir($this->modulesPath)) {
            return [];
        }

        $modules = [];
        $directories = scandir($this->modulesPath);

        if ($directories === false) {
            return [];
        }

        foreach ($directories as $dir) {
            // Dot directories are the updater's staging/previous copies, never modules
            if (str_starts_with($dir, '.')) {
                continue;
            }

            $modulePath = $this->modulesPath.'/'.$dir;
            if (! is_dir($modulePath)) {
                continue;
            }

            $manifestPath = $modulePath.'/module.json';
            if (! file_exists($manifestPath)) {
                continue;
            }

            $modules[] = $this->readManifest($manifestPath);
        }

        return $modules;
    }

    /**
     * @throws InvalidArgumentException When the manifest cannot be read or lacks required fields
     */
    public function readManifest(string $path): ModuleManifestDTO
    {
        return (new ModuleManifestReader($this->logger))->read($path);
    }
}
