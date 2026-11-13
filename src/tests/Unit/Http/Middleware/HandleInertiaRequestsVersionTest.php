<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Middleware;
use RuntimeException;
use Tests\Support\Knowledge\StaticModulesResolver;
use Tests\TestCase;

/**
 * The asset version also follows the enabled modules, so a tab opened before a module was
 * installed, enabled, disabled or updated reloads in full on its next visit and gets that
 * module's translations, pages and bundles (core#623).
 */
final class HandleInertiaRequestsVersionTest extends TestCase
{
    public function test_without_modules_the_version_is_the_core_one(): void
    {
        $this->useModules([]);

        $this->assertSame($this->coreVersion(), $this->version());
    }

    public function test_enabling_a_module_changes_the_version(): void
    {
        $this->useModules([$this->module('surveys', '1.0.0')]);
        $before = $this->version();

        $this->useModules([$this->module('surveys', '1.0.0'), $this->module('forum', '1.0.0')]);

        $this->assertNotSame($before, $this->version());
    }

    public function test_disabling_a_module_changes_the_version(): void
    {
        $this->useModules([$this->module('surveys', '1.0.0'), $this->module('forum', '1.0.0')]);
        $before = $this->version();

        $this->useModules([$this->module('surveys', '1.0.0')]);

        $this->assertNotSame($before, $this->version());
    }

    public function test_updating_a_module_changes_the_version(): void
    {
        $this->useModules([$this->module('forum', '1.0.0')]);
        $before = $this->version();

        $this->useModules([$this->module('forum', '1.1.0')]);

        $this->assertNotSame($before, $this->version());
    }

    public function test_the_order_the_modules_come_in_does_not_change_the_version(): void
    {
        $this->useModules([$this->module('surveys', '1.0.0'), $this->module('forum', '1.0.0')]);
        $before = $this->version();

        $this->useModules([$this->module('forum', '1.0.0'), $this->module('surveys', '1.0.0')]);

        $this->assertSame($before, $this->version());
    }

    public function test_the_same_modules_give_the_same_version_on_every_request(): void
    {
        $this->useModules([$this->module('forum', '1.0.0')]);

        $this->assertSame($this->version(), $this->version());
    }

    public function test_an_unreadable_module_list_falls_back_to_the_core_version(): void
    {
        $this->app->instance(EnabledModulesResolverInterface::class, new class implements EnabledModulesResolverInterface
        {
            public function names(): array
            {
                throw new RuntimeException('modules table unreachable');
            }

            public function modules(): array
            {
                throw new RuntimeException('modules table unreachable');
            }

            public function rejected(): array
            {
                return [];
            }

            public function migrationPaths(): array
            {
                return [];
            }

            public function reset(): void {}
        });

        $this->assertSame($this->coreVersion(), $this->version());
    }

    /**
     * @param  list<Module>  $modules
     */
    private function useModules(array $modules): void
    {
        $this->app->instance(EnabledModulesResolverInterface::class, new StaticModulesResolver($modules));
    }

    private function version(): ?string
    {
        return (new HandleInertiaRequests)->version(Request::create('/', 'GET'));
    }

    private function coreVersion(): ?string
    {
        return (new class extends Middleware {})->version(Request::create('/', 'GET'));
    }

    private function module(string $name, string $version): Module
    {
        return new Module(
            id: ModuleId::generate(),
            name: ModuleName::fromString($name),
            displayName: $name,
            description: '',
            version: ModuleVersion::fromString($version),
            author: '',
            requirements: ModuleRequirements::fromArray([]),
            status: ModuleStatus::Enabled,
        );
    }
}
