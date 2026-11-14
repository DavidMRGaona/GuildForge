<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Support;

use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Support\ModuleSettingsPages;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\OwnClusterSettingsPage;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\Support\Modules\SettingsPageModuleProvider;
use Tests\Support\Modules\ThrowingAccessClusterSettingsPage;
use Tests\Support\Modules\UnregisteredClusterSettingsPage;
use Tests\TestCase;

final class ModuleSettingsPagesTest extends TestCase
{
    use InteractsWithModulePanel;
    use LazilyRefreshDatabase;
    use RegistersBootedModuleProviders;

    public function test_it_is_one_instance_per_request(): void
    {
        // Its memo is what keeps the modules page from warning once per row and render
        $this->assertSame(app(ModuleSettingsPages::class), app(ModuleSettingsPages::class));
    }

    public function test_a_module_without_a_loaded_provider_declares_nothing(): void
    {
        $pages = app(ModuleSettingsPages::class);

        $this->assertFalse($pages->declaresPage('not-loaded'));
        $this->assertNull($pages->usablePage('not-loaded'));
        $this->assertNull($pages->pageUrl('not-loaded'));
    }

    public function test_a_module_that_declares_no_page_declares_nothing(): void
    {
        $this->registerBootedProvider(new SettingsPageModuleProvider(app(), 'plain-module', null));
        $pages = app(ModuleSettingsPages::class);

        $this->assertFalse($pages->declaresPage('plain-module'));
        $this->assertNull($pages->usablePage('plain-module'));
    }

    public function test_a_registered_page_of_its_own_in_the_settings_cluster_is_usable(): void
    {
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'own-page-module', OwnClusterSettingsPage::class));
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('own-page-module'));
        $this->assertSame(OwnClusterSettingsPage::class, $pages->usablePage('own-page-module'));
        $this->assertSame(OwnClusterSettingsPage::getUrl(), $pages->pageUrl('own-page-module'));
    }

    /**
     * @return array<string, array{string, list<string>, string}>
     */
    public static function unusablePages(): array
    {
        $missing = 'Modules\\Missing\\Filament\\Pages\\SettingsPage';

        return [
            'a class that does not exist' => [$missing, [$missing], 'not_a_page'],
            'a class that is not a page' => [\stdClass::class, [\stdClass::class], 'not_a_page'],
            'a page the module does not list' => [DashboardSettings::class, [], 'not_listed_by_module'],
            'a page the panel does not register' => [UnregisteredClusterSettingsPage::class, [UnregisteredClusterSettingsPage::class], 'not_registered_in_panel'],
            'a page outside the settings cluster' => [ModulesPage::class, [ModulesPage::class], 'outside_settings_cluster'],
        ];
    }

    /**
     * @param  list<string>  $listed
     */
    #[DataProvider('unusablePages')]
    public function test_an_unusable_declared_page_still_counts_as_declared_and_says_why(string $page, array $listed, string $reason): void
    {
        // Still declared: the generic page must not take over a module that meant to leave it
        $log = Log::spy();
        $this->registerBootedProvider(new SettingsPageModuleProvider(app(), 'broken-module', $page, $listed)); // @phpstan-ignore argument.type
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('broken-module'));
        $this->assertNull($pages->usablePage('broken-module'));
        $this->assertNull($pages->pageUrl('broken-module'));
        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['module'] === 'broken-module'
                && $context['page'] === $page
                && $context['reason'] === $reason,
        );
    }

    public function test_an_unusable_page_is_reported_once_per_request(): void
    {
        // The modules page asks for every row on every render (each pause of the search box too)
        $log = Log::spy();
        $this->registerBootedProvider(new SettingsPageModuleProvider(app(), 'broken-module', UnregisteredClusterSettingsPage::class, [UnregisteredClusterSettingsPage::class]));
        $pages = app(ModuleSettingsPages::class);

        foreach (range(1, 3) as $ignored) {
            $pages->declaresPage('broken-module');
            $pages->usablePage('broken-module');
            $pages->accessibleUrl('broken-module');
        }

        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_provider_that_throws_counts_as_declared_and_unusable_and_is_reported_once(): void
    {
        // Fails closed: without knowing, the generic page could edit what the module guards
        $log = Log::spy();
        $this->registerBootedProvider(SettingsPageModuleProvider::throwing(app(), 'throwing-module'));
        $pages = app(ModuleSettingsPages::class);

        $this->assertTrue($pages->declaresPage('throwing-module'));
        $this->assertNull($pages->usablePage('throwing-module'));
        $this->assertTrue($pages->declaresPage('throwing-module'));

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['module'] === 'throwing-module'
                && $context['reason'] === 'provider_failed',
        );
    }

    public function test_the_url_is_offered_only_to_users_the_page_lets_in(): void
    {
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'own-page-module', OwnClusterSettingsPage::class));
        $pages = app(ModuleSettingsPages::class);

        $this->actingAs(UserModel::factory()->editor()->create());
        $this->assertNull($pages->accessibleUrl('own-page-module'));

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->assertSame(OwnClusterSettingsPage::getUrl(), $pages->accessibleUrl('own-page-module'));
    }

    public function test_an_access_check_that_throws_offers_no_url_and_is_reported_once(): void
    {
        $log = Log::spy();
        $this->registerOnAdminPanel(pages: [ThrowingAccessClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'failing-module', ThrowingAccessClusterSettingsPage::class));
        $this->actingAs(UserModel::factory()->admin()->create());
        $pages = app(ModuleSettingsPages::class);

        $this->assertNull($pages->accessibleUrl('failing-module'));
        $this->assertNull($pages->accessibleUrl('failing-module'));

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['module'] === 'failing-module'
                && $context['reason'] === 'access_check_failed',
        );
    }

    public function test_the_access_check_is_asked_again_for_each_call(): void
    {
        // Only failures are remembered: who is signed in can change within a test or a queue job
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'own-page-module', OwnClusterSettingsPage::class));
        $pages = app(ModuleSettingsPages::class);

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->assertNotNull($pages->accessibleUrl('own-page-module'));

        $this->actingAs(UserModel::factory()->editor()->create());
        $this->assertNull($pages->accessibleUrl('own-page-module'));
    }
}
