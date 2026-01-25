<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use InvalidArgumentException;

final readonly class ModuleDiscoveryService
{
    public function __construct(
        private string $modulesPath,
    ) {
    }

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
            if ($dir === '.' || $dir === '..') {
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

            $modules[] = $this->parseManifest($manifestPath);
        }

        return $modules;
    }

    private function parseManifest(string $path): ModuleManifestDTO
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("Cannot read manifest file: {$path}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        return ModuleManifestDTO::fromArray($data);
    }
}
