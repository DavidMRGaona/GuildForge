<?php

declare(strict_types=1);

namespace App\Domain\Updates\Exceptions;

use Exception;

/**
 * Base exception for update-related errors.
 */
class UpdateException extends Exception
{
    public static function downloadFailed(string $moduleName, string $reason): self
    {
        return new self("Failed to download update for '{$moduleName}': {$reason}");
    }

    public static function checksumMismatch(string $moduleName): self
    {
        return new self("Checksum verification failed for '{$moduleName}'. The download may be corrupted.");
    }

    public static function checksumFetchFailed(string $moduleName): self
    {
        return new self("Failed to fetch checksum for '{$moduleName}'.");
    }

    public static function backupFailed(string $moduleName, string $reason): self
    {
        return new self("Failed to create backup for '{$moduleName}': {$reason}");
    }

    public static function extractionFailed(string $moduleName, string $reason): self
    {
        return new self("Failed to extract update for '{$moduleName}': {$reason}");
    }

    public static function migrationFailed(string $moduleName, string $reason): self
    {
        return new self("Migration failed for '{$moduleName}': {$reason}");
    }

    public static function healthCheckFailed(string $moduleName, string $reason): self
    {
        return new self("Health check failed for '{$moduleName}': {$reason}");
    }

    public static function rollbackFailed(string $moduleName, string $reason): self
    {
        return new self("Rollback failed for '{$moduleName}': {$reason}");
    }

    public static function coreIncompatible(string $moduleName, string $requiredCore, string $currentCore): self
    {
        return new self(
            "Module '{$moduleName}' requires core version {$requiredCore}, but current version is {$currentCore}."
        );
    }

    public static function lockAcquisitionFailed(string $moduleName): self
    {
        return new self("Could not acquire update lock for '{$moduleName}'. Another update may be in progress.");
    }

    public static function noDownloadableAssets(string $moduleName): self
    {
        return new self("Release for '{$moduleName}' has no downloadable assets.");
    }

    public static function noUpdateAvailable(string $moduleName): self
    {
        return new self("No update available for '{$moduleName}'.");
    }

    public static function noSourceConfigured(string $moduleName): self
    {
        return new self("Module '{$moduleName}' has no GitHub source configured.");
    }
}
