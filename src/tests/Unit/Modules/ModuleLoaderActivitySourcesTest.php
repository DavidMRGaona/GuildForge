<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Activity\FakeActivitySource;
use Tests\TestCase;

final class ModuleLoaderActivitySourcesTest extends TestCase
{
    public function test_module_activity_sources_are_registered_under_the_module_name(): void
    {
        $source = new class extends FakeActivitySource
        {
            public function type(): string
            {
                return 'example-module.session';
            }
        };

        $provider = new class($this->app) extends ModuleServiceProvider
        {
            /** @var list<class-string<ActivitySourceInterface>> */
            public static array $sources = [];

            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerActivitySources(): array
            {
                return self::$sources;
            }
        };
        $provider::$sources = [$source::class];

        $this->register($provider);

        $registry = $this->app->make(ActivitySourceRegistryInterface::class);
        $this->assertSame('example-module', $registry->find('example-module.session')?->module);
        $this->assertSame($source::class, $registry->find('example-module.session')->class);

        $registry->unregisterModule('example-module');
        $this->assertNull($registry->find('example-module.session'));
    }

    public function test_a_module_without_activity_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->assertSame([], $provider->registerActivitySources());
        $this->register($provider);

        $this->assertSame([], $this->app->make(ActivitySourceRegistryInterface::class)->all());
    }

    private function register(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderActivitySources'))->invoke($loader, $provider, $provider->moduleName());
    }
}
