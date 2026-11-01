<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Knowledge;

use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Knowledge\CreatesKnowledgeRecords;
use Tests\TestCase;

final class KnowledgeBasePageTest extends TestCase
{
    use CreatesKnowledgeRecords;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeCategory('panel-basics', 'panel');
        $this->knowledgeCategory('getting-started');
        $this->knowledgeGuide('core.panel-guide', [
            'audience' => 'panel',
            'category_id' => 'panel-basics',
            'title' => 'Panel guide title',
            'body' => 'Hello **panel** world.',
            'body_text' => 'Hello panel world.',
        ]);
        $this->knowledgeGuide('core.hidden-panel', [
            'audience' => 'panel',
            'category_id' => 'panel-basics',
            'title' => 'Hidden panel title',
            'conditions' => ['permissions' => ['any' => ['no.such_permission']]],
        ]);
        $this->knowledgeGuide('core.site-guide', ['title' => 'Site guide title']);
    }

    private function signIn(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());
    }

    public function test_panel_users_see_visible_panel_guides_only(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->assertSee('El panel')
            ->assertSee('Panel guide title')
            ->assertDontSee('Hidden panel title')
            ->assertDontSee('Site guide title');
    }

    public function test_selecting_a_guide_renders_its_html(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->set('guide', 'core.panel-guide')
            ->assertSeeHtml('<strong>panel</strong>');
    }

    public function test_guide_is_read_from_the_url(): void
    {
        $this->signIn();

        Livewire::withQueryParams(['guia' => 'core.panel-guide'])
            ->test(KnowledgeBasePage::class)
            ->assertSet('guide', 'core.panel-guide')
            ->assertSeeHtml('<strong>panel</strong>');
    }

    public function test_invisible_guide_shows_an_unavailable_notice(): void
    {
        $this->signIn();

        foreach (['core.hidden-panel', 'core.site-guide', 'core.missing', 'not a valid id!'] as $id) {
            Livewire::test(KnowledgeBasePage::class)
                ->set('guide', $id)
                ->assertOk()
                ->assertSee('Esta guía no está disponible');
        }
    }

    public function test_typing_searches_and_submitting_records_a_miss(): void
    {
        $this->signIn();

        $component = Livewire::test(KnowledgeBasePage::class)
            ->set('search', 'zzzzzzqq');

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
        $component->assertSee(__('knowledge.panel.no_results'));

        $component->call('submitSearch');

        $this->assertSame(1, DB::table('knowledge_search_misses')->where('audience', 'panel')->count());
    }

    public function test_submitting_a_search_with_results_or_too_short_records_nothing(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->set('search', 'z')
            ->call('submitSearch');

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
    }

    public function test_submitting_a_search_that_finds_guides_records_nothing(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->set('search', 'panel')
            ->assertSee('Panel guide title')
            ->call('submitSearch');

        $this->assertSame(0, DB::table('knowledge_search_misses')->count());
    }

    public function test_opening_a_guide_from_search_results_shows_the_guide(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->set('search', 'panel')
            ->assertSee(__('knowledge.panel.results'))
            ->call('openGuide', 'core.panel-guide')
            ->assertSet('search', '')
            ->assertSet('guide', 'core.panel-guide')
            ->assertSeeHtml('<strong>panel</strong>')
            ->assertDontSee(__('knowledge.panel.results'));
    }

    public function test_show_home_clears_guide_and_search(): void
    {
        $this->signIn();

        Livewire::test(KnowledgeBasePage::class)
            ->set('guide', 'core.panel-guide')
            ->set('search', 'panel')
            ->call('showHome')
            ->assertSet('guide', null)
            ->assertSet('search', '')
            ->assertDontSeeHtml('<strong>panel</strong>');
    }

    public function test_users_without_panel_access_cannot_open_the_page(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get(KnowledgeBasePage::getUrl())->assertForbidden();
    }

    public function test_panel_users_can_open_the_page(): void
    {
        $this->signIn();

        $this->get(KnowledgeBasePage::getUrl())->assertOk()->assertSee('Ayuda');
    }
}
