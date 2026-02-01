<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Infrastructure\Updates\Services\ModuleUpdateChecker;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ModuleUpdateCheckerTest extends TestCase
{
    private MockInterface&ModuleRepositoryInterface $moduleRepository;

    private MockInterface&GitHubReleaseFetcherInterface $githubFetcher;

    private ModuleUpdateChecker $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleRepository = Mockery::mock(ModuleRepositoryInterface::class);
        $this->githubFetcher = Mockery::mock(GitHubReleaseFetcherInterface::class);

        $this->service = new ModuleUpdateChecker(
            $this->moduleRepository,
            $this->githubFetcher
        );

        config(['updates.behavior.allow_prereleases' => false]);
        config(['updates.batch_check' => true]);
    }

    public function test_it_detects_available_update(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', 'owner', 'forum-module');
        $release = $this->createReleaseInfo('1.2.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'forum'))
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('owner', 'forum-module')
            ->andReturn($release);

        $this->moduleRepository->shouldReceive('save')
            ->with(Mockery::on(fn ($arg) => $arg instanceof Module))
            ->once();

        $result = $this->service->checkForUpdate(ModuleName::fromString('forum'));

        $this->assertNotNull($result);
        $this->assertEquals('forum', $result->moduleName);
        $this->assertEquals('1.0.0', $result->currentVersion);
        $this->assertEquals('1.2.0', $result->availableVersion);
    }

    public function test_it_returns_null_when_module_not_found(): void
    {
        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn(null);

        $result = $this->service->checkForUpdate(ModuleName::fromString('nonexistent'));

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_module_has_no_source(): void
    {
        $module = $this->createRealModuleWithoutSource('local');

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $result = $this->service->checkForUpdate(ModuleName::fromString('local'));

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_no_release_found(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', 'owner', 'repo');

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn(null);

        $result = $this->service->checkForUpdate(ModuleName::fromString('forum'));

        $this->assertNull($result);
    }

    public function test_it_returns_null_when_already_up_to_date(): void
    {
        $module = $this->createRealModule('forum', '2.0.0', 'owner', 'repo');
        $release = $this->createReleaseInfo('1.5.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

        $result = $this->service->checkForUpdate(ModuleName::fromString('forum'));

        $this->assertNull($result);
    }

    public function test_it_skips_prereleases_by_default(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', 'owner', 'repo');
        $release = $this->createReleaseInfo('2.0.0', true); // prerelease

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

        $result = $this->service->checkForUpdate(ModuleName::fromString('forum'));

        $this->assertNull($result);
    }

    public function test_it_includes_prereleases_when_configured(): void
    {
        // Set the config before creating the service
        config()->set('updates.behavior.allow_prereleases', true);

        // Create a fresh service instance to pick up the new config
        $service = new ModuleUpdateChecker(
            $this->moduleRepository,
            $this->githubFetcher
        );

        $module = $this->createRealModule('forumpr', '1.0.0', 'owner', 'repo');
        $release = $this->createReleaseInfo('2.0.0', true);

        $this->moduleRepository->shouldReceive('findByName')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'forumpr'))
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

        $this->moduleRepository->shouldReceive('save')->once();

        $result = $service->checkForUpdate(ModuleName::fromString('forumpr'));

        $this->assertNotNull($result);
        $this->assertTrue($result->isPrerelease);
    }

    public function test_it_identifies_major_updates(): void
    {
        $module = $this->createRealModule('forum', '1.5.0', 'owner', 'repo');
        $release = $this->createReleaseInfo('2.0.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn($release);

        $this->moduleRepository->shouldReceive('save')->once();

        $result = $this->service->checkForUpdate(ModuleName::fromString('forum'));

        $this->assertNotNull($result);
        $this->assertTrue($result->isMajorUpdate);
    }

    public function test_check_all_for_updates_returns_collection(): void
    {
        $module1 = $this->createRealModule('forumall', '1.0.0', 'owner', 'forumall');
        $module2 = $this->createRealModuleWithoutSource('localall');

        $moduleCollection = new ModuleCollection($module1, $module2);

        $this->moduleRepository->shouldReceive('all')
            ->andReturn($moduleCollection);

        $release = $this->createReleaseInfo('1.5.0', false);

        $this->githubFetcher->shouldReceive('batchFetchLatestReleases')
            ->with([['owner' => 'owner', 'repo' => 'forumall']])
            ->andReturn(['owner/forumall' => $release]);

        $this->moduleRepository->shouldReceive('save')->once();

        $results = $this->service->checkAllForUpdates();

        $this->assertCount(1, $results);
        $this->assertEquals('forumall', $results->first()->moduleName);
    }

    public function test_check_all_returns_empty_when_all_up_to_date(): void
    {
        $moduleCollection = new ModuleCollection();

        $this->moduleRepository->shouldReceive('all')
            ->andReturn($moduleCollection);

        $results = $this->service->checkAllForUpdates();

        $this->assertCount(0, $results);
    }

    public function test_force_check_clears_cache(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', 'owner', 'repo');

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('clearCache')
            ->with('owner', 'repo')
            ->once();

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->andReturn(null);

        $this->service->forceCheck(ModuleName::fromString('forum'));
    }

    public function test_get_last_check_time(): void
    {
        $lastCheck = new DateTimeImmutable('2024-01-15 10:30:00');
        $module = $this->createRealModuleWithLastCheck('forum', $lastCheck);

        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn($module);

        $result = $this->service->getLastCheckTime(ModuleName::fromString('forum'));

        $this->assertEquals($lastCheck, $result);
    }

    public function test_get_last_check_time_returns_null_when_module_not_found(): void
    {
        $this->moduleRepository->shouldReceive('findByName')
            ->andReturn(null);

        $result = $this->service->getLastCheckTime(ModuleName::fromString('nonexistent'));

        $this->assertNull($result);
    }

    private function createRealModule(
        string $name,
        string $version,
        string $owner,
        string $repo
    ): Module {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
            description: 'Test module',
            version: ModuleVersion::fromString($version),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            sourceOwner: $owner,
            sourceRepo: $repo,
        );
    }

    private function createRealModuleWithoutSource(string $name): Module
    {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
            description: 'Test module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
        );
    }

    private function createRealModuleWithLastCheck(string $name, DateTimeImmutable $lastCheck): Module
    {
        return new Module(
            id: new ModuleId(Str::uuid()->toString()),
            name: ModuleName::fromString($name),
            displayName: ucfirst($name) . ' Module',
            description: 'Test module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
            lastUpdateCheckAt: $lastCheck,
        );
    }

    private function createReleaseInfo(string $version, bool $isPrerelease): GitHubReleaseInfo
    {
        return new GitHubReleaseInfo(
            tagName: "v{$version}",
            version: ModuleVersion::fromString($version),
            downloadUrl: "https://github.com/owner/repo/releases/download/v{$version}/module.zip",
            checksumUrl: '',
            releaseNotes: 'Test release',
            publishedAt: new DateTimeImmutable(),
            isPrerelease: $isPrerelease,
        );
    }
}
