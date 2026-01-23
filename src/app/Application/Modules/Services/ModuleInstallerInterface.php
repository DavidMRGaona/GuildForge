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
     * @throws \App\Domain\Modules\Exceptions\ModuleInstallationException
     * @return ModuleManifestDTO The installed module's manifest
     */
    public function installFromZip(UploadedFile $file): ModuleManifestDTO;
}
