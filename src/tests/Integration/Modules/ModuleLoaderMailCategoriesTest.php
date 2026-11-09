<?php

declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Mail\Exceptions\InvalidMailCategoryException;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

final class ModuleLoaderMailCategoriesTest extends TestCase
{
    public function test_module_categories_are_registered_under_the_module_prefix(): void
    {
        $this->registerCategories($this->provider('example-module.reminders'));

        $this->assertNotNull(
            $this->app->make(MailCategoryRegistryInterface::class)->find('example-module.reminders'),
        );
    }

    public function test_a_module_cannot_declare_a_category_of_another_prefix(): void
    {
        $this->expectException(InvalidMailCategoryException::class);

        $this->registerCategories($this->provider('core.account'));
    }

    private function provider(string $key): ModuleServiceProvider
    {
        return new class($this->app, $key) extends ModuleServiceProvider
        {
            public function __construct($app, private readonly string $key)
            {
                parent::__construct($app);
            }

            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerMailCategories(): array
            {
                return [MailCategoryDTO::optional(
                    key: $this->key,
                    section: 'example-module::mail.section',
                    label: 'example-module::mail.reminders.label',
                    description: 'example-module::mail.reminders.description',
                )];
            }
        };
    }

    private function registerCategories(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader(
            $this->app,
            Mockery::mock(EnabledModulesResolverInterface::class),
        );

        (new ReflectionMethod($loader, 'registerProviderMailCategories'))
            ->invoke($loader, $provider, $provider->moduleName());
    }
}
