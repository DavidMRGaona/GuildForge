<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Pages\Settings\MailSettings;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Widgets\MailHealthWidget;
use App\Filament\Widgets\MailStatsOverviewWidget;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class PermissionGatedPagesTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function gatedPages(): array
    {
        return [
            'modules (manage)' => [ModulesPage::class, 'modules.manage', 'canAccess'],
            'modules (configure)' => [ModulesPage::class, 'modules.configure', 'canAccess'],
            'module settings' => [ModuleSettingsPage::class, 'modules.configure', 'canAccess'],
            'dashboard settings' => [DashboardSettings::class, 'dashboard.configure', 'canAccess'],
            'legal pages' => [LegalPagesSettings::class, 'pages.manage', 'canAccess'],
            'about page' => [AboutPageSettings::class, 'pages.manage', 'canAccess'],
            'mail settings' => [MailSettings::class, 'mail.configure', 'canAccess'],
            'mail health widget' => [MailHealthWidget::class, 'mail.configure', 'canView'],
            'mail statistics' => [MailStatisticsPage::class, 'mail.view_stats', 'canAccess'],
            'mail stats widget' => [MailStatsOverviewWidget::class, 'mail.view_stats', 'canView'],
        ];
    }

    #[DataProvider('gatedPages')]
    public function test_page_opens_with_its_permission_only(string $page, string $permission, string $method): void
    {
        $this->actingAs($this->editorWithPermissions([$permission]));
        $this->assertTrue($page::$method());

        $this->actingAs($this->editorWithPermissions([]));
        $this->assertFalse($page::$method());
    }

    #[DataProvider('gatedPages')]
    public function test_admin_still_opens_every_gated_page(string $page, string $permission, string $method): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertTrue($page::$method());
    }

    public function test_modules_page_hides_management_controls_without_modules_manage(): void
    {
        $this->actingAs($this->editorWithPermissions(['modules.configure']));

        Livewire::test(ModulesPage::class)
            ->assertDontSee(__('modules.filament.install_form.submit'))
            ->assertActionHidden('install')
            ->assertActionHidden('discover');
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function managementMethods(): array
    {
        return [
            'enable' => ['enableModule', ['any-module']],
            'disable' => ['disableModule', ['any-module']],
            'confirm uninstall' => ['confirmUninstall', ['any-module']],
            'uninstall' => ['uninstallModule', []],
        ];
    }

    /**
     * @param  list<string>  $arguments
     */
    #[DataProvider('managementMethods')]
    public function test_management_methods_are_forbidden_without_modules_manage(string $method, array $arguments): void
    {
        $this->actingAs($this->editorWithPermissions(['modules.configure']));

        Livewire::test(ModulesPage::class)
            ->call($method, ...$arguments)
            ->assertForbidden();
    }

    public function test_install_action_is_not_mounted_without_modules_manage(): void
    {
        $this->actingAs($this->editorWithPermissions(['modules.configure']));

        $component = Livewire::test(ModulesPage::class)->mountAction('install');

        $this->assertSame([], $component->get('mountedActions'));
    }

    public function test_modules_page_shows_management_controls_with_modules_manage(): void
    {
        $this->actingAs($this->editorWithPermissions(['modules.manage']));

        Livewire::test(ModulesPage::class)
            ->assertActionVisible('install')
            ->assertActionVisible('discover');
    }
}
