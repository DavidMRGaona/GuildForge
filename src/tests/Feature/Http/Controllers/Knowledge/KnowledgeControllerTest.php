<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Knowledge;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class KnowledgeControllerTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeCategory('getting-started');
        $this->knowledgeCategory('account', order: 20);
        $this->knowledgeCategory('venue', order: 30);
        $this->knowledgeCategory('panel-basics', 'panel');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/ayuda')->assertRedirect(route('login'));
        $this->get('/ayuda/core.one')->assertRedirect(route('login'));
        $this->get('/ayuda/categoria/account')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_categories_with_visible_guides(): void
    {
        $this->knowledgeGuide('core.one');
        $this->knowledgeGuide('core.two');
        $this->knowledgeGuide('core.locked', ['category_id' => 'venue', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Index')
            ->has('home.categories', 1)
            ->where('home.categories.0.id', 'getting-started')
            ->where('home.categories.0.label', 'Primeros pasos')
            ->where('home.categories.0.guideCount', 2)
            ->where('home.categories.0.icon', 'heroicon-o-rocket-launch')
            ->where('query', '')
            ->where('results', null)
            ->has('seo'));
    }

    public function test_index_features_starred_guides_first_then_new_ones_up_to_six(): void
    {
        $old = now()->subDays(90);
        $this->knowledgeGuide('core.plain', ['first_seen_at' => $old, 'content_changed_at' => $old]);
        $this->knowledgeGuide('core.star', ['featured' => true, 'first_seen_at' => $old, 'content_changed_at' => $old]);
        $this->knowledgeGuide('core.fresh', ['first_seen_at' => now()->subDay(), 'content_changed_at' => now()->subDay()]);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda')->assertInertia(fn (Assert $page) => $page
            ->has('home.featured', 2)
            ->where('home.featured.0.id', 'core.star')
            ->where('home.featured.0.badge', null)
            ->where('home.featured.1.id', 'core.fresh')
            ->where('home.featured.1.badge', 'new')
            ->where('home.featured.1.categoryId', 'getting-started'));
    }

    public function test_index_with_a_query_returns_hits(): void
    {
        $this->knowledgeGuide('core.signup', ['title' => 'Apuntarse a una partida', 'summary' => 'Cómo apuntarse', 'body_text' => 'Pulsa apuntarme.']);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda?q=apuntarse')->assertInertia(fn (Assert $page) => $page
            ->where('query', 'apuntarse')
            ->has('results', 1)
            ->where('results.0.id', 'core.signup')
            ->has('results.0.snippet'));
    }

    public function test_category_page_lists_guides_in_order(): void
    {
        $this->knowledgeGuide('core.b', ['category_id' => 'account', 'sort_order' => 20]);
        $this->knowledgeGuide('core.a', ['category_id' => 'account', 'sort_order' => 10]);
        $this->knowledgeGuide('core.other');
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/categoria/account')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Category')
            ->where('page.category.id', 'account')
            ->where('page.category.label', 'Mi cuenta')
            ->where('page.category.guideCount', 2)
            ->has('page.guides', 2)
            ->where('page.guides.0.id', 'core.a')
            ->where('page.guides.1.id', 'core.b')
            ->has('seo'));
    }

    public function test_category_without_visible_guides_or_of_the_panel_is_404(): void
    {
        $this->knowledgeGuide('core.panel', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/categoria/venue')->assertNotFound();
        $this->get('/ayuda/categoria/panel-basics')->assertNotFound();
        $this->get('/ayuda/categoria/nope')->assertNotFound();
    }

    public function test_show_renders_a_visible_guide_with_badge_and_related(): void
    {
        $this->knowledgeGuide('core.main', [
            'title' => 'Guía principal',
            'body' => "Hola **mundo**.\n",
            'related' => ['core.sibling', 'core.locked'],
            'first_seen_at' => now()->subDays(90),
            'content_changed_at' => now()->subDays(2),
        ]);
        $this->knowledgeGuide('core.sibling', ['sort_order' => 20]);
        $this->knowledgeGuide('core.locked', ['category_id' => 'venue', 'conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.main')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Show')
            ->where('guide.id', 'core.main')
            ->where('guide.title', 'Guía principal')
            ->where('guide.summary', 'Summary')
            ->where('guide.category.id', 'getting-started')
            ->where('guide.badge', 'updated')
            ->where('guide.html', fn ($html): bool => is_string($html) && str_contains($html, '<strong>mundo</strong>'))
            ->has('guide.related', 1)
            ->where('guide.related.0.id', 'core.sibling')
            ->has('guide.siblings', 2)
            ->where('guide.siblings.0.id', 'core.main')
            ->has('seo'));
    }

    public function test_show_returns_404_for_a_guide_the_user_cannot_see(): void
    {
        $this->knowledgeGuide('core.locked', ['conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->knowledgeGuide('core.hidden');
        KnowledgeGuideOverrideModel::query()
            ->create(['guide_id' => 'core.hidden', 'hidden' => true, 'featured' => false]);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.locked')->assertNotFound();
        $this->get('/ayuda/core.hidden')->assertNotFound();
        $this->get('/ayuda/core.missing')->assertNotFound();
    }

    public function test_show_returns_404_for_a_guide_of_a_disabled_module(): void
    {
        $this->knowledgeGuide('tournaments.rules', ['source' => 'module', 'module' => 'tournaments', 'active' => false]);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/tournaments.rules')->assertNotFound();
    }

    public function test_old_ids_redirect_permanently(): void
    {
        $this->knowledgeGuide('core.new');
        $this->knowledgeAlias('core.old', 'core.new');
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.old')->assertStatus(301)->assertRedirect('/ayuda/core.new');
    }

    public function test_an_old_id_whose_target_is_not_visible_is_404_without_redirect(): void
    {
        $this->knowledgeGuide('core.new', ['conditions' => ['permissions' => ['any' => ['no.such_permission']]]]);
        $this->knowledgeAlias('core.old', 'core.new');
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.old')->assertNotFound();
        $this->get('/ayuda/core.new')->assertNotFound();
    }

    public function test_an_alias_pointing_to_itself_never_loops(): void
    {
        $this->knowledgeAlias('core.loop', 'core.loop');
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.loop')->assertNotFound();
    }

    public function test_panel_guides_are_not_served_on_the_site(): void
    {
        $this->knowledgeGuide('core.panel', ['audience' => 'panel', 'category_id' => 'panel-basics']);
        $this->actingAs(UserModel::factory()->create());

        $this->get('/ayuda/core.panel')->assertNotFound();
    }

    public function test_unverified_users_are_sent_to_verification_when_required(): void
    {
        $this->knowledgeGuide('core.one');
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $this->actingAs(UserModel::factory()->unverified()->create(['created_at' => '2026-06-01 12:00:00']));

        $this->get('/ayuda')->assertRedirect(route('verification.notice'));
        $this->get('/ayuda/core.one')->assertRedirect(route('verification.notice'));
    }
}
