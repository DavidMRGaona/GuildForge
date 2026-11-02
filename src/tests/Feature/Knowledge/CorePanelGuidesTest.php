<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\CoreUpdatesPage;
use App\Filament\Pages\Dashboard;
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
            ->assertActionVisible('knowledgeGuide')
            ->assertActionVisible('create');
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

        Livewire::test($page)->assertActionVisible('knowledgeGuide');
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return app(KnowledgeQueryServiceInterface::class);
    }
}
