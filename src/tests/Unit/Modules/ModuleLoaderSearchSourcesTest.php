<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Search\FakeIndexedSearchSource;
use Tests\TestCase;

final class ModuleLoaderSearchSourcesTest extends TestCase
{
    public function test_registers_module_search_sources_with_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerSearchSources(): array
            {
                return [FakeIndexedSearchSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(SearchSourceRegistryInterface::class);
        $this->assertSame('example-module', $registry->moduleOf('fake-indexed'));

        $registry->unregisterModule('example-module');
        $this->assertSame([], $registry->indexedSources());
    }

    public function test_a_module_without_search_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->registerSources($provider);

        $this->assertSame([], $this->app->make(SearchSourceRegistryInterface::class)->indexedSources());
    }

    private function registerSources(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderSearchSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
