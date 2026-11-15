<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileRegistrationDTO;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Profiles\FakeProfileSection;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

final class ModuleLoaderProfilesTest extends TestCase
{
    public function test_module_profile_sections_and_stats_are_registered_under_the_module_name(): void
    {
        $section = new class extends FakeProfileSection
        {
            public function key(): string
            {
                return 'example-module.latest';
            }
        };
        $stat = new class extends FakeProfileStat
        {
            public function key(): string
            {
                return 'example-module.count';
            }
        };

        $provider = new class($this->app) extends ModuleServiceProvider
        {
            /** @var list<class-string<ProfileSectionInterface>> */
            public static array $sections = [];

            /** @var list<class-string<ProfileStatInterface>> */
            public static array $stats = [];

            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerProfileSections(): array
            {
                return self::$sections;
            }

            public function registerProfileStats(): array
            {
                return self::$stats;
            }
        };
        $provider::$sections = [$section::class];
        $provider::$stats = [$stat::class];

        $this->register($provider);

        $sections = $this->app->make(ProfileSectionRegistryInterface::class);
        $stats = $this->app->make(ProfileStatRegistryInterface::class);
        $this->assertSame('example-module', $sections->find('example-module.latest')?->module);
        $this->assertSame($stat::class, $stats->find('example-module.count')?->class);

        $sections->unregisterModule('example-module');
        $stats->unregisterModule('example-module');
        $this->assertNull($sections->find('example-module.latest'));
        $this->assertNull($stats->find('example-module.count'));
    }

    public function test_a_module_without_profile_sections_or_stats_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->assertSame([], $provider->registerProfileSections());
        $this->assertSame([], $provider->registerProfileStats());
        $this->register($provider);

        // Only the core's own section, and no stat
        $this->assertSame(['core.about'], array_map(
            static fn (ProfileRegistrationDTO $registration): string => $registration->key,
            $this->app->make(ProfileSectionRegistryInterface::class)->all(),
        ));
        $this->assertSame([], $this->app->make(ProfileStatRegistryInterface::class)->all());
    }

    private function register(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        foreach (['registerProviderProfileSections', 'registerProviderProfileStats'] as $method) {
            (new ReflectionMethod($loader, $method))->invoke($loader, $provider, $provider->moduleName());
        }
    }
}
