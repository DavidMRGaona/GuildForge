<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Infrastructure\Publications\Sources\EventPublicationSource;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Publications\FakePublicationSource;
use Tests\TestCase;

final class ModuleLoaderPublicationSourcesTest extends TestCase
{
    public function test_module_publication_sources_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerPublicationSources(): array
            {
                return [FakePublicationSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(PublicationSourceRegistryInterface::class);
        $this->assertContains(FakePublicationSource::class, $registry->all());

        $registry->unregisterModule('example-module');
        $this->assertNotContains(FakePublicationSource::class, $registry->all());
        $this->assertContains(EventPublicationSource::class, $registry->all());
    }

    public function test_a_module_without_publication_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $registry = $this->app->make(PublicationSourceRegistryInterface::class);
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

        (new ReflectionMethod($loader, 'registerProviderPublicationSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
