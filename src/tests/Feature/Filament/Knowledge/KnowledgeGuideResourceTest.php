<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Knowledge;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Forms\RichEditor\KnowledgeActionBlock;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Filament\Resources\KnowledgeGuideResource\Pages\CreateKnowledgeGuide;
use App\Filament\Resources\KnowledgeGuideResource\Pages\EditKnowledgeGuide;
use App\Filament\Resources\KnowledgeGuideResource\Pages\ListKnowledgeGuides;
use App\Filament\Resources\KnowledgeGuideResource\Widgets\KnowledgeSearchMissesWidget;
use App\Filament\Support\KnowledgeActionTargets;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideOverrideModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

final class KnowledgeGuideResourceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        KnowledgeCategoryModel::query()->create(['category_id' => 'community', 'module' => null, 'audience' => 'site', 'sort_order' => 1, 'icon' => 'heroicon-o-users', 'label_key' => 'knowledge.categories.community']);
        KnowledgeCategoryModel::query()->create(['category_id' => 'content', 'module' => null, 'audience' => 'panel', 'sort_order' => 1, 'icon' => 'heroicon-o-document', 'label_key' => 'knowledge.categories.content']);
    }

    public function test_the_list_needs_knowledge_manage(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any']));
        $this->get(KnowledgeGuideResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));
        // Labels the panel guide core.panel.help-guides names
        $this->get(KnowledgeGuideResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Crear guía de ayuda');
    }

    public function test_the_list_shows_official_and_site_guides_with_their_votes(): void
    {
        $official = $this->guide('core.signup', 'Darte de alta');
        $this->guide('core.gone', 'Guía retirada', active: false);
        $this->guide('core.signup', 'Sign up', locale: 'en');
        $draft = $this->guide('site.house-rules', 'Normas de la casa', source: 'site', active: false);
        DB::table('knowledge_guide_feedback')->insert([
            'id' => (string) Str::uuid(), 'guide_id' => 'core.signup',
            'day' => app(TimezoneServiceInterface::class)->today()->format('Y-m-d'),
            'helpful_count' => 7, 'not_helpful_count' => 2,
        ]);
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        $list = Livewire::test(ListKnowledgeGuides::class)
            ->assertCanSeeTableRecords([$official, $draft])
            ->assertCountTableRecords(2)
            ->assertTableColumnStateSet('helpful', 7, $official)
            ->assertTableColumnStateSet('not_helpful', 2, $official)
            ->assertTableColumnStateSet('status', 'draft', $draft);

        $this->assertFalse($this->rowActionHidden($list, 'edit', $draft));
        $this->assertTrue($this->rowActionHidden($list, 'edit', $official));
        $this->assertTrue($this->rowActionHidden($list, 'delete', $official));
        $this->assertFalse($this->rowActionHidden($list, 'hide', $official));
        $this->assertTrue($this->rowActionHidden($list, 'hide', $draft));
        $this->assertTrue($this->rowActionHidden($list, 'feature', $draft));
    }

    /**
     * Whether a row action is hidden for the given record. The row actions sit in a menu, and
     * Filament caches a grouped action's visibility by the record's object id: the table test
     * helpers load a fresh copy of the record, which may reuse the id of a row already rendered
     * and freed, and read that row's visibility. Checking with the test's own record avoids it.
     *
     * @param  Testable<ListKnowledgeGuides>  $list
     */
    private function rowActionHidden(Testable $list, string $name, KnowledgeGuideModel $record): bool
    {
        $action = $list->instance()->getTable()->getAction($name);
        $this->assertInstanceOf(Action::class, $action, "Row action {$name} not found");

        return $action->record($record)->isHidden();
    }

    public function test_official_guides_are_hidden_and_starred_without_editing_them(): void
    {
        $official = $this->guide('core.signup', 'Darte de alta', featured: true);
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        Livewire::test(ListKnowledgeGuides::class)
            ->callTableAction('hide', $official)
            ->assertHasNoTableActionErrors()
            ->assertTableActionVisible('show', $official->fresh())
            ->callTableAction('feature', $official, ['featured' => 'not_featured'])
            ->assertHasNoTableActionErrors();

        $override = KnowledgeGuideOverrideModel::query()->where('guide_id', 'core.signup')->sole();
        $this->assertTrue($override->hidden);
        $this->assertFalse($override->featured);
        $this->assertSame('Darte de alta', $official->refresh()->title);
        $this->assertTrue($official->refresh()->featured);

        $this->get(KnowledgeGuideResource::getUrl('edit', ['record' => $official]))->assertForbidden();
    }

    public function test_a_site_guide_is_created_from_the_form(): void
    {
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        Livewire::test(CreateKnowledgeGuide::class)
            ->fillForm([
                'title' => 'Cómo reservamos el local',
                'summary' => 'Los pasos para pedir el local.',
                'audience' => 'site',
                'category_id' => 'community',
                'sort_order' => 20,
                'keywords' => ['local', 'reserva'],
                'active' => true,
                'body' => '<p>Escribe a la junta de <span data-type="mergeTag" data-id="site_name"></span>.</p>',
                'permissions_any' => ['events.create'],
                'association_member' => 'members',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guide = KnowledgeGuideModel::query()->where('guide_id', 'site.como-reservamos-el-local')->sole();
        $this->assertSame('site', $guide->source);
        $this->assertSame(['local', 'reserva'], $guide->keywords);
        $this->assertSame(['association_member' => true, 'permissions' => ['any' => ['events.create']]], $guide->conditions);
        $this->assertStringContainsString('data-type="mergeTag"', $guide->body);
        $this->assertTrue($guide->active);
    }

    public function test_the_form_rejects_a_category_of_another_audience_and_unknown_permissions(): void
    {
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        Livewire::test(CreateKnowledgeGuide::class)
            ->fillForm([
                'title' => 'Normas',
                'summary' => 'Resumen.',
                'audience' => 'site',
                'category_id' => 'content',
                'body' => '<p>Texto.</p>',
                'permissions_all' => ['no.such_permission'],
            ])
            ->call('create')
            ->assertHasFormErrors(['category_id', 'permissions_all']);

        $this->assertSame(0, KnowledgeGuideModel::query()->count());
    }

    public function test_a_site_guide_is_edited_and_keeps_its_id(): void
    {
        $guide = $this->guide('site.house-rules', 'Normas', source: 'site');
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        Livewire::test(EditKnowledgeGuide::class, ['record' => $guide->getRouteKey()])
            ->assertFormSet(['title' => 'Normas', 'audience' => 'site', 'association_member' => 'any'])
            ->fillForm(['title' => 'Normas de convivencia', 'audience' => 'panel', 'category_id' => 'content'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $guide->refresh();
        $this->assertSame('site.house-rules', $fresh->guide_id);
        $this->assertSame('Normas de convivencia', $fresh->title);
        $this->assertSame('panel', $fresh->audience);
    }

    public function test_deleting_a_site_guide_needs_the_second_confirmation(): void
    {
        $guide = $this->guide('site.house-rules', 'Normas', source: 'site');
        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));

        Livewire::test(ListKnowledgeGuides::class)
            ->callTableAction(DeleteAction::class, $guide, ['confirmation' => false])
            ->assertHasTableActionErrors(['confirmation']);
        $this->assertNotNull($guide->fresh());

        Livewire::test(ListKnowledgeGuides::class)
            ->callTableAction(DeleteAction::class, $guide, ['confirmation' => true])
            ->assertHasNoTableActionErrors();
        $this->assertNull($guide->fresh());
    }

    public function test_help_searches_without_results_are_listed_below_the_guides(): void
    {
        DB::table('knowledge_search_misses')->insert([
            'id' => (string) Str::uuid(), 'normalized_query' => 'aforo del local', 'audience' => 'site',
            'day' => app(TimezoneServiceInterface::class)->today()->format('Y-m-d'), 'count' => 4,
        ]);

        $this->actingAs($this->editorWithPermissions(['events.view_any']));
        $this->assertFalse(KnowledgeSearchMissesWidget::canView());

        $this->actingAs($this->editorWithPermissions(['knowledge.manage']));
        $this->assertTrue(KnowledgeSearchMissesWidget::canView());
        Livewire::test(KnowledgeSearchMissesWidget::class)
            ->assertSee('aforo del local')
            ->assertSee('Web (personas con cuenta)');
    }

    public function test_button_targets_offer_site_and_panel_pages_without_required_parameters(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $grouped = KnowledgeActionTargets::grouped();
        $all = array_merge(...array_values($grouped));

        $this->assertArrayHasKey('events.index', $all);
        $this->assertArrayHasKey('profile.show', $all);
        $this->assertArrayHasKey(KnowledgeGuideResource::getRouteBaseName().'.index', $all);
        $this->assertArrayNotHasKey('legal.show:aviso-legal', $all);
        $this->assertArrayNotHasKey(KnowledgeGuideResource::getRouteBaseName().'.edit', $all);
        $this->assertSame('Guías de ayuda', KnowledgeActionTargets::label(KnowledgeGuideResource::getRouteBaseName().'.index'));
    }

    public function test_block_preview_escapes_its_label(): void
    {
        $html = KnowledgeActionBlock::toPreviewHtml(['target' => 'events.index', 'label' => '<b>Ver</b>']);

        $this->assertStringContainsString('&lt;b&gt;Ver&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Ver', $html);
    }

    public function test_editor_is_granted_knowledge_manage_by_migration(): void
    {
        $editor = RoleModel::query()->create(['name' => 'editor', 'display_name' => 'Editor']);

        $this->runMigrationFile(database_path('migrations/2026_11_06_100002_grant_knowledge_manage_to_editor.php'));

        $this->assertTrue($editor->permissions()->where('key', 'knowledge.manage')->exists());
        $entry = RoleAuditEntryModel::query()->where('role_id', $editor->id)->latest('created_at')->firstOrFail();
        $this->assertSame(['permissions_added' => ['knowledge.manage']], $entry->changes);
    }

    public function test_featured_override_migration_keeps_old_rows_meaning_as_the_file_says(): void
    {
        $this->runMigrationFile(database_path('migrations/2026_11_06_100001_make_knowledge_override_featured_nullable.php'), 'down');
        DB::table('knowledge_guide_overrides')->insert(['id' => (string) Str::uuid(), 'guide_id' => 'core.a', 'hidden' => true, 'featured' => false]);
        DB::table('knowledge_guide_overrides')->insert(['id' => (string) Str::uuid(), 'guide_id' => 'core.b', 'hidden' => false, 'featured' => true]);

        $this->runMigrationFile(database_path('migrations/2026_11_06_100001_make_knowledge_override_featured_nullable.php'));

        $this->assertNull(DB::table('knowledge_guide_overrides')->where('guide_id', 'core.a')->value('featured'));
        $this->assertTrue((bool) DB::table('knowledge_guide_overrides')->where('guide_id', 'core.b')->value('featured'));
    }

    private function guide(string $id, string $title, string $source = 'core', bool $active = true, string $locale = 'es', bool $featured = false): KnowledgeGuideModel
    {
        return KnowledgeGuideModel::query()->create([
            'guide_id' => $id, 'locale' => $locale, 'source' => $source, 'module' => null, 'audience' => 'site',
            'category_id' => 'community', 'sort_order' => 1, 'title' => $title, 'summary' => 'Resumen.',
            'keywords' => [], 'body_format' => $source === 'site' ? 'html' : 'markdown',
            'body' => $source === 'site' ? '<p>Texto.</p>' : 'Texto.', 'body_text' => 'Texto.', 'conditions' => [],
            'related' => [], 'featured' => $featured, 'content_hash' => str_repeat('a', 40), 'active' => $active,
        ]);
    }
}
