<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules;

use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Application\Modules\Services\ModuleCompatibilityServiceInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\HostEnvironment;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleCompatibilityService;
use App\Infrastructure\Modules\Services\ModuleManifestReader;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleCompatibilityServiceTest extends TestCase
{
    private string $modulesPath;

    private ModuleCompatibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-compat-'.uniqid();
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);

        $host = new class () implements HostEnvironmentProviderInterface {
            public function current(): HostEnvironment
            {
                return new HostEnvironment(
                    core: new ModuleVersion(2, 6, 0),
                    php: new ModuleVersion(8, 4, 26),
                    laravel: new ModuleVersion(12, 69, 3),
                    filament: new ModuleVersion(3, 3, 56),
                    extensions: ['json'],
                );
            }
        };

        $this->service = new ModuleCompatibilityService(new ModuleManifestReader(), new ModuleCompatibilityChecker(new ConstraintMatcher()), $host);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_an_installed_module_within_the_default_core_constraint_is_compatible(): void
    {
        $this->manifest('announcements', []);

        $this->assertTrue($this->service->checkInstalled(new ModuleName('announcements'))->isCompatible());
    }

    public function test_an_installed_module_that_requires_another_core_is_incompatible(): void
    {
        $this->manifest('announcements', ['core' => '^3.0']);

        $this->assertEquals(
            [new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED)],
            $this->service->checkInstalled(new ModuleName('announcements'))->issues,
        );
    }

    public function test_a_missing_manifest_fails_closed(): void
    {
        $this->assertEquals(
            [new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_MISSING)],
            $this->service->checkInstalled(new ModuleName('ghost'))->issues,
        );
    }

    public function test_an_unreadable_manifest_fails_closed(): void
    {
        File::ensureDirectoryExists($this->modulesPath.'/broken');
        File::put($this->modulesPath.'/broken/module.json', '{"name": "broken", invalid');
        File::ensureDirectoryExists($this->modulesPath.'/partial');
        File::put($this->modulesPath.'/partial/module.json', '{"name": "partial", "version": "1.0.0"}');
        File::ensureDirectoryExists($this->modulesPath.'/mistyped');
        File::put($this->modulesPath.'/mistyped/module.json', '{"name": 123, "version": "1.0.0", "namespace": "Modules\\\\Mistyped", "provider": "MistypedServiceProvider"}');

        foreach (['broken', 'partial', 'mistyped'] as $name) {
            $this->assertEquals(
                [new CompatibilityIssue(RequirementType::Manifest, '', null, CompatibilityIssue::MANIFEST_INVALID)],
                $this->service->checkInstalled(new ModuleName($name))->issues,
            );
        }
    }

    public function test_a_core_requirement_that_is_not_a_string_is_an_invalid_constraint(): void
    {
        $this->manifest('announcements', ['core' => 3]);

        $this->assertEquals(
            [new CompatibilityIssue(RequirementType::Core, '', '2.6.0', CompatibilityIssue::INVALID_CONSTRAINT)],
            $this->service->checkInstalled(new ModuleName('announcements'))->issues,
        );
    }

    public function test_the_result_is_reevaluated_when_the_manifest_changes(): void
    {
        $this->manifest('announcements', ['core' => '^3.0']);
        $this->assertFalse($this->service->checkInstalled(new ModuleName('announcements'))->isCompatible());

        $this->manifest('announcements', ['core' => '^2.6']);
        touch($this->modulesPath.'/announcements/module.json', time() + 10);

        $this->assertTrue($this->service->checkInstalled(new ModuleName('announcements'))->isCompatible());
    }

    public function test_check_manifest_file_reads_any_path(): void
    {
        $this->manifest('staged', ['core' => '^99.0']);

        $this->assertFalse($this->service->checkManifestFile($this->modulesPath.'/staged/module.json')->isCompatible());
        $this->assertFalse($this->service->checkManifestFile($this->modulesPath.'/nowhere/module.json')->isCompatible());
    }

    public function test_it_is_bound_as_a_singleton(): void
    {
        $this->assertSame(app(ModuleCompatibilityServiceInterface::class), app(ModuleCompatibilityServiceInterface::class));
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function manifest(string $name, array $requires): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires,
        ]));
    }
}
