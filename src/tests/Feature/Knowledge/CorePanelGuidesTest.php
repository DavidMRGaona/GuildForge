<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\CoreUpdatesPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\ModuleUpdatesPage;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Pages\Settings\MailSettings;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\GalleryResource\Pages\ListGalleries;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class CorePanelGuidesTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const array PANEL_GUIDES = [
        'core.panel.tour',
        'core.panel.dashboard-settings',
        'core.panel.manage-events',
        'core.panel.manage-articles',
        'core.panel.manage-galleries',
        'core.panel.hero-slides',
        'core.panel.tags',
        'core.panel.menu',
        'core.panel.legal-pages',
        'core.panel.manage-users',
        'core.panel.roles-and-permissions',
        'core.panel.association-role',
        'core.panel.site-settings',
        'core.panel.maintenance',
        'core.panel.mail',
        'core.panel.modules',
        'core.panel.updates',
        'core.panel.media-library',
        'core.panel.impersonation',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_editor_without_mail_permissions_does_not_see_mail_guide(): void
    {
        $editor = $this->editorWithPermissions(['events.view_any', 'events.create']);

        $visible = $this->queries()->visibleIds($editor, GuideAudience::Panel);

        $this->assertContains('core.panel.tour', $visible);
        $this->assertContains('core.panel.manage-events', $visible);
        $this->assertNotContains('core.panel.mail', $visible);
        $this->assertNotContains('core.panel.manage-users', $visible);
        $this->assertNotContains('core.panel.updates', $visible);
    }

    public function test_editor_with_only_mail_statistics_sees_mail_guide(): void
    {
        $editor = $this->editorWithPermissions(['mail.view_stats']);

        $this->assertContains('core.panel.mail', $this->queries()->visibleIds($editor, GuideAudience::Panel));
    }

    public function test_admin_sees_every_core_panel_guide(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $visible = $this->queries()->visibleIds($admin, GuideAudience::Panel);

        foreach (self::PANEL_GUIDES as $id) {
            $this->assertContains($id, $visible);
        }
    }

    public function test_impersonation_guide_needs_the_impersonation_permission(): void
    {
        $editor = $this->editorWithPermissions(['users.view_any', 'users.update']);

        $this->assertNotContains('core.panel.impersonation', $this->queries()->visibleIds($editor, GuideAudience::Panel));
    }

    public function test_edit_user_shows_the_impersonation_help_to_an_admin_only(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();

        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $target->getKey()])->assertActionVisible('contextualHelp');

        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.update']));
        Livewire::test(EditUser::class, ['record' => $target->getKey()])->assertActionDoesNotExist('contextualHelp');
    }

    public function test_each_panel_guide_renders_without_raw_markers(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $featured = [];

        foreach (self::PANEL_GUIDES as $id) {
            $guide = $this->queries()->guide($admin, GuideAudience::Panel, GuideId::fromString($id));
            $this->assertNotNull($guide, $id);
            $this->assertStringNotContainsString('{{', $guide->title.$guide->summary, $id);
            $this->assertStringNotContainsString('[[', $guide->html, $id);
            $this->assertStringNotContainsString('{{', $guide->html, $id);
            $this->assertStringNotContainsString('[!', $guide->html, $id);
        }

        foreach ($this->queries()->home($admin, GuideAudience::Panel)->featured as $summary) {
            $featured[] = $summary->id;
        }
        $this->assertContains('core.panel.tour', $featured);
    }

    public function test_event_resource_shows_the_contextual_help_action(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.create']));

        Livewire::test(ListEvents::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionVisible('create');
    }

    public function test_pages_without_a_visible_guide_have_no_help_action(): void
    {
        // The guide needs events.create: the action must be absent, not resolved by name
        $this->actingAs($this->editorWithPermissions(['events.view_any']));

        Livewire::test(ListEvents::class)->assertActionDoesNotExist('contextualHelp');
    }

    public function test_site_settings_help_falls_back_to_the_maintenance_guide(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.maintenance']));

        Livewire::test(SiteSettings::class)
            ->assertActionVisible('contextualHelp')
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => 'core.panel.maintenance']));
    }

    public function test_site_settings_help_opens_the_site_settings_guide_for_settings_managers(): void
    {
        $this->actingAs($this->editorWithPermissions(['settings.manage', 'settings.maintenance']));

        Livewire::test(SiteSettings::class)
            ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => 'core.panel.site-settings']));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function pagesWithContextualHelp(): iterable
    {
        foreach ([
            Dashboard::class,
            DashboardSettings::class,
            SiteSettings::class,
            MailSettings::class,
            MailStatisticsPage::class,
            LegalPagesSettings::class,
            AboutPageSettings::class,
            ModulesPage::class,
            ModuleUpdatesPage::class,
            CoreUpdatesPage::class,
            ListEvents::class,
            ListArticles::class,
            ListGalleries::class,
            ListHeroSlides::class,
            ListTags::class,
            ListMenuItems::class,
            ListUsers::class,
            ListRoles::class,
            MediaLibraryPage::class,
        ] as $page) {
            yield class_basename($page) => [$page];
        }
    }

    /**
     * @param  class-string  $page
     */
    #[DataProvider('pagesWithContextualHelp')]
    public function test_core_pages_show_the_contextual_help_action_to_an_admin(string $page): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test($page)->assertActionVisible('contextualHelp');
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return app(KnowledgeQueryServiceInterface::class);
    }
}
