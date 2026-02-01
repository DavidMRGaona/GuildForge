<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Updates\Services\CoreVersionService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class CoreVersionServiceTest extends TestCase
{
    private CoreVersionService $service;

    private string $versionFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CoreVersionService();
        $this->versionFilePath = base_path('VERSION');
    }

    protected function tearDown(): void
    {
        // Restore original VERSION file if it existed
        if (File::exists($this->versionFilePath.'.backup')) {
            File::move($this->versionFilePath.'.backup', $this->versionFilePath);
        }

        parent::tearDown();
    }

    public function test_it_reads_version_from_file(): void
    {
        $this->backupAndWriteVersion('2.5.10');

        // Clear cached version by creating new instance
        $service = new CoreVersionService();
        $version = $service->getCurrentVersion();

        $this->assertEquals('2.5.10', $version->value());
        $this->assertEquals(2, $version->major);
        $this->assertEquals(5, $version->minor);
        $this->assertEquals(10, $version->patch);
    }

    public function test_it_returns_fallback_when_file_missing(): void
    {
        $this->backupVersionFile();
        File::delete($this->versionFilePath);

        $service = new CoreVersionService();
        $version = $service->getCurrentVersion();

        $this->assertEquals('0.0.0', $version->value());
    }

    public function test_it_caches_version_on_subsequent_calls(): void
    {
        $this->backupAndWriteVersion('1.0.0');

        $service = new CoreVersionService();
        $firstCall = $service->getCurrentVersion();

        // Modify the file (but cache should preserve original)
        File::put($this->versionFilePath, '9.9.9');

        $secondCall = $service->getCurrentVersion();

        $this->assertEquals('1.0.0', $firstCall->value());
        $this->assertEquals('1.0.0', $secondCall->value());
    }

    public function test_it_returns_current_git_commit(): void
    {
        $commit = $this->service->getCurrentCommit();

        // Should be either a valid SHA or 'unknown'
        if ($commit !== 'unknown') {
            // Git SHA is 40 hex characters
            $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $commit);
        } else {
            $this->assertEquals('unknown', $commit);
        }
    }

    public function test_satisfies_returns_true_for_matching_constraint(): void
    {
        $this->backupAndWriteVersion('1.5.3');

        $service = new CoreVersionService();

        $this->assertTrue($service->satisfies('^1.0'));
        $this->assertTrue($service->satisfies('>=1.0.0'));
        $this->assertTrue($service->satisfies('~1.5'));
    }

    public function test_satisfies_returns_false_for_non_matching_constraint(): void
    {
        $this->backupAndWriteVersion('1.5.3');

        $service = new CoreVersionService();

        $this->assertFalse($service->satisfies('^2.0'));
        $this->assertFalse($service->satisfies('>=2.0.0'));
        $this->assertFalse($service->satisfies('~2.0'));
    }

    public function test_service_is_registered_in_container(): void
    {
        $service = $this->app->make(CoreVersionServiceInterface::class);

        $this->assertInstanceOf(CoreVersionService::class, $service);
    }

    public function test_it_trims_whitespace_from_version_file(): void
    {
        $this->backupAndWriteVersion("  3.2.1  \n");

        $service = new CoreVersionService();
        $version = $service->getCurrentVersion();

        $this->assertEquals('3.2.1', $version->value());
    }

    private function backupVersionFile(): void
    {
        if (File::exists($this->versionFilePath)) {
            File::move($this->versionFilePath, $this->versionFilePath.'.backup');
        }
    }

    private function backupAndWriteVersion(string $version): void
    {
        $this->backupVersionFile();
        File::put($this->versionFilePath, $version);
    }
}
