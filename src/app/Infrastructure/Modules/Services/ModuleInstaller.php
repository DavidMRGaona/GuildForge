<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Exceptions\ModuleInstallationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use ZipArchive;

final readonly class ModuleInstaller implements ModuleInstallerInterface
{
    private const string TEMP_DIRECTORY = 'temp/modules';

    public function __construct(
        private Dispatcher $events,
    ) {
    }

    public function installFromZip(UploadedFile $file): ModuleManifestDTO
    {
        $this->validateZipFile($file);

        $tempPath = $this->extractToTemp($file);

        try {
            $manifest = $this->findAndValidateManifest($tempPath);
            $this->validateModuleName($manifest);

            $targetPath = $this->moveToModulesDirectory($tempPath, $manifest->name);

            $this->events->dispatch(new ModuleInstalled(
                $manifest->name,
                $manifest->version,
                $targetPath,
            ));

            return $manifest;
        } finally {
            $this->cleanupTempDirectory($tempPath);
        }
    }

    private function validateZipFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_ZIP_SIZE) {
            throw ModuleInstallationException::zipTooLarge((int) (self::MAX_ZIP_SIZE / (1024 * 1024)));
        }

        // Validate by actually opening as ZIP (more reliable than extension check)
        $zip = new ZipArchive();
        $result = $zip->open($file->getPathname());

        if ($result !== true) {
            throw ModuleInstallationException::invalidZip();
        }

        $zip->close();
    }

    private function extractToTemp(UploadedFile $file): string
    {
        $tempId = uniqid('module_', true);
        $tempPath = storage_path('app/'.self::TEMP_DIRECTORY.'/'.$tempId);

        if (! File::makeDirectory($tempPath, 0755, true)) {
            throw ModuleInstallationException::extractionFailed('Failed to create temp directory');
        }

        $zip = new ZipArchive();
        $zip->open($file->getPathname());
        $zip->extractTo($tempPath);
        $zip->close();

        return $tempPath;
    }

    private function findAndValidateManifest(string $tempPath): ModuleManifestDTO
    {
        $manifestPath = $this->findManifestPath($tempPath);

        if ($manifestPath === null) {
            throw ModuleInstallationException::manifestNotFound();
        }

        $content = File::get($manifestPath);
        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (! is_array($data)) {
            throw ModuleInstallationException::invalidManifestJson();
        }

        foreach (self::REQUIRED_MANIFEST_FIELDS as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw ModuleInstallationException::missingManifestField($field);
            }
        }

        try {
            return ModuleManifestDTO::fromArray($data);
        } catch (InvalidArgumentException $e) {
            throw ModuleInstallationException::invalidManifestJson();
        }
    }

    private function findManifestPath(string $tempPath): ?string
    {
        $rootManifest = $tempPath.'/module.json';
        if (File::exists($rootManifest)) {
            return $rootManifest;
        }

        $directories = File::directories($tempPath);
        if (count($directories) === 1) {
            $subdirManifest = $directories[0].'/module.json';
            if (File::exists($subdirManifest)) {
                return $subdirManifest;
            }
        }

        return null;
    }

    private function validateModuleName(ModuleManifestDTO $manifest): void
    {
        $name = strtolower($manifest->name);

        if (in_array($name, self::FORBIDDEN_NAMES, true)) {
            throw ModuleInstallationException::forbiddenModuleName($manifest->name);
        }

        $modulesPath = config('modules.path', base_path('modules'));
        $targetPath = $modulesPath.'/'.$manifest->name;

        if (File::isDirectory($targetPath)) {
            throw ModuleInstallationException::moduleAlreadyExists($manifest->name);
        }
    }

    private function moveToModulesDirectory(string $tempPath, string $moduleName): string
    {
        $modulesPath = config('modules.path', base_path('modules'));
        $targetPath = $modulesPath.'/'.$moduleName;

        if (!File::isDirectory($modulesPath) && !File::makeDirectory($modulesPath, 0755, true)) {
            throw ModuleInstallationException::extractionFailed('Failed to create modules directory');
        }

        $sourcePath = $this->getSourcePath($tempPath);

        // Try rename first (fast, same-filesystem), fall back to copy+delete (cross-filesystem/Docker)
        if (! File::moveDirectory($sourcePath, $targetPath)) {
            if (! File::copyDirectory($sourcePath, $targetPath)) {
                throw ModuleInstallationException::extractionFailed('Failed to move module to target directory');
            }
            File::deleteDirectory($sourcePath);
        }

        return $targetPath;
    }

    private function getSourcePath(string $tempPath): string
    {
        if (File::exists($tempPath.'/module.json')) {
            return $tempPath;
        }

        $directories = File::directories($tempPath);
        if (count($directories) === 1 && File::exists($directories[0].'/module.json')) {
            return $directories[0];
        }

        return $tempPath;
    }

    private function cleanupTempDirectory(string $tempPath): void
    {
        if (File::isDirectory($tempPath)) {
            File::deleteDirectory($tempPath);
        }
    }
}
