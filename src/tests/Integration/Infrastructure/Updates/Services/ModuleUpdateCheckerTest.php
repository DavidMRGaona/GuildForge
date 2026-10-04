<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
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
            $this->githubFetcher,
            new ReleaseChannelPolicy(allowPrereleases: false),
        );
    }

    public function test_it_detects_available_update(): void
    {
        $module = $this->createRealModule('forum', '1.0.0', 'owner', 'forum-module');
        $release = $this->createReleaseInfo('1.2.0', false);

        $this->moduleRepository->shouldReceive('findByName')
            ->with(Mockery::on(fn ($arg) => $arg instanceof ModuleName && $arg->value === 'forum'))
            ->andReturn($module);

        $this->githubFetcher->shouldReceive('getLatestRelease')
            ->with('owner', 'forum-module', false)
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
        // Every check records its time, even without an update
        $this->moduleRepository->shouldReceive('save')->once();

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
        // Every check records its time, even without an update
        $this->moduleRepository->shouldReceive('save')->once();

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
        // Every check records its time, even without an update
        $this->moduleRepository->shouldReceive('save')->once();

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
        $service = new ModuleUpdateChecker(
            $this->moduleRepository,
            $this->githubFetcher,
            new ReleaseChannelPolicy(allowPrereleases: true),
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
            ->with([['owner' => 'owner', 'repo' => 'forumall']], false)
            ->andReturn(['owner/forumall' => $release]);

        $this->moduleRepository->shouldReceive('save')->once();

        $results = $this->service->checkAllForUpdates();

        $this->assertCount(1, $results);
        $this->assertEquals('forumall', $results->first()->moduleName);
    }

    public function test_check_all_returns_empty_when_all_up_to_date(): void
    {
        $moduleCollection = new ModuleCollection;

        $this->moduleRepository->shouldReceive('all')
            ->andReturn($moduleCollection);

        $results = $this->service->checkAllForUpdates();

        $this->assertCount(0, $results);
    }

    public function test_check_all_finds_prerelease_update_for_beta_install(): void
    {
        $module = $this->createRealModule('eventreg', '1.0.8-beta', 'owner', 'eventreg');
        $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection($module));
        $this->moduleRepository->shouldReceive('save')->once();
        $this->githubFetcher->shouldReceive('batchFetchLatestReleases')
            ->with([['owner' => 'owner', 'repo' => 'eventreg']], true)
            ->andReturn(['owner/eventreg' => $this->createReleaseInfo('1.0.9-beta', true)]);

        $result = $this->service->checkAll();

        $this->assertSame('1.0.9-beta', $result->updates->first()?->availableVersion);
        $this->assertSame([], $result->errors);
        $this->assertSame('1.0.9-beta', $module->latestAvailableVersion());
    }

    public function test_check_all_reports_github_errors_per_module(): void
    {
        $module = $this->createRealModule('gametables', '1.0.0-beta', 'owner', 'gametables');
        $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection($module));
        $this->moduleRepository->shouldReceive('save');
        $this->githubFetcher->shouldReceive('batchFetchLatestReleases')
            ->andReturn(['owner/gametables' => UpdateException::githubRequestFailed('owner/gametables', 'HTTP 403')]);

        $result = $this->service->checkAll();

        $this->assertTrue($result->updates->isEmpty());
        $this->assertStringContainsString('HTTP 403', $result->errors['gametables']);
    }

    public function test_check_all_lists_modules_without_source(): void
    {
        $module = $this->createRealModuleWithoutSource('localonly');
        $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection($module));
        $this->githubFetcher->shouldNotReceive('batchFetchLatestReleases');

        $this->assertSame(['localonly'], $this->service->checkAll()->modulesWithoutSource);
    }

    public function test_check_all_clears_stale_latest_version_when_up_to_date(): void
    {
        $module = $this->createRealModule('uptodate', '1.0.9-beta', 'owner', 'uptodate');
        $module->updateLatestAvailableVersion('1.0.9-beta');
        $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection($module));
        $this->moduleRepository->shouldReceive('save')->once();
        $this->githubFetcher->shouldReceive('batchFetchLatestReleases')
            ->andReturn(['owner/uptodate' => $this->createReleaseInfo('1.0.9-beta', true)]);

        $this->service->checkAll();

        $this->assertNull($module->latestAvailableVersion());
        $this->assertNotNull($module->lastUpdateCheckAt());
    }

    public function test_check_all_fresh_clears_each_repository_cache_first(): void
    {
        $module = $this->createRealModule('freshmod', '1.0.0', 'owner', 'freshmod');
        $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection($module));
        $this->moduleRepository->shouldReceive('save');
        $this->githubFetcher->shouldReceive('clearCache')->with('owner', 'freshmod')->once()->ordered();
        $this->githubFetcher->shouldReceive('batchFetchLatestReleases')->once()->ordered()
            ->andReturn(['owner/freshmod' => null]);

        $this->service->checkAll(fresh: true);
    }

    public function test_force_check_clears_cache(): void
    {
        // Every check records its time, even without an update
        $this->moduleRepository->shouldReceive('save')->once();

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
            displayName: ucfirst($name).' Module',
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
            displayName: ucfirst($name).' Module',
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
            displayName: ucfirst($name).' Module',
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
            publishedAt: new DateTimeImmutable,
            isPrerelease: $isPrerelease,
        );
    }
}
