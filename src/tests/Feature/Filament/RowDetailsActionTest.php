<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\GalleryResource\Pages\ListGalleries;
use App\Filament\Resources\KnowledgeGuideResource\Pages\ListKnowledgeGuides;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Filament\ArrayRecordsTableFixtureWidget;
use Tests\TestCase;

/**
 * «Ver detalles» (core#399): what a table hides on narrow screens can still be read from the row.
 */
final class RowDetailsActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private KnowledgeGuideModel $guide;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        $this->actingAs(UserModel::factory()->admin()->create());

        KnowledgeCategoryModel::query()->create(['category_id' => 'community', 'module' => null, 'audience' => 'site', 'sort_order' => 1, 'icon' => 'heroicon-o-users', 'label_key' => 'knowledge.categories.community']);
        $this->guide = KnowledgeGuideModel::query()->create([
            'guide_id' => 'core.signup', 'locale' => 'es', 'source' => 'core', 'module' => null, 'audience' => 'site',
            'category_id' => 'community', 'sort_order' => 1, 'title' => 'Darte de alta', 'summary' => 'Cómo crear tu cuenta.',
            'keywords' => [], 'body_format' => 'markdown', 'body' => 'Texto.', 'body_text' => 'Texto.', 'conditions' => [],
            'related' => [], 'featured' => false, 'content_hash' => str_repeat('a', 40), 'active' => true,
        ]);
        DB::table('knowledge_guide_feedback')->insert([
            'id' => (string) Str::uuid(), 'guide_id' => 'core.signup',
            'day' => app(TimezoneServiceInterface::class)->today()->format('Y-m-d'),
            'helpful_count' => 7, 'not_helpful_count' => 2,
        ]);
    }

    public function test_the_panel_lists_every_column_of_the_row_with_its_value(): void
    {
        $entries = RowDetailsAction::entries($this->table(ListKnowledgeGuides::class), $this->guide);

        $this->assertSame(
            ['title', 'source', 'audience', 'category_id', 'status', 'is_featured', 'helpful', 'not_helpful'],
            array_column($entries, 'name'),
        );
        $byName = array_column($entries, null, 'name');
        $this->assertSame('Útil', $byName['helpful']['label']);
        $this->assertStringContainsString('7', $byName['helpful']['html']->toHtml());
        $this->assertStringContainsString('2', $byName['not_helpful']['html']->toHtml());
        // The main column keeps its description
        $this->assertStringContainsString('Cómo crear tu cuenta.', $byName['title']['html']->toHtml());
    }

    public function test_the_panel_leaves_the_table_columns_as_they_were(): void
    {
        $table = $this->table(ListKnowledgeGuides::class);

        RowDetailsAction::entries($table, $this->guide);

        // Values sit at the start of the panel, but the table keeps its own alignment
        $this->assertSame(Alignment::Center, $table->getColumn('is_featured')?->getAlignment());
        $this->assertSame(Alignment::Center, $table->getColumn('helpful')?->getAlignment());
    }

    public function test_opening_it_shows_the_row_even_without_an_edit_page(): void
    {
        // Official guides cannot be edited: the panel is the only place their votes show on a phone
        $list = Livewire::test(ListKnowledgeGuides::class)
            ->mountTableAction(RowDetailsAction::NAME, $this->guide);

        $component = $list->instance();
        $this->assertInstanceOf(HasTable::class, $component);
        $action = $component->getTable()->getAction(RowDetailsAction::NAME);
        $this->assertInstanceOf(Action::class, $action);
        $action->record($this->guide);
        $this->assertFalse($action->isHidden());

        $content = $action->getModalContent();
        $this->assertInstanceOf(Htmlable::class, $content);
        $html = $content->toHtml();
        $this->assertStringContainsString('data-row-details-entry="helpful"', $html);
        $this->assertStringContainsString('data-row-details-entry="not_helpful"', $html);
        $this->assertStringContainsString('Darte de alta', $html);
    }

    public function test_a_blank_cell_is_marked_empty(): void
    {
        $event = EventModel::factory()->create(['location' => null, 'image_public_id' => null]);

        $byName = array_column(RowDetailsAction::entries($this->table(ListEvents::class), $event), null, 'name');

        $this->assertTrue($byName['location']['empty']);
        $this->assertTrue($byName['image_public_id']['empty']);
        $this->assertFalse($byName['title']['empty']);
        // An icon is a value, not a blank
        $this->assertFalse($byName['is_published']['empty']);
    }

    /**
     * Rows that come from `->records()` are arrays, not models (core#403).
     */
    public function test_it_reads_rows_that_are_arrays(): void
    {
        $widget = Livewire::test(ArrayRecordsTableFixtureWidget::class)
            ->mountTableAction(RowDetailsAction::NAME, 'first');

        $component = $widget->instance();
        $this->assertInstanceOf(HasTable::class, $component);
        $table = $component->getTable();
        // The row as Filament hands it to the action: the array plus its key
        $record = $component->getTableRecord('first');
        $this->assertIsArray($record);

        $byName = array_column(RowDetailsAction::entries($table, $record), null, 'name');
        $this->assertSame(['title', 'kind', 'pending'], array_keys($byName));
        $this->assertStringContainsString('3', $byName['pending']['html']->toHtml());

        $action = $table->getAction(RowDetailsAction::NAME);
        $this->assertInstanceOf(Action::class, $action);
        $content = $action->record($record)->getModalContent();
        $this->assertInstanceOf(Htmlable::class, $content);
        $this->assertStringContainsString('data-row-details-entry="pending"', $content->toHtml());
        $this->assertStringContainsString('Mesa del sábado', $content->toHtml());
    }

    public function test_it_hides_from_the_width_where_every_column_already_shows(): void
    {
        $this->assertSame('2xl:hidden', RowDetailsAction::hiddenFromClass($this->table(ListKnowledgeGuides::class)));
        $this->assertSame('2xl:hidden', RowDetailsAction::hiddenFromClass($this->table(ListEvents::class)));
        $this->assertSame('sm:hidden', RowDetailsAction::hiddenFromClass($this->table(ListGalleries::class)));
    }

    /**
     * @param  class-string<HasTable>  $page
     */
    private function table(string $page): Table
    {
        $component = Livewire::test($page)->instance();
        $this->assertInstanceOf(HasTable::class, $component);

        return $component->getTable();
    }
}
