<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Actions\RowDetailsAction;
use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\EventResource\Pages\ListEvents;
use App\Filament\Resources\GalleryResource\Pages\ListGalleries;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Filament\Resources\KnowledgeGuideResource\Pages\ListKnowledgeGuides;
use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\RecentArticlesWidget;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Panel tables fit their container (core#383): row actions sit in one menu, secondary columns
 * show from the width where they fit and the main text column wraps instead of widening the table.
 */
final class PanelTablesFitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    /**
     * @return array<string, array{class-string<HasTable>, list<string>}>
     */
    public static function rowActionMenus(): array
    {
        return [
            'help guides' => [ListKnowledgeGuides::class, ['rowDetails', 'edit', 'hide', 'show', 'feature', 'delete']],
            'tags' => [ListTags::class, ['rowDetails', 'edit', 'delete']],
            'users' => [ListUsers::class, ['rowDetails', 'edit', 'sendVerificationEmail', 'sendPasswordResetEmail', 'deactivate', 'restore', 'anonymize']],
            'menu items' => [ListMenuItems::class, ['rowDetails', 'edit', 'delete']],
            'roles' => [ListRoles::class, ['rowDetails', 'edit', 'delete']],
        ];
    }

    /**
     * Labelled row actions took 120-200 px and pushed the table past its container: they go in
     * one menu, which takes the room of a single icon, and none of them is lost on the way.
     *
     * @param  class-string<HasTable>  $page
     * @param  list<string>  $expected
     */
    #[DataProvider('rowActionMenus')]
    public function test_row_actions_sit_in_a_single_menu(string $page, array $expected): void
    {
        $actions = $this->table($page)->getRecordActions();

        $this->assertCount(1, $actions);
        $group = $actions[0];
        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertEqualsCanonicalizing($expected, array_keys($group->getFlatActions()));
    }

    /**
     * Tables whose only row action is «Ver detalles»: the whole row opens the record, so it shows
     * as one icon instead of a menu with a single entry.
     *
     * @return array<string, array{class-string<HasTable>}>
     */
    public static function tablesWithOnlyRowDetails(): array
    {
        return [
            'events' => [ListEvents::class],
            'articles' => [ListArticles::class],
            'galleries' => [ListGalleries::class],
            'hero slides' => [ListHeroSlides::class],
            'recent articles' => [RecentArticlesWidget::class],
        ];
    }

    /**
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('tablesWithOnlyRowDetails')]
    public function test_row_details_is_the_only_row_action_where_there_was_none(string $page): void
    {
        $actions = $this->table($page)->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(Action::class, $actions[0]);
        $this->assertSame(RowDetailsAction::NAME, $actions[0]->getName());
    }

    /**
     * Every list table of the panel.
     *
     * @return array<string, array{class-string<HasTable>}>
     */
    public static function panelTables(): array
    {
        return [
            'events' => [ListEvents::class],
            'help guides' => [ListKnowledgeGuides::class],
            'tags' => [ListTags::class],
            'users' => [ListUsers::class],
            'menu items' => [ListMenuItems::class],
            'hero slides' => [ListHeroSlides::class],
            'articles' => [ListArticles::class],
            'galleries' => [ListGalleries::class],
            'roles' => [ListRoles::class],
            'recent articles' => [RecentArticlesWidget::class],
        ];
    }

    /**
     * A column hidden below some width must still be readable on a phone (core#399).
     *
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('panelTables')]
    public function test_tables_that_hide_columns_offer_row_details(string $page): void
    {
        $table = $this->table($page);
        $hidesColumns = array_filter($table->getColumns(), fn (Column $column): bool => $column->getVisibleFrom() !== null);

        $this->assertNotEmpty($hidesColumns, 'Every panel table hides some column; update the list otherwise');
        $this->assertInstanceOf(Action::class, $table->getAction(RowDetailsAction::NAME));
    }

    /**
     * Icons and colour swatches sit in the middle of their column, under its heading.
     *
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('panelTables')]
    public function test_icon_columns_are_centred(string $page): void
    {
        foreach ($this->table($page)->getColumns() as $column) {
            if ($column instanceof IconColumn || $column instanceof ColorColumn) {
                $this->assertSame(Alignment::Center, $column->getAlignment(), "Column {$column->getName()} is not centred");
            }
        }
    }

    /**
     * Short counters are centred too; text and badges stay at the start.
     *
     * @return array<string, array{class-string<HasTable>, string}>
     */
    public static function counterColumns(): array
    {
        return [
            'guides helpful' => [ListKnowledgeGuides::class, 'helpful'],
            'guides not helpful' => [ListKnowledgeGuides::class, 'not_helpful'],
            'tags events' => [ListTags::class, 'events_count'],
            'tags articles' => [ListTags::class, 'articles_count'],
            'tags galleries' => [ListTags::class, 'galleries_count'],
            'tags order' => [ListTags::class, 'sort_order'],
            'menu order' => [ListMenuItems::class, 'sort_order'],
            'hero slides order' => [ListHeroSlides::class, 'sort_order'],
            'galleries photos' => [ListGalleries::class, 'photos_count'],
            'roles users' => [ListRoles::class, 'users_count'],
            'roles permissions' => [ListRoles::class, 'permissions_count'],
        ];
    }

    /**
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('counterColumns')]
    public function test_counters_are_centred(string $page, string $column): void
    {
        $this->assertSame(Alignment::Center, $this->column($page, $column)->getAlignment());
    }

    /**
     * The breakpoint each column shows from (null: always). Widths measured with the sidebar
     * open: the table has ~343 px on a phone, ~608-640 px from sm to lg (the fixed sidebar takes
     * 320 px from lg), 896 px from xl and 1152 px from 2xl.
     *
     * @return array<string, array{class-string<HasTable>, string, string|null}>
     */
    public static function columnBreakpoints(): array
    {
        return [
            'events title' => [ListEvents::class, 'title', null],
            'events published' => [ListEvents::class, 'is_published', null],
            'events date' => [ListEvents::class, 'start_date', 'sm'],
            'events image' => [ListEvents::class, 'image_public_id', 'md'],
            'events location' => [ListEvents::class, 'location', 'xl'],

            'guides title' => [ListKnowledgeGuides::class, 'title', null],
            'guides status' => [ListKnowledgeGuides::class, 'status', null],
            'guides source' => [ListKnowledgeGuides::class, 'source', 'sm'],
            'guides featured' => [ListKnowledgeGuides::class, 'is_featured', 'md'],
            'guides audience' => [ListKnowledgeGuides::class, 'audience', 'xl'],
            'guides helpful' => [ListKnowledgeGuides::class, 'helpful', 'xl'],
            'guides not helpful' => [ListKnowledgeGuides::class, 'not_helpful', 'xl'],
            'guides category' => [ListKnowledgeGuides::class, 'category_id', '2xl'],

            'tags name' => [ListTags::class, 'name', null],
            'tags color' => [ListTags::class, 'color', null],
            'tags applies to' => [ListTags::class, 'applies_to', 'sm'],
            'tags events' => [ListTags::class, 'events_count', 'xl'],
            'tags articles' => [ListTags::class, 'articles_count', 'xl'],
            'tags galleries' => [ListTags::class, 'galleries_count', 'xl'],

            'users name' => [ListUsers::class, 'name', null],
            'users status' => [ListUsers::class, 'status', null],
            'users email' => [ListUsers::class, 'email', 'sm'],
            'users avatar' => [ListUsers::class, 'avatar_public_id', 'md'],
            'users roles' => [ListUsers::class, 'roles.display_name', 'xl'],
            'users verified' => [ListUsers::class, 'email_verified_at', 'xl'],
            'users display name' => [ListUsers::class, 'display_name', '2xl'],

            'menu label' => [ListMenuItems::class, 'label', null],
            'menu active' => [ListMenuItems::class, 'is_active', null],
            'menu visibility' => [ListMenuItems::class, 'visibility', 'sm'],
            'menu module' => [ListMenuItems::class, 'module', 'md'],
            'menu link' => [ListMenuItems::class, 'route', 'xl'],

            'hero slides title' => [ListHeroSlides::class, 'title', null],
            'hero slides active' => [ListHeroSlides::class, 'is_active', null],
            'hero slides image' => [ListHeroSlides::class, 'image_public_id', 'sm'],
            'hero slides order' => [ListHeroSlides::class, 'sort_order', 'sm'],
            'hero slides subtitle' => [ListHeroSlides::class, 'subtitle', 'xl'],

            'articles title' => [ListArticles::class, 'title', null],
            'articles published' => [ListArticles::class, 'is_published', null],
            'articles publication date' => [ListArticles::class, 'published_at', 'sm'],
            'articles image' => [ListArticles::class, 'featured_image_public_id', 'md'],
            'articles author' => [ListArticles::class, 'author.display_name', 'xl'],

            'galleries title' => [ListGalleries::class, 'title', null],
            'galleries published' => [ListGalleries::class, 'is_published', null],
            'galleries photos' => [ListGalleries::class, 'photos_count', 'sm'],
            'galleries featured' => [ListGalleries::class, 'is_featured', 'sm'],

            'roles display name' => [ListRoles::class, 'display_name', null],
            'roles users' => [ListRoles::class, 'users_count', null],
            'roles permissions' => [ListRoles::class, 'permissions_count', 'sm'],
            'roles protected' => [ListRoles::class, 'is_protected', 'sm'],
            'roles name' => [ListRoles::class, 'name', 'md'],
            'roles requires member' => [ListRoles::class, 'requires_association_member', 'xl'],

            'recent articles title' => [RecentArticlesWidget::class, 'title', null],
            'recent articles published' => [RecentArticlesWidget::class, 'published_at', null],
            'recent articles author' => [RecentArticlesWidget::class, 'author.display_name', 'sm'],
        ];
    }

    /**
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('columnBreakpoints')]
    public function test_columns_show_from_the_width_where_they_fit(string $page, string $column, ?string $breakpoint): void
    {
        $this->assertSame($breakpoint, $this->column($page, $column)->getVisibleFrom());
    }

    /**
     * A title limited to 50 characters but kept on one line measured up to ~340 px: wrapping it
     * lets the main column narrow down to what is left.
     *
     * @return array<string, array{class-string<HasTable>, string}>
     */
    public static function wrappedColumns(): array
    {
        return [
            'events' => [ListEvents::class, 'title'],
            'guides' => [ListKnowledgeGuides::class, 'title'],
            'tags' => [ListTags::class, 'name'],
            'hero slides' => [ListHeroSlides::class, 'title'],
            'articles' => [ListArticles::class, 'title'],
            'galleries' => [ListGalleries::class, 'title'],
            'roles' => [ListRoles::class, 'display_name'],
            'recent articles' => [RecentArticlesWidget::class, 'title'],
        ];
    }

    /**
     * @param  class-string<HasTable>  $page
     */
    #[DataProvider('wrappedColumns')]
    public function test_the_main_column_wraps(string $page, string $column): void
    {
        $textColumn = $this->column($page, $column);
        $this->assertInstanceOf(TextColumn::class, $textColumn);

        $this->assertTrue($textColumn->canWrap());
    }

    /**
     * The media library list (its grid is a content grid of cards): one menu with «Ver detalles»
     * first, secondary columns from the width where they fit, the title wrapping and the usage
     * counter centred.
     */
    public function test_media_library_list_fits(): void
    {
        $component = Livewire::test(MediaLibraryPage::class)->callAction('toggleLayout')->instance();
        $this->assertInstanceOf(HasTable::class, $component);
        $table = $component->getTable();

        $actions = $table->getRecordActions();
        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame([RowDetailsAction::NAME, 'viewDetails', 'delete'], array_keys($actions[0]->getFlatActions()));

        $breakpoints = [];
        foreach ($table->getColumns() as $column) {
            $breakpoints[$column->getName()] = $column->getVisibleFrom();
        }
        $this->assertSame([
            'thumbnail' => null,
            'title' => null,
            'origin' => 'md',
            'bytes' => 'sm',
            'usages_count' => null,
            'uploaded_at' => 'xl',
            'status' => null,
        ], $breakpoints);

        $title = $table->getColumn('title');
        $this->assertInstanceOf(TextColumn::class, $title);
        $this->assertTrue($title->canWrap());
        $this->assertSame(Alignment::Center, $table->getColumn('usages_count')?->getAlignment());
        $this->assertSame('xl:hidden', RowDetailsAction::hiddenFromClass($table));
    }

    /**
     * The grid shows cards (three from md, four from xl); a card opens its details and its menu has no
     * «Ver detalles», since cards hide no column.
     */
    public function test_media_library_grid_uses_cards(): void
    {
        $component = Livewire::test(MediaLibraryPage::class)->instance();
        $this->assertInstanceOf(HasTable::class, $component);
        $table = $component->getTable();

        $this->assertSame(['md' => 3, 'xl' => 4], $table->getContentGrid());
        $actions = $table->getRecordActions();
        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame(['viewDetails', 'delete'], array_keys($actions[0]->getFlatActions()));
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

    /**
     * @param  class-string<HasTable>  $page
     */
    private function column(string $page, string $name): Column
    {
        $column = $this->table($page)->getColumn($name);
        $this->assertInstanceOf(Column::class, $column, "Column {$name} not found");

        return $column;
    }
}
