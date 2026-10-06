<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Modules\ModuleLoader;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ModulesServiceProvider;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

/**
 * A module whose module.json the host does not satisfy must never be compiled: a compile
 * error in panel() or in a migration cannot be caught and would take the whole site down.
 */
final class IncompatibleModuleBootTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    /** MODULES_PATH must be relative to src/ (config/modules.php wraps it in base_path()) */
    private string $relativeModulesPath;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relativeModulesPath = 'storage/framework/testing/boot-'.uniqid().'/modules';
        $this->modulesPath = base_path($this->relativeModulesPath);
        $this->root = dirname($this->modulesPath);

        foreach (['test-module', 'incompatible-module'] as $fixture) {
            File::copyDirectory(base_path("tests/Fixtures/modules/{$fixture}"), "{$this->modulesPath}/{$fixture}");
        }

        config(['modules.path' => $this->modulesPath]);
        $GLOBALS['incompatible_module_compiled'] = [];
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        unset($GLOBALS['incompatible_module_compiled']);
        parent::tearDown();
    }

    public function test_an_enabled_incompatible_module_is_never_compiled_when_the_app_boots(): void
    {
        foreach (['test-module' => 'TestModule', 'incompatible-module' => 'IncompatibleModule'] as $name => $studly) {
            ModuleModel::factory()->enabled()->create([
                'name' => $name,
                'path' => "{$this->modulesPath}/{$name}",
                'namespace' => "Modules\\{$studly}",
                'provider' => "{$studly}ServiceProvider",
            ]);
        }
        app(EnabledModulesResolverInterface::class)->reset();
        $this->app->forgetInstance(ModuleLoader::class);

        (new AdminPanelProvider($this->app))->panel(Panel::make());
        (new ModulesServiceProvider($this->app))->boot();

        $this->assertSame([], $GLOBALS['incompatible_module_compiled']);
        $this->assertContains('test-module', app(ModuleLoader::class)->loadedModules());
        $this->assertNotContains('incompatible-module', app(ModuleLoader::class)->loadedModules());
        $this->assertNotContains("{$this->modulesPath}/incompatible-module/database/migrations", app('migrator')->paths());
        $this->assertSame(['incompatible-module'], array_map(
            static fn (RejectedModuleDTO $rejected): string => $rejected->name,
            app(EnabledModulesResolverInterface::class)->rejected(),
        ));
    }

    public function test_artisan_migrates_and_lists_modules_with_an_incompatible_module_enabled(): void
    {
        $database = "{$this->root}/database.sqlite";
        $marker = "{$this->root}/compiled.txt";
        touch($database);
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'MODULES_PATH' => $this->relativeModulesPath,
            'MODULES_CACHE_ENABLED' => 'false',
            'INCOMPATIBLE_MODULE_MARKER' => $marker,
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
        ];

        // Fresh install: no modules table while the providers register
        $this->runArtisan($env, ['migrate', '--force']);

        $pdo = new PDO("sqlite:{$database}");
        $pdo->prepare("INSERT INTO modules (id, name, display_name, version, status, namespace, provider, created_at, updated_at)
            VALUES (?, 'incompatible-module', 'Incompatible module', '1.0.0', 'enabled', 'Modules\\IncompatibleModule', 'IncompatibleModuleServiceProvider', datetime('now'), datetime('now'))")
            ->execute([(string) Str::uuid()]);

        // Every entrypoint step boots the panel and the module providers
        $this->runArtisan($env, ['migrate', '--force']);
        $this->runArtisan($env, ['module:list']);

        $this->assertFileDoesNotExist($marker);
        $this->assertSame([], $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'incompatible_widgets'")?->fetchAll());
    }

    /**
     * @param  array<string, string>  $env
     * @param  list<string>  $arguments
     */
    private function runArtisan(array $env, array $arguments): void
    {
        $result = Process::path(base_path())->env($env)->timeout(300)->run(['php', 'artisan', ...$arguments]);

        $this->assertSame(0, $result->exitCode(), implode(' ', $arguments).":\n".$result->output().$result->errorOutput());
    }
}
