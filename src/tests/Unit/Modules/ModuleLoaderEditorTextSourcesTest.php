<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\Support\Content\NotesEditorTextSource;
use Tests\TestCase;

final class ModuleLoaderEditorTextSourcesTest extends TestCase
{
    public function test_module_editor_text_sources_are_registered_under_the_module_name(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerEditorTextSources(): array
            {
                return [NotesEditorTextSource::class];
            }
        };

        $this->registerSources($provider);

        $registry = $this->app->make(EditorTextSourceRegistryInterface::class);
        $this->assertInstanceOf(NotesEditorTextSource::class, $registry->source(NotesEditorTextSource::TYPE));

        $registry->unregisterModule('example-module');
        $this->assertNull($registry->source(NotesEditorTextSource::TYPE));
        $this->assertNotNull($registry->source('articles'));
    }

    public function test_a_module_without_editor_text_sources_registers_nothing(): void
    {
        $provider = new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }
        };

        $registry = $this->app->make(EditorTextSourceRegistryInterface::class);
        $before = $this->types($registry);

        $this->registerSources($provider);

        $this->assertSame($before, $this->types($registry));
    }

    /**
     * @return list<string>
     */
    private function types(EditorTextSourceRegistryInterface $registry): array
    {
        return array_map(static fn (EditorTextSourceInterface $source): string => $source->type(), $registry->sources());
    }

    private function registerSources(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderEditorTextSources'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
