<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Impersonation\ReadsOnly;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\ModuleUpdatesPage;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\TagResource\Pages\CreateTag;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Filament\ImpersonationFixturePage;
use Tests\TestCase;

/**
 * «Solo mirar» in the panel (core#576): while an administrator impersonates in read-only mode,
 * the panel can be browsed, but no Filament action runs and nothing is saved, in core pages
 * and in pages the core knows nothing about (a module's). In «Ejecutar y deshacer» everything
 * runs as usual.
 */
final class PanelReadOnlyTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $target;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->admin = UserModel::factory()->admin()->create();
        $this->target = $this->editorWithPermissions([
            'tags.view_any', 'tags.create', 'tags.update', 'tags.delete',
            'menu_items.view_any', 'menu_items.delete',
        ]);
        $this->target->forceFill(['name' => 'Marta Ejemplo', 'display_name' => 'Marta'])->save();
    }

    public function test_a_row_action_is_refused(): void
    {
        $tag = TagModel::factory()->create();
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ListTags::class)
            ->callAction(TestAction::make('delete')->table($tag))
            ->assertNotified($this->refusal());

        $this->assertNotNull($tag->fresh());
    }

    public function test_a_bulk_action_is_refused(): void
    {
        $item = $this->menuItem();
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ListMenuItems::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$item])
            ->assertNotified($this->refusal());

        $this->assertNotNull($item->fresh());
    }

    public function test_saving_a_resource_is_refused(): void
    {
        $tag = TagModel::factory()->create(['name' => 'Rol']);
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->fillForm(['name' => 'Wargames'])
            ->call('save')
            ->assertNotified($this->refusal());

        $this->assertSame('Rol', $tag->fresh()?->name);
    }

    public function test_creating_a_record_is_refused(): void
    {
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'Wargames', 'slug' => 'wargames'])
            ->call('create')
            ->assertNotified($this->refusal());

        $this->assertSame(0, TagModel::query()->count());
    }

    /**
     * A module's page: header actions with and without a modal, an action with a form and a
     * settings form saved by a public method, none of them written with impersonation in mind.
     */
    public function test_a_modules_actions_and_settings_form_are_refused(): void
    {
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('run')
            ->assertNotified($this->refusal())
            ->assertSet('ran', false);

        // Refused on submit, so its modal stays open, as after a failed validation
        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('confirmRun')
            ->assertNotified($this->refusal())
            ->assertSet('ran', false)
            ->assertActionMounted('confirmRun');

        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('addNote', data: ['note' => 'Hola'])
            ->assertNotified($this->refusal())
            ->assertSet('note', null);

        Livewire::test(ImpersonationFixturePage::class)
            ->fillForm(['club_name' => 'Otro'])
            ->call('save')
            ->assertNotified($this->refusal())
            ->assertSet('saved', null);
    }

    public function test_a_method_left_out_of_every_list_is_refused(): void
    {
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ImpersonationFixturePage::class)
            ->call('_startUpload', 'data.club_name', [], false)
            ->assertNotified($this->refusal());
    }

    public function test_browsing_still_works(): void
    {
        $tag = TagModel::factory()->create();
        TagModel::factory()->create();
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ListTags::class)
            ->filterTable('applies_to', 'events')
            ->sortTable('name')
            ->searchTable($tag->name)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->call('previousPage')
            ->assertSet('paginators.page', 1)
            ->assertNotNotified($this->refusal());
    }

    public function test_modals_and_forms_still_open(): void
    {
        $tag = TagModel::factory()->create();
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ListTags::class)
            ->mountAction(TestAction::make(RowDetailsAction::NAME)->table($tag))
            ->assertActionMounted(TestAction::make(RowDetailsAction::NAME)->table($tag))
            ->assertNotNotified($this->refusal());

        Livewire::test(ListTags::class)
            ->mountAction(TestAction::make('delete')->table($tag))
            ->assertActionMounted(TestAction::make('delete')->table($tag))
            ->assertNotNotified($this->refusal());

        Livewire::test(ImpersonationFixturePage::class)
            ->mountAction('addNote')
            ->assertActionMounted('addNote')
            ->assertNotNotified($this->refusal());
    }

    /**
     * Refusing an action that runs on click must leave the page as it was, so the next modal
     * opens as usual.
     */
    public function test_a_modal_opens_after_an_action_was_refused(): void
    {
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ImpersonationFixturePage::class)
            ->mountAction('run')
            ->assertSet('ran', false)
            ->assertSet('mountedActions', [])
            ->mountAction('addNote')
            ->assertActionMounted('addNote');
    }

    public function test_what_is_marked_as_only_reading_runs(): void
    {
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('peek')
            ->assertSet('peeked', true)
            ->call('showSection', 'general')
            ->assertSet('section', 'general')
            ->assertNotNotified($this->refusal());
    }

    public function test_everything_runs_while_simulating(): void
    {
        $tag = TagModel::factory()->create(['name' => 'Rol']);
        $this->impersonate(ImpersonationMode::Simulate);

        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('run')
            ->assertSet('ran', true)
            ->call('save')
            ->assertSet('saved', 'Gremio')
            ->assertNotNotified($this->refusal());

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->fillForm(['name' => 'Wargames'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Wargames', $tag->fresh()?->name);
    }

    public function test_nothing_changes_without_an_impersonation(): void
    {
        $this->actingAs($this->target);

        Livewire::test(ImpersonationFixturePage::class)
            ->callAction('run')
            ->assertSet('ran', true)
            ->call('save')
            ->assertSet('saved', 'Gremio');
    }

    public function test_the_media_library_layout_still_switches(): void
    {
        $this->target = $this->editorWithPermissions(['media.view_any']);
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(MediaLibraryPage::class)
            ->set('display', 'grid')
            ->callAction('toggleLayout')
            ->assertSet('display', 'list')
            ->assertNotNotified($this->refusal());
    }

    /**
     * Core pages whose own methods only change what they show.
     */
    #[DataProvider('coreMethodsThatOnlyRead')]
    public function test_core_pages_mark_what_only_changes_the_view(string $page, string $method): void
    {
        $this->assertNotSame([], (new ReflectionMethod($page, $method))->getAttributes(ReadsOnly::class));
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function coreMethodsThatOnlyRead(): array
    {
        return [
            'help: open a guide' => [KnowledgeBasePage::class, 'openGuide'],
            'help: home' => [KnowledgeBasePage::class, 'showHome'],
            'help: search' => [KnowledgeBasePage::class, 'submitSearch'],
            'modules: filter' => [ModulesPage::class, 'setFilter'],
            'modules: ask before uninstalling' => [ModulesPage::class, 'confirmUninstall'],
            'modules: do not uninstall' => [ModulesPage::class, 'cancelUninstall'],
            'module updates: follow the queue' => [ModuleUpdatesPage::class, 'pollUpdates'],
        ];
    }

    public function test_core_writes_are_not_marked(): void
    {
        foreach ([[ModulesPage::class, 'uninstallModule'], [ModulesPage::class, 'enableModule'], [ModuleUpdatesPage::class, 'updateModule']] as [$page, $method]) {
            $this->assertSame([], (new ReflectionMethod($page, $method))->getAttributes(ReadsOnly::class), $method);
        }
    }

    public function test_the_table_shortcuts_of_older_pages_still_open_modals(): void
    {
        $tag = TagModel::factory()->create();
        $item = $this->menuItem();
        $this->impersonate(ImpersonationMode::ReadOnly);

        Livewire::test(ListTags::class)
            ->mountTableAction(RowDetailsAction::NAME, $tag)
            ->assertActionMounted(TestAction::make(RowDetailsAction::NAME)->table($tag))
            ->assertNotNotified($this->refusal());

        Livewire::test(ListMenuItems::class)
            ->mountTableBulkAction('delete', [$item])
            ->assertNotNotified($this->refusal());
    }

    /**
     * The request as ApplyImpersonation leaves it: the target in the guard and the
     * impersonation in the context.
     */
    private function impersonate(ImpersonationMode $mode): void
    {
        $this->actingAs($this->target);
        app(ImpersonationContext::class)->activate($this->admin, $this->target, $mode, 'log-id');
    }

    private function refusal(): string
    {
        return __('filament.impersonation_read_only.refused', ['name' => 'Marta']);
    }

    private function menuItem(): MenuItemModel
    {
        return MenuItemModel::factory()->create([
            'location' => MenuLocation::Header,
            'label' => 'Torneos',
            'route' => 'tournaments.index',
            'module' => 'tournaments',
        ]);
    }
}
