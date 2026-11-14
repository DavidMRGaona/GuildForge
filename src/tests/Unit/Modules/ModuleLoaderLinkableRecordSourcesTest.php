<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Filament\Linking\Sources\EventLinkableRecordSource;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Linking\FakeLinkableRecordSource;
use Tests\TestCase;

final class ModuleLoaderLinkableRecordSourcesTest extends TestCase
{
    public function test_module_linkable_record_sources_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerLinkableRecordSources(): array
            {
                return [FakeLinkableRecordSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(LinkableRecordSourceRegistryInterface::class);
        $this->assertContains(FakeLinkableRecordSource::class, $registry->all());

        $registry->unregisterModule('example-module');
        $this->assertNotContains(FakeLinkableRecordSource::class, $registry->all());
        $this->assertContains(EventLinkableRecordSource::class, $registry->all());
    }

    public function test_a_module_without_linkable_record_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $registry = $this->app->make(LinkableRecordSourceRegistryInterface::class);
        $before = $registry->all();

        $this->registerSources($provider);

        $this->assertSame($before, $registry->all());
    }

    private function registerSources(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderLinkableRecordSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
