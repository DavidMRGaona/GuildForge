<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Infrastructure\Updates\Services\CoreVersionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CoreVersionServiceTest extends TestCase
{
    private CoreVersionService $service;

    private string $versionFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch src/VERSION: the parallel suite reads it to check module compatibility
        $this->versionFilePath = sys_get_temp_dir().'/gf-version-'.uniqid();
        $this->service = new CoreVersionService($this->versionFilePath);
    }

    protected function tearDown(): void
    {
        File::delete($this->versionFilePath);

        parent::tearDown();
    }

    public function test_it_reads_version_from_file(): void
    {
        $this->writeVersion('2.5.10');

        // Clear cached version by creating new instance
        $service = new CoreVersionService($this->versionFilePath);
        $version = $service->getCurrentVersion();

        $this->assertEquals('2.5.10', $version->value());
        $this->assertEquals(2, $version->major);
        $this->assertEquals(5, $version->minor);
        $this->assertEquals(10, $version->patch);
    }

    public function test_it_returns_fallback_when_file_missing(): void
    {
        $service = new CoreVersionService($this->versionFilePath);
        $version = $service->getCurrentVersion();

        $this->assertEquals('0.0.0', $version->value());
    }

    public function test_it_caches_version_on_subsequent_calls(): void
    {
        $this->writeVersion('1.0.0');

        $service = new CoreVersionService($this->versionFilePath);
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

    public function test_it_prefers_the_commit_injected_by_the_deployment(): void
    {
        // Coolify exposes the deployed SHA as SOURCE_COMMIT; the image has no .git
        config(['updates.core.commit' => 'abc123def4567890abc123def4567890abc12345']);

        $this->assertSame('abc123def4567890abc123def4567890abc12345', $this->service->getCurrentCommit());
    }

    public function test_satisfies_returns_true_for_matching_constraint(): void
    {
        $this->writeVersion('1.5.3');

        $service = new CoreVersionService($this->versionFilePath);

        $this->assertTrue($service->satisfies('^1.0'));
        $this->assertTrue($service->satisfies('>=1.0.0'));
        $this->assertTrue($service->satisfies('~1.5'));
    }

    public function test_satisfies_returns_false_for_non_matching_constraint(): void
    {
        $this->writeVersion('1.5.3');

        $service = new CoreVersionService($this->versionFilePath);

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
        $this->writeVersion("  3.2.1  \n");

        $service = new CoreVersionService($this->versionFilePath);
        $version = $service->getCurrentVersion();

        $this->assertEquals('3.2.1', $version->value());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedVersions(): array
    {
        return [
            'leading v' => ['v2.6.0'],
            'missing patch' => ['2.6'],
            'trailing garbage' => ["2.6.0 beta\n"],
        ];
    }

    #[DataProvider('malformedVersions')]
    public function test_a_malformed_version_file_falls_back_to_0_0_0_and_logs_once(string $content): void
    {
        Log::spy();
        $this->writeVersion($content);

        $service = new CoreVersionService($this->versionFilePath);

        $this->assertSame('0.0.0', $service->getCurrentVersion()->value());
        $this->assertSame('0.0.0', $service->getCurrentVersion()->value());
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'core version')
                && $context['file'] === $this->versionFilePath
                && $context['value'] === trim($content));
    }

    public function test_it_reads_the_application_version_file_by_default(): void
    {
        $this->assertSame(trim(File::get(base_path('VERSION'))), (new CoreVersionService())->getCurrentVersion()->value());
    }

    private function writeVersion(string $version): void
    {
        File::put($this->versionFilePath, $version);
    }
}
