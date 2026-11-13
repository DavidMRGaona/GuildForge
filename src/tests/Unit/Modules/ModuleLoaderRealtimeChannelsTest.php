<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Realtime\ExampleRoomRealtimeChannel;
use Tests\TestCase;

final class ModuleLoaderRealtimeChannelsTest extends TestCase
{
    public function test_module_realtime_channels_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerRealtimeChannels(): array
            {
                return [ExampleRoomRealtimeChannel::class];
            }
        };

        $this->registerChannels($provider);

        $registry = $this->app->make(RealtimeChannelRegistryInterface::class);
        $user = new UserModel;
        $this->assertTrue($registry->authorize($user, 'example-module.room.open'));

        $registry->unregisterModule('example-module');
        $this->assertFalse($registry->authorize($user, 'example-module.room.open'));
    }

    public function test_a_module_without_realtime_channels_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->assertSame([], $provider->registerRealtimeChannels());
        $this->registerChannels($provider);

        $this->assertFalse($this->app->make(RealtimeChannelRegistryInterface::class)->authorize(new UserModel, 'example-module.room.open'));
    }

    private function registerChannels(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderRealtimeChannels'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
