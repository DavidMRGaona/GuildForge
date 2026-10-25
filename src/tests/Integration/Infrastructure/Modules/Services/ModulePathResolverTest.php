<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModulePathResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ModulePathResolverTest extends TestCase
{
    private const string MODULE = 'path-test';

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/module-paths-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->modulesPath.'/'.self::MODULE, 0755, true);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_the_stored_path_wins_while_it_exists(): void
    {
        Log::spy();
        $stored = $this->modulesPath.'/elsewhere/'.self::MODULE;
        File::makeDirectory($stored, 0755, true);

        $this->assertSame($stored, $this->resolver()->resolve($this->module($stored)));
        Log::shouldNotHaveReceived('warning');
    }

    public function test_a_stale_stored_path_falls_back_to_the_modules_directory(): void
    {
        Log::spy();
        $stale = $this->modulesPath.'/moved-away';

        $this->assertSame($this->modulesPath.'/'.self::MODULE, $this->resolver()->resolve($this->module($stale)));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Stored path of module '.self::MODULE.' does not exist, using the modules directory'
                && $context === ['stored_path' => $stale, 'path' => $this->modulesPath.'/'.self::MODULE]);
    }

    public function test_the_fallback_is_logged_once_a_day_not_on_every_request(): void
    {
        Log::spy();
        $module = $this->module($this->modulesPath.'/moved-away');

        $this->resolver()->resolve($module);
        $this->resolver()->resolve($module);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_fallback_is_logged_when_the_cache_is_down(): void
    {
        Log::spy();
        Cache::shouldReceive('add')->andThrow(new \RuntimeException('cache down'));

        $this->resolver()->resolve($this->module($this->modulesPath.'/moved-away'));

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_module_found_nowhere_throws(): void
    {
        File::deleteDirectory($this->modulesPath.'/'.self::MODULE);

        $this->expectException(ModuleNotFoundException::class);

        $this->resolver()->resolve($this->module($this->modulesPath.'/moved-away'));
    }

    public function test_the_modules_directory_defaults_to_the_configured_one(): void
    {
        config(['modules.path' => $this->modulesPath]);

        $this->assertSame($this->modulesPath.'/'.self::MODULE, (new ModulePathResolver)->defaultPath(self::MODULE));
        $this->assertSame(
            $this->modulesPath.'/'.self::MODULE,
            (new ModulePathResolver)->resolve($this->module($this->modulesPath.'/moved-away')),
        );
    }

    private function resolver(): ModulePathResolver
    {
        return new ModulePathResolver($this->modulesPath);
    }

    private function module(string $path): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: new ModuleName(self::MODULE),
            displayName: 'Path test',
            description: 'Module whose directory the test moves',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test Author',
            requirements: new ModuleRequirements(
                phpVersion: null,
                laravelVersion: null,
                requiredModules: [],
                requiredExtensions: [],
            ),
            status: ModuleStatus::Enabled,
            path: $path,
        );
    }
}
