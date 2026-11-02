<?php

declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Knowledge\FakeKnowledgeCapability;
use Tests\TestCase;

final class ModuleLoaderKnowledgeCapabilitiesTest extends TestCase
{
    public function test_module_capabilities_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerKnowledgeCapabilities(): array
            {
                return [FakeKnowledgeCapability::class];
            }
        };

        $this->registerCapabilities($provider);

        $registry = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $this->assertContains('fake.can_do_it', $registry->keys());

        $registry->unregisterModule('example-module');
        $this->assertNotContains('fake.can_do_it', $registry->keys());
    }

    public function test_the_core_email_verification_capability_is_registered(): void
    {
        $this->assertContains(
            'core.email_verification_required',
            $this->app->make(KnowledgeCapabilityRegistryInterface::class)->keys(),
        );
    }

    private function registerCapabilities(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderKnowledgeCapabilities'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
