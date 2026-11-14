<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\UserResource;
use App\Filament\Support\PanelCreateMenu;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * The panel top bar: global search, the «Crear» menu, help and the public site, plus the
 * profile avatar and the user menu links.
 */
final class PanelTopbarTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_avatar_is_the_profile_picture_of_the_public_site(): void
    {
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => '']);

        $user = UserModel::factory()->create(['avatar_public_id' => 'avatars/abc.jpg']);

        $this->assertStringStartsWith('https://res.cloudinary.com/demo/image/upload/', (string) $user->getFilamentAvatarUrl());
        $this->assertStringEndsWith('/avatars/abc', (string) $user->getFilamentAvatarUrl());
    }

    public function test_avatar_falls_back_to_initials_without_a_picture(): void
    {
        $user = UserModel::factory()->create(['avatar_public_id' => null]);

        $this->assertNull($user->getFilamentAvatarUrl());
    }

    public function test_the_initials_avatar_is_drawn_in_the_panel_without_third_parties(): void
    {
        $user = UserModel::factory()->create(['name' => 'John Doe', 'avatar_public_id' => null]);

        Filament::setCurrentPanel('admin');
        $url = Filament::getUserAvatarUrl($user);

        $this->assertIsString($url);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);
    }

    public function test_core_content_and_users_are_globally_searchable(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        foreach ([EventResource::class, ArticleResource::class, GalleryResource::class, UserResource::class] as $resource) {
            $this->assertTrue($resource::canGloballySearch(), $resource.' is not globally searchable');
        }

        $this->assertSame(['command+k', 'ctrl+k'], Filament::getPanel('admin')->getGlobalSearchKeyBindings());
    }

    public function test_global_search_finds_events_the_user_may_see(): void
    {
        EventModel::factory()->create(['title' => 'Torneo de primavera']);
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));

        Filament::setCurrentPanel('admin');

        Livewire::test(GlobalSearch::class)
            ->set('search', 'primavera')
            ->assertSee('Torneo de primavera');
    }

    public function test_global_search_leaves_out_resources_the_user_may_not_see(): void
    {
        UserModel::factory()->create(['name' => 'Persona escondida']);
        EventModel::factory()->create(['title' => 'Jornada escondida']);
        // Content without accounts: the event shows up, the person does not
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.update']));

        Filament::setCurrentPanel('admin');

        Livewire::test(GlobalSearch::class)
            ->set('search', 'escondida')
            ->assertSee('Jornada escondida')
            ->assertDontSee('Persona escondida');
    }

    public function test_top_bar_offers_create_links_help_and_the_site(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('data-panel-create-menu', false)
            ->assertSee(EventResource::getUrl('create'), false)
            ->assertSee(ArticleResource::getUrl('create'), false)
            ->assertSee('data-panel-help-link', false)
            ->assertSee(KnowledgeBasePage::getUrl(), false)
            ->assertSee('data-panel-site-link', false);
    }

    public function test_create_menu_lays_groups_out_in_columns_that_scroll(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('data-panel-create-columns', false)
            // Every group is a block of its own, heading first, so a column never splits it
            ->assertSeeInOrder(['data-panel-create-group', 'Contenido', EventResource::getUrl('create')], false);
    }

    public function test_create_menu_groups_follow_the_sidebar_order(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        $menuLabels = array_values(array_filter(array_column(PanelCreateMenu::groups(), 'label')));
        $sidebarLabels = array_values(array_filter(array_map(
            static fn (NavigationGroup $group): ?string => $group->getLabel() ?? null,
            Filament::getPanel('admin')->getNavigation(),
        )));

        $this->assertNotSame([], $menuLabels);
        $this->assertSame(array_values(array_intersect($sidebarLabels, $menuLabels)), $menuLabels);
    }

    public function test_create_menu_lists_only_what_the_user_may_create(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'events.create']));

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(EventResource::getUrl('create'), false)
            ->assertDontSee(ArticleResource::getUrl('create'), false)
            ->assertDontSee(UserResource::getUrl('create'), false);
    }

    public function test_create_menu_is_left_out_when_nothing_can_be_created(): void
    {
        $this->actingAs($this->editorWithPermissions([]));

        $this->get(KnowledgeBasePage::getUrl())
            ->assertOk()
            ->assertDontSee('data-panel-create-menu', false);
    }

    public function test_contextual_help_button_tells_the_top_bar_its_guide(): void
    {
        $this->knowledgeCategory('panel-basics', 'panel');
        $this->knowledgeGuide('core.panel.manage-events', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ListEvents::class)
            ->assertSeeHtml('data-panel-guide-url="'.e(KnowledgeBasePage::getUrl(['guia' => 'core.panel.manage-events'])).'"');
    }

    public function test_user_menu_links_to_the_profile_and_the_help(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(route('profile.show'), false)
            ->assertSee(__('filament.user_menu.profile'))
            ->assertSee(__('filament.user_menu.help'));
    }

    public function test_the_bell_shows_the_unread_notices_and_leads_to_them(): void
    {
        $user = UserModel::factory()->admin()->create();
        $this->notify($user, 3);
        $this->actingAs($user);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('data-panel-notifications-bell', false)
            ->assertSee('href="'.route('site-notifications.index').'"', false)
            ->assertSee('aria-label="Avisos, 3 sin leer"', false)
            ->assertSee('data-panel-notifications-badge', false)
            ->assertSee('>3</span>', false)
            ->assertSee('data-count-url="'.route('site-notifications.count').'"', false);
    }

    public function test_the_bell_caps_its_count_like_the_site_and_says_nothing_with_none_unread(): void
    {
        $busy = UserModel::factory()->admin()->create();
        $this->notify($busy, 12);
        $this->actingAs($busy);
        $this->get(Dashboard::getUrl())->assertSee('>9+</span>', false)->assertSee('aria-label="Avisos, 12 sin leer"', false);

        $calm = UserModel::factory()->admin()->create();
        $this->actingAs($calm);
        $content = $this->get(Dashboard::getUrl())
            ->assertSee('data-panel-notifications-bell', false)
            ->assertSee('aria-label="Avisos"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/data-panel-notifications-badge\s+hidden/', (string) $content);
    }

    public function test_the_bell_is_left_out_while_the_account_must_verify_its_email(): void
    {
        $this->app->instance(EmailVerificationPolicyInterface::class, new class implements EmailVerificationPolicyInterface
        {
            public function mustVerify(object $user): bool
            {
                return true;
            }
        });
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('data-panel-notifications-bell', false);
    }

    private function notify(UserModel $user, int $times): void
    {
        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );

        foreach (range(1, $times) as $n) {
            $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Aviso '.$n, url: '/foro/'.$n)));
        }
    }
}
