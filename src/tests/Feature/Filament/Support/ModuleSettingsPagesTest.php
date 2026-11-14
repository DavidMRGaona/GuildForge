<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support;

use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Support\ModuleSettingsPages;
use App\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\Support\Modules\UnregisteredClusterSettingsPage;
use Tests\TestCase;

final class ModuleSettingsPagesTest extends TestCase
{
    use RegistersBootedModuleProviders;

    public function test_a_module_without_a_loaded_provider_declares_nothing(): void
    {
        $pages = app(ModuleSettingsPages::class);

        $this->assertFalse($pages->declaresPage('not-loaded'));
        $this->assertNull($pages->usablePage('not-loaded'));
    }

    public function test_a_module_that_declares_no_page_declares_nothing(): void
    {
        $this->registerModule('plain-module', null);
        $pages = app(ModuleSettingsPages::class);

        $this->assertFalse($pages->declaresPage('plain-module'));
        $this->assertNull($pages->usablePage('plain-module'));
    }

    public function test_a_registered_page_of_the_settings_cluster_is_usable(): void
    {
        $this->registerModule('own-page-module', DashboardSettings::class);
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('own-page-module'));
        $this->assertSame(DashboardSettings::class, $pages->usablePage('own-page-module'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusablePages(): array
    {
        return [
            'a class that does not exist' => ['Modules\\Missing\\Filament\\Pages\\SettingsPage'],
            'a class that is not a page' => [\stdClass::class],
            'a page the panel does not register' => [UnregisteredClusterSettingsPage::class],
            'a page outside the settings cluster' => [ModulesPage::class],
        ];
    }

    #[DataProvider('unusablePages')]
    public function test_an_unusable_declared_page_still_counts_as_declared_and_is_logged(string $page): void
    {
        // Still declared: the generic page must not take over a module that meant to leave it
        $log = Log::spy();
        $this->registerModule('broken-module', $page);
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('broken-module'));
        $this->assertNull($pages->usablePage('broken-module'));
        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $context['module'] === 'broken-module'
                && $context['page'] === $page,
        );
    }

    public function test_a_provider_that_throws_counts_as_declared_and_unusable(): void
    {
        // Fails closed: without knowing, the generic page could edit what the module guards
        $this->registerBootedProvider(new class(app()) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'throwing-module';
            }

            public function getSettingsPage(): ?string
            {
                throw new \RuntimeException('boom');
            }
        });
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('throwing-module'));
        $this->assertNull($pages->usablePage('throwing-module'));
    }

    private function registerModule(string $name, ?string $page): void
    {
        $this->registerBootedProvider(new class(app(), $name, $page) extends ModuleServiceProvider
        {
            public function __construct(Application $app, private readonly string $name, private readonly ?string $page)
            {
                parent::__construct($app);
            }

            public function moduleName(): string
            {
                return $this->name;
            }

            public function getSettingsPage(): ?string
            {
                // Deliberately any string: the core must cope with what a module declares
                return $this->page; // @phpstan-ignore return.type
            }
        });
    }
}
