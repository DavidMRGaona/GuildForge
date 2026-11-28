<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Application\Bots\Contracts\BotInterface;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Bots\FakeBot;
use Tests\TestCase;

final class ModuleBotsRegistrationTest extends TestCase
{
    public function test_an_enabled_module_bots_are_registered_with_its_name(): void
    {
        $provider = $this->provider([FakeBot::class]);

        $this->loadBots($provider);

        $definition = $this->app->make(BotRegistryInterface::class)->find('forum.publisher');
        $this->assertNotNull($definition);
        $this->assertSame('forum', $definition->module);
    }

    public function test_a_module_without_bots_registers_nothing(): void
    {
        $this->loadBots($this->provider([]));

        $this->assertSame([], $this->app->make(BotRegistryInterface::class)->all());
    }

    public function test_a_module_that_is_not_loaded_offers_no_bots(): void
    {
        // A disabled module is never loaded, so its provider is never asked for its bots
        $this->provider([FakeBot::class]);

        $this->assertSame([], $this->app->make(BotRegistryInterface::class)->all());
    }

    /**
     * @param  array<class-string<BotInterface>>  $bots
     */
    private function provider(array $bots): ModuleServiceProvider
    {
        return new class($this->app, $bots) extends ModuleServiceProvider
        {
            /** @param array<class-string<BotInterface>> $bots */
            public function __construct($app, private readonly array $bots)
            {
                parent::__construct($app);
            }

            public function moduleName(): string
            {
                return 'forum';
            }

            /** @return array<class-string<BotInterface>> */
            public function registerBots(): array
            {
                return $this->bots;
            }
        };
    }

    private function loadBots(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));
        (new ReflectionMethod($loader, 'registerProviderBots'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
