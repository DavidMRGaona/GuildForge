<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\ModuleUpdatesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Updates\Jobs\UpdateModuleJob;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An installed module the host rejects (its module.json asks for another Filament major)
 * must still be offered its compatible release and be updatable from the panel: it is
 * how every tenant leaves the window between a host upgrade and the module upgrade.
 */
final class RejectedModuleUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-rejected-'.uniqid();
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_a_rejected_module_is_offered_its_compatible_release_and_can_be_updated(): void
    {
        $filamentMajor = (int) InstalledVersions::getVersion('filament/filament');
        $this->installedModule('announcements', '1.1.0', ['core' => '>=2.0', 'filament' => '^'.($filamentMajor + 2).'.0']);
        Http::fake([
            'api.github.com/repos/o/announcements/releases*' => Http::response([$this->release('v2.0.0')]),
            'example.test/v2.0.0/module.json' => Http::response([
                'name' => 'announcements',
                'version' => '2.0.0',
                'requires' => ['core' => '>=2.0', 'filament' => '^'.$filamentMajor.'.0'],
            ]),
        ]);
        Queue::fake();
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs(UserModel::factory()->admin()->create());

        $rejected = array_map(static fn (RejectedModuleDTO $module): string => $module->name, app(EnabledModulesResolverInterface::class)->rejected());
        $this->assertSame(['announcements'], $rejected);

        Livewire::test(ModuleUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertSet('availableUpdates.0.module_name', 'announcements')
            ->assertSet('availableUpdates.0.available_version', '2.0.0')
            ->assertSet('blockedReleases', [])
            ->call('updateAllModules')
            ->assertSet('queuedModules', ['announcements']);

        Queue::assertPushed(UpdateModuleJob::class, fn (UpdateModuleJob $job): bool => $job->moduleName === 'announcements');
    }

    /**
     * @param  array<string, string>  $requires
     */
    private function installedModule(string $name, string $version, array $requires): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode([
            'name' => $name,
            'version' => $version,
            'namespace' => 'Modules\\Announcements',
            'provider' => 'AnnouncementsServiceProvider',
            'requires' => $requires,
        ]));
        ModuleModel::factory()->enabled()->create([
            'name' => $name,
            'version' => $version,
            'path' => "{$this->modulesPath}/{$name}",
            'source_owner' => 'o',
            'source_repo' => $name,
        ]);

        // The application resolved its modules when it booted, before this row existed
        app(EnabledModulesResolverInterface::class)->reset();
    }

    /**
     * @return array<string, mixed>
     */
    private function release(string $tag): array
    {
        $version = ltrim($tag, 'v');

        return [
            'tag_name' => $tag,
            'prerelease' => false,
            'draft' => false,
            'body' => '',
            'published_at' => '2026-10-07T10:00:00Z',
            'assets' => [
                ['name' => "announcements-{$version}.zip", 'browser_download_url' => "https://example.test/announcements-{$version}.zip"],
                ['name' => "announcements-{$version}.zip.sha256", 'browser_download_url' => "https://example.test/announcements-{$version}.zip.sha256"],
                ['name' => 'module.json', 'browser_download_url' => "https://example.test/{$tag}/module.json"],
            ],
        ];
    }
}
