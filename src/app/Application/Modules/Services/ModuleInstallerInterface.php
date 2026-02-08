<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use Illuminate\Http\UploadedFile;

interface ModuleInstallerInterface
{
    /**
     * Maximum ZIP file size in bytes (50MB).
     */
    public const int MAX_ZIP_SIZE = 50 * 1024 * 1024;

    /**
     * Forbidden module names that cannot be used.
     */
    public const array FORBIDDEN_NAMES = [
        'core',
        'app',
        'admin',
        'filament',
        'system',
        'modules',
    ];

    /**
     * Required fields in module.json.
     */
    public const array REQUIRED_MANIFEST_FIELDS = [
        'name',
        'version',
        'namespace',
        'provider',
    ];

    /**
     * Install a module from an uploaded ZIP file.
     *
     * @return ModuleManifestDTO The installed module's manifest
     *
     * @throws \App\Domain\Modules\Exceptions\ModuleInstallationException
     */
    public function installFromZip(UploadedFile $file): ModuleManifestDTO;

    /**
     * Update an existing module from an uploaded ZIP file.
     *
     * Creates a backup, replaces the module files, copies pre-built assets,
     * runs pending migrations and seeders, and updates the version in the DB.
     *
     * @return ModuleManifestDTO The updated module's manifest
     *
     * @throws \App\Domain\Modules\Exceptions\ModuleInstallationException
     */
    public function updateFromZip(UploadedFile $file): ModuleManifestDTO;

    /**
     * Check if a module with the given name exists in the database.
     */
    public function moduleExists(string $name): bool;

    /**
     * Extract and validate the manifest from a ZIP file without installing it.
     *
     * @throws \App\Domain\Modules\Exceptions\ModuleInstallationException
     */
    public function peekManifest(UploadedFile $file): ModuleManifestDTO;
}
