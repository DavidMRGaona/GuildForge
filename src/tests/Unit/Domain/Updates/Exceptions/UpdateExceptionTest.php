<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Updates\Exceptions;

use App\Domain\Updates\Exceptions\UpdateException;
use PHPUnit\Framework\TestCase;

final class UpdateExceptionTest extends TestCase
{
    public function test_download_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::downloadFailed('forum', 'Connection timeout');

        $this->assertStringContainsString('forum', $exception->getMessage());
        $this->assertStringContainsString('Connection timeout', $exception->getMessage());
        $this->assertStringContainsString('Failed to download', $exception->getMessage());
    }

    public function test_checksum_mismatch_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::checksumMismatch('shop');

        $this->assertStringContainsString('shop', $exception->getMessage());
        $this->assertStringContainsString('Checksum verification failed', $exception->getMessage());
        $this->assertStringContainsString('corrupted', $exception->getMessage());
    }

    public function test_checksum_fetch_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::checksumFetchFailed('gallery');

        $this->assertStringContainsString('gallery', $exception->getMessage());
        $this->assertStringContainsString('Failed to fetch checksum', $exception->getMessage());
    }

    public function test_backup_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::backupFailed('events', 'Disk full');

        $this->assertStringContainsString('events', $exception->getMessage());
        $this->assertStringContainsString('Disk full', $exception->getMessage());
        $this->assertStringContainsString('Failed to create backup', $exception->getMessage());
    }

    public function test_extraction_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::extractionFailed('members', 'Invalid ZIP format');

        $this->assertStringContainsString('members', $exception->getMessage());
        $this->assertStringContainsString('Invalid ZIP format', $exception->getMessage());
        $this->assertStringContainsString('Failed to extract', $exception->getMessage());
    }

    public function test_migration_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::migrationFailed('inventory', 'Foreign key constraint');

        $this->assertStringContainsString('inventory', $exception->getMessage());
        $this->assertStringContainsString('Foreign key constraint', $exception->getMessage());
        $this->assertStringContainsString('Migration failed', $exception->getMessage());
    }

    public function test_health_check_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::healthCheckFailed('payments', 'Provider not loading');

        $this->assertStringContainsString('payments', $exception->getMessage());
        $this->assertStringContainsString('Provider not loading', $exception->getMessage());
        $this->assertStringContainsString('Health check failed', $exception->getMessage());
    }

    public function test_rollback_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::rollbackFailed('notifications', 'Backup file missing');

        $this->assertStringContainsString('notifications', $exception->getMessage());
        $this->assertStringContainsString('Backup file missing', $exception->getMessage());
        $this->assertStringContainsString('Rollback failed', $exception->getMessage());
    }

    public function test_core_incompatible_creates_exception_with_version_details(): void
    {
        $exception = UpdateException::coreIncompatible('forum', '^2.0', '1.5.0');

        $this->assertStringContainsString('forum', $exception->getMessage());
        $this->assertStringContainsString('^2.0', $exception->getMessage());
        $this->assertStringContainsString('1.5.0', $exception->getMessage());
        $this->assertStringContainsString('requires core version', $exception->getMessage());
    }

    public function test_lock_acquisition_failed_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::lockAcquisitionFailed('calendar');

        $this->assertStringContainsString('calendar', $exception->getMessage());
        $this->assertStringContainsString('Could not acquire update lock', $exception->getMessage());
        $this->assertStringContainsString('Another update may be in progress', $exception->getMessage());
    }

    public function test_no_downloadable_assets_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::noDownloadableAssets('reporting');

        $this->assertStringContainsString('reporting', $exception->getMessage());
        $this->assertStringContainsString('no downloadable assets', $exception->getMessage());
    }

    public function test_no_update_available_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::noUpdateAvailable('analytics');

        $this->assertStringContainsString('analytics', $exception->getMessage());
        $this->assertStringContainsString('No update available', $exception->getMessage());
    }

    public function test_no_source_configured_creates_exception_with_correct_message(): void
    {
        $exception = UpdateException::noSourceConfigured('custom-module');

        $this->assertStringContainsString('custom-module', $exception->getMessage());
        $this->assertStringContainsString('no GitHub source configured', $exception->getMessage());
    }

    public function test_exception_extends_domain_exception(): void
    {
        $exception = UpdateException::downloadFailed('test', 'error');

        $this->assertInstanceOf(\DomainException::class, $exception);
    }
}
