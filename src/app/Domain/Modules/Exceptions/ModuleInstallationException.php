<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleInstallationException extends DomainException
{
    public static function invalidZip(): self
    {
        return new self(__('modules.errors.invalid_zip'));
    }

    public static function zipTooLarge(int $limitMb): self
    {
        return new self(__('modules.errors.zip_too_large', ['limit' => $limitMb]));
    }

    public static function manifestNotFound(): self
    {
        return new self(__('modules.errors.manifest_not_found'));
    }

    public static function invalidManifestJson(): self
    {
        return new self(__('modules.errors.invalid_manifest_json'));
    }

    public static function missingManifestField(string $field): self
    {
        return new self(__('modules.errors.missing_manifest_field', ['field' => $field]));
    }

    public static function moduleAlreadyExists(string $name): self
    {
        return new self(__('modules.errors.module_already_exists', ['name' => $name]));
    }

    public static function forbiddenModuleName(string $name): self
    {
        return new self(__('modules.errors.forbidden_module_name', ['name' => $name]));
    }

    public static function extractionFailed(string $error): self
    {
        return new self(__('modules.errors.installation_failed', ['error' => $error]));
    }

    public static function moduleNotInstalled(string $name): self
    {
        return new self(__('modules.errors.module_not_installed', ['name' => $name]));
    }

    public static function updateFailed(string $error): self
    {
        return new self(__('modules.errors.update_failed', ['error' => $error]));
    }
}
