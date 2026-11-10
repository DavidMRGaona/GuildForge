<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Media\FakeMediaSource;
use Tests\TestCase;

final class ModuleMediaSourcesTest extends TestCase
{
    public function test_enabled_module_sources_are_registered_with_their_module_name(): void
    {
        $registry = $this->app->make(MediaSourceRegistryInterface::class);
        $registry->clear();

        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerMediaSources(): array
            {
                return [FakeMediaSource::class];
            }
        };

        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));
        (new ReflectionMethod($loader, 'registerProviderMediaSources'))
            ->invoke($loader, $provider, $provider->moduleName());

        $this->assertSame('example-module', $registry->moduleOf('fake'));

        $registry->unregisterModule('example-module');
        $this->assertSame([], $registry->sources());
    }

    public function test_a_module_without_media_sources_registers_nothing(): void
    {
        $registry = $this->app->make(MediaSourceRegistryInterface::class);
        $registry->clear();

        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));
        (new ReflectionMethod($loader, 'registerProviderMediaSources'))
            ->invoke($loader, $provider, $provider->moduleName());

        $this->assertSame([], $registry->sources());
    }
}
