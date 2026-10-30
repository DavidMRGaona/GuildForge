<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Surveys\FakeSurveySubjectSource;
use Tests\TestCase;

final class ModuleLoaderSurveySubjectSourcesTest extends TestCase
{
    public function test_module_survey_sources_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerSurveySubjectSources(): array
            {
                return [FakeSurveySubjectSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(SurveySubjectSourceRegistryInterface::class);
        $this->assertSame([FakeSurveySubjectSource::class], $registry->all());

        $registry->unregisterModule('example-module');
        $this->assertSame([], $registry->all());
    }

    public function test_a_module_without_survey_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $this->registerSources($provider);

        $this->assertSame([], $this->app->make(SurveySubjectSourceRegistryInterface::class)->all());
    }

    private function registerSources(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderSurveySubjectSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
