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

        // A bad repository only disables updates for this module; it must not block discovery
        if (isset($data['repository']) && ! ModuleManifestDTO::isValidRepository($data['repository'])) {
            $this->logger->warning("Ignoring invalid repository in {$path}, expected 'owner/repo'");
            unset($data['repository']);
        }

        return ModuleManifestDTO::fromArray($data);
    }
}
