<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Calendar\FakeCalendarAttendanceSource;
use Tests\TestCase;

final class ModuleLoaderCalendarAttendanceSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(CalendarAttendanceSourceRegistryInterface::class)->clear();
    }

    public function test_registers_module_attendance_sources_with_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerCalendarAttendanceSources(): array
            {
                return [FakeCalendarAttendanceSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(CalendarAttendanceSourceRegistryInterface::class);
        $this->assertSame([FakeCalendarAttendanceSource::class], $registry->all());

        $registry->unregisterModule('example-module');
        $this->assertSame([], $registry->all());
    }

    public function test_a_module_without_attendance_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->registerSources($provider);

        $this->assertSame([], $this->app->make(CalendarAttendanceSourceRegistryInterface::class)->all());
    }

    private function registerSources(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderCalendarAttendanceSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
