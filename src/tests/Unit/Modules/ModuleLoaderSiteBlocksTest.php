<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\SiteBlocks\FakeSiteBlock;
use Tests\TestCase;

final class ModuleLoaderSiteBlocksTest extends TestCase
{
    public function test_module_site_blocks_are_registered_under_the_module_name(): void
    {
        $block = new class extends FakeSiteBlock
        {
            public function key(): string
            {
                return 'example-module.subscribe';
            }
        };

        $provider = new class($this->app) extends ModuleServiceProvider
        {
            /** @var list<class-string<SiteBlockInterface>> */
            public static array $blocks = [];

            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerSiteBlocks(): array
            {
                return self::$blocks;
            }
        };
        $provider::$blocks = [$block::class];

        $this->registerSiteBlocks($provider);

        $registry = $this->app->make(SiteBlockRegistryInterface::class);
        $this->assertSame('example-module.subscribe', $registry->find('example-module.subscribe')?->key());

        $registry->unregisterModule('example-module');
        $this->assertNull($registry->find('example-module.subscribe'));
    }

    public function test_a_module_without_site_blocks_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->assertSame([], $provider->registerSiteBlocks());
        $this->registerSiteBlocks($provider);

        $this->assertSame([], $this->app->make(SiteBlockRegistryInterface::class)->all());
    }

    private function registerSiteBlocks(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderSiteBlocks'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
