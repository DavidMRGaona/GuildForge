<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Infrastructure\Modules\Services\EnabledModulesResolver;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EnabledModulesResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-resolver-'.uniqid();
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);

        foreach (['test-module', 'incompatible-module'] as $fixture) {
            File::copyDirectory(base_path("tests/Fixtures/modules/{$fixture}"), "{$this->modulesPath}/{$fixture}");
        }

        // A compatible module with migrations that nobody enabled yet
        File::ensureDirectoryExists("{$this->modulesPath}/disabled-module/database/migrations");
        File::put("{$this->modulesPath}/disabled-module/module.json", (string) json_encode([
            'name' => 'disabled-module', 'version' => '1.0.0', 'namespace' => 'Modules\\DisabledModule', 'provider' => 'DisabledModuleServiceProvider',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_only_enabled_and_compatible_modules_are_resolved(): void
    {
        $this->enabled('test-module');
        $this->enabled('incompatible-module');
        ModuleModel::factory()->disabled()->create(['name' => 'disabled-module']);

        $resolver = $this->resolver();

        $this->assertSame(['test-module'], $resolver->names());
        $this->assertSame(['test-module'], array_map(static fn (Module $m): string => $m->name()->value, $resolver->modules()));
        $this->assertEquals([new RejectedModuleDTO('incompatible-module', 'Incompatible module', [
            new CompatibilityIssue(RequirementType::Core, '^99.0', app(\App\Application\Updates\Services\CoreVersionServiceInterface::class)->getCurrentVersion()->value(), CompatibilityIssue::UNSATISFIED),
        ])], $resolver->rejected());
    }

    public function test_an_enabled_module_without_directory_is_rejected_as_manifest_missing(): void
    {
        $this->enabled('vanished-module');

        $resolver = $this->resolver();

        $this->assertSame([], $resolver->names());
        $this->assertSame('vanished-module', $resolver->rejected()[0]->name);
        $this->assertSame(CompatibilityIssue::MANIFEST_MISSING, $resolver->rejected()[0]->issues[0]->reasonKey);
    }

    public function test_migration_paths_cover_compatible_modules_on_disk_whatever_their_status(): void
    {
        $this->enabled('incompatible-module');

        $paths = $this->resolver()->migrationPaths();

        $this->assertContains("{$this->modulesPath}/disabled-module/database/migrations", $paths);
        $this->assertNotContains("{$this->modulesPath}/incompatible-module/database/migrations", $paths);
    }

    public function test_without_modules_table_nothing_is_enabled_but_compatible_migrations_load(): void
    {
        Schema::drop('modules');

        $resolver = $this->resolver();

        $this->assertSame([], $resolver->names());
        $this->assertSame([], $resolver->rejected());
        $this->assertSame(["{$this->modulesPath}/disabled-module/database/migrations"], $resolver->migrationPaths());
    }

    public function test_the_module_cache_never_persists_a_rejection(): void
    {
        config(['modules.cache.enabled' => true, 'modules.cache.key' => 'modules.discovered']);
        $this->enabled('test-module');
        $this->enabled('incompatible-module');

        $resolver = $this->resolver();
        $this->assertSame(['test-module'], $resolver->names());
        $this->assertCount(2, Cache::get('modules.discovered'));

        // The host becomes compatible again (image rollback, module update): no database change needed
        $manifest = "{$this->modulesPath}/incompatible-module/module.json";
        File::put($manifest, str_replace('^99.0', '^2.0', File::get($manifest)));
        $resolver->reset();

        $this->assertSame(['test-module', 'incompatible-module'], $resolver->names());
    }

    public function test_results_are_memoized_until_reset(): void
    {
        $resolver = $this->resolver();
        $this->assertSame([], $resolver->names());

        $this->enabled('test-module');
        $this->assertSame([], $resolver->names());

        $resolver->reset();
        $this->assertSame(['test-module'], $resolver->names());
    }

    public function test_a_rejection_is_logged_once_across_processes(): void
    {
        $this->enabled('incompatible-module');
        Log::spy();

        // Every scheduler tick is a new process with a new resolver
        app()->make(EnabledModulesResolver::class)->names();
        app()->make(EnabledModulesResolver::class)->names();

        Log::shouldHaveReceived('warning')
            ->with('[EnabledModulesResolver] Module rejected', \Mockery::on(
                static fn (array $context): bool => $context['module'] === 'incompatible-module',
            ))
            ->once();
    }

    public function test_web_requests_never_log_a_rejection(): void
    {
        $this->enabled('incompatible-module');
        Log::spy();
        $setConsole = fn (bool $console) => (fn () => $this->isRunningInConsole = $console)->call(app());
        $setConsole(false);

        try {
            $resolver = app()->make(EnabledModulesResolver::class);
            $this->assertSame([], $resolver->names());
            $this->assertCount(1, $resolver->rejected());
        } finally {
            $setConsole(true);
        }

        // The panel banner replaces the log on the web
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_different_reason_is_logged_again(): void
    {
        $this->enabled('incompatible-module');
        Log::spy();

        app()->make(EnabledModulesResolver::class)->names();

        $manifest = "{$this->modulesPath}/incompatible-module/module.json";
        File::put($manifest, str_replace('^99.0', '^999.0', File::get($manifest)));
        app()->make(EnabledModulesResolver::class)->names();

        Log::shouldHaveReceived('warning')->twice();
    }

    private function resolver(): EnabledModulesResolverInterface
    {
        $resolver = app(EnabledModulesResolverInterface::class);
        $resolver->reset();

        return $resolver;
    }

    private function enabled(string $name): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => $name,
            'display_name' => ucfirst(str_replace('-', ' ', $name)),
            'path' => "{$this->modulesPath}/{$name}",
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
        ]);
    }
}
