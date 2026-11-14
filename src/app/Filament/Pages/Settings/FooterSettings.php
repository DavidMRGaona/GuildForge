<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Navigation\Services\FooterMenusInterface;
use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Support\PanelNavigationGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use JsonException;
use Throwable;

/**
 * Composes the public footer by columns of site blocks (core#697), stored as `footer_layout`.
 *
 * The block definitions come from every valid footer block (SiteBlockRegistryInterface::forPlacement),
 * available or not, so a stored block whose provider is down keeps its form and its settings; only the
 * available ones can be added. A stored block without a form (nobody registers its key now, or its
 * settings cannot be built) keeps its title and settings untouched in a hidden field, so saving never
 * drops or empties it; that hidden copy is read for those stored blocks only.
 *
 * @property Schema $form
 */
final class FooterSettings extends Page implements HasForms
{
    use ChecksPermissions;
    use HasKnowledgeGuideAction;
    use InteractsWithForms;

    public const string PERMISSION = 'footer.manage';

    /** Hidden field of a block nobody registers now: its settings, as stored */
    private const string STORED_SETTINGS = 'stored_settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'pie-de-pagina';

    protected string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /** @var list<string>|null Keys of the stored layout, read once per request */
    private ?array $storedKeys = null;

    /**
     * What the Builder needs, worked out once per request: Filament evaluates `blocks()` many times
     * per request (once per column and more), and available() asks every module block isAvailable().
     *
     * @var array{blocks: list<SiteBlockInterface>, available: list<string>, schemas: array<string, bool>}|null
     */
    private ?array $catalog = null;

    public static function getNavigationLabel(): string
    {
        return __('filament.footer.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Site->getLabel();
    }

    public function getTitle(): string
    {
        return __('filament.footer.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan(self::PERMISSION);
    }

    public function mount(FooterLayoutServiceInterface $layouts, FooterMenusInterface $menus): void
    {
        $names = $menus->names();
        $menuNames = [];

        foreach (MenuLocation::footers() as $location) {
            $menuNames[$location->value] = $names[$location->value] ?? '';
        }

        $this->form->fill([
            'columns' => array_map(fn (FooterColumn $column): array => [
                'blocks' => array_map($this->blockState(...), $column->blocks),
            ], $layouts->layout()->columns),
            'menu_names' => $menuNames,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.footer.sections.columns'))
                    ->description(__('filament.footer.sections.columns_help', ['max' => FooterLayout::MAX_COLUMNS]))
                    ->schema([
                        Text::make(__('filament.footer.intro')),
                        Repeater::make('columns')
                            ->hiddenLabel()
                            ->schema([
                                Builder::make('blocks')
                                    ->label(__('filament.footer.fields.blocks'))
                                    ->blocks(fn (): array => $this->builderBlocks())
                                    ->addActionLabel(__('filament.footer.fields.add_block'))
                                    ->maxItems(FooterLayout::MAX_BLOCKS_PER_COLUMN)
                                    ->blockNumbers(false)
                                    ->collapsible()
                                    ->reorderable(),
                            ])
                            ->itemLabel(__('filament.footer.fields.column'))
                            ->itemNumbers()
                            ->addActionLabel(__('filament.footer.fields.add_column'))
                            ->maxItems(FooterLayout::MAX_COLUMNS)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make(__('filament.footer.sections.menus'))
                    ->description(__('filament.footer.sections.menus_help'))
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema($this->menuNameFields()),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(FooterLayoutServiceInterface $layouts, FooterMenusInterface $menus): void
    {
        // Livewire methods can be called without the page's own guard
        abort_unless(self::canAccess(), 403);

        $state = $this->form->getState();

        $layouts->save($this->layoutFrom($state['columns'] ?? []));
        $menus->saveNames(is_array($state['menu_names'] ?? null) ? $state['menu_names'] : []);

        Notification::make()
            ->title(__('filament.footer.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([$this->knowledgeGuideAction('core.panel.footer')]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }

    /**
     * One definition per footer block, available or not, plus one per stored key nobody registers now.
     *
     * @return list<Block>
     */
    private function builderBlocks(): array
    {
        $catalog = $this->catalog();
        $blocks = [];

        // Fresh components for every Builder: one component cannot belong to two columns
        foreach ($catalog['blocks'] as $block) {
            $blocks[] = $catalog['schemas'][$block->key()]
                ? $this->definitionFor($block, in_array($block->key(), $catalog['available'], true))
                : $this->preservedDefinition($block->key(), $block->label(), __('filament.footer.broken_block'));
        }

        foreach ($this->storedKeys() as $key) {
            if (! array_key_exists($key, $catalog['schemas'])) {
                $blocks[] = $this->preservedDefinition($key, __('filament.footer.unknown_block.label', ['key' => $key]), __('filament.footer.unknown_block.help'));
            }
        }

        return $blocks;
    }

    /**
     * @return array{blocks: list<SiteBlockInterface>, available: list<string>, schemas: array<string, bool>}
     */
    private function catalog(): array
    {
        if ($this->catalog === null) {
            $blocks = resolve(SiteBlockRegistryInterface::class)->forPlacement(SiteBlockPlacement::Footer);
            $schemas = [];

            foreach ($blocks as $block) {
                $schemas[$block->key()] = $this->schemaOf($block) !== null;
            }

            $this->catalog = [
                'blocks' => $blocks,
                'available' => array_map(
                    static fn (SiteBlockInterface $block): string => $block->key(),
                    resolve(SiteBlockRendererInterface::class)->available(SiteBlockPlacement::Footer),
                ),
                'schemas' => $schemas,
            ];
        }

        return $this->catalog;
    }

    /**
     * Whether the page has no form for this block (nobody registers its key now, or its settings
     * cannot be built), so its stored settings travel untouched in a hidden field.
     */
    private function preservesSettings(string $key): bool
    {
        return ! ($this->catalog()['schemas'][$key] ?? false);
    }

    /**
     * @return array<Component>|null null when the block's settings cannot be built
     */
    private function schemaOf(SiteBlockInterface $block): ?array
    {
        try {
            return $block->settingsSchema();
        } catch (Throwable) {
            return null;
        }
    }

    private function definitionFor(SiteBlockInterface $block, bool $available): Block
    {
        $label = $block->label();
        $settings = $this->schemaOf($block) ?? [];

        return Block::make($block->key())
            ->label(static fn (?array $state): string => self::itemLabel($label, $state))
            ->schema([
                Text::make(__('filament.footer.unavailable_block'))
                    ->color('warning')
                    ->visible(! $available),
                $this->titleField(),
                Group::make($settings)->statePath('settings'),
            ])
            // Kept where it is stored, but not offered while it cannot be drawn
            ->maxItems($available ? null : 0);
    }

    private function preservedDefinition(string $key, string $label, string $help): Block
    {
        return Block::make($key)
            ->label($label)
            ->schema([
                Text::make($help)->color('warning'),
                Hidden::make('title'),
                Hidden::make(self::STORED_SETTINGS),
            ])
            ->maxItems(0);
    }

    private function titleField(): TextInput
    {
        return TextInput::make('title')
            ->label(__('filament.footer.fields.title'))
            ->helperText(__('filament.footer.fields.title_help'))
            ->maxLength(FooterBlock::MAX_TITLE_LENGTH)
            ->live(onBlur: true);
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private static function itemLabel(string $label, ?array $state): string
    {
        $title = is_string($state['title'] ?? null) ? trim($state['title']) : '';

        return $title === '' ? $label : $label.' · '.$title;
    }

    /**
     * @return list<TextInput>
     */
    private function menuNameFields(): array
    {
        $menus = resolve(FooterMenusInterface::class);

        return array_map(
            static fn (MenuLocation $location): TextInput => TextInput::make('menu_names.'.$location->value)
                ->label(__('filament.footer.fields.menu_name', ['default' => $menus->defaultLabel($location)]))
                ->placeholder($menus->defaultLabel($location))
                ->maxLength(FooterMenusInterface::MAX_NAME_LENGTH),
            MenuLocation::footers(),
        );
    }

    /**
     * The Builder state of a stored block.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    private function blockState(FooterBlock $block): array
    {
        if (! $this->preservesSettings($block->key)) {
            return ['type' => $block->key, 'data' => ['title' => $block->title, 'settings' => $block->settings]];
        }

        try {
            $stored = json_encode($block->settings, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $stored = '{}';
        }

        return ['type' => $block->key, 'data' => ['title' => $block->title, self::STORED_SETTINGS => $stored]];
    }

    /**
     * @param  array<array-key, mixed>  $columns
     */
    private function layoutFrom(array $columns): FooterLayout
    {
        $result = [];

        foreach (array_slice(array_values($columns), 0, FooterLayout::MAX_COLUMNS) as $column) {
            $blocks = [];

            foreach (is_array($column) && is_array($column['blocks'] ?? null) ? array_values($column['blocks']) : [] as $item) {
                $block = $this->blockFrom($item);

                if ($block instanceof FooterBlock) {
                    $blocks[] = $block;
                }
            }

            $result[] = new FooterColumn(array_slice($blocks, 0, FooterLayout::MAX_BLOCKS_PER_COLUMN));
        }

        return FooterLayout::of($result);
    }

    private function blockFrom(mixed $item): ?FooterBlock
    {
        if (! is_array($item) || ! is_string($item['type'] ?? null) || $item['type'] === '') {
            return null;
        }

        $type = $item['type'];
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $title = is_string($data['title'] ?? null) ? $data['title'] : null;

        if ($this->preservesSettings($type)) {
            // Only a block that was stored: a key forged into the form is not kept
            if (! in_array($type, $this->storedKeys(), true)) {
                return null;
            }

            return new FooterBlock($type, $title, $this->storedSettings($data[self::STORED_SETTINGS] ?? null));
        }

        // A block with a form: its fields, never a hidden copy sent along with them
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return new FooterBlock($type, $title, array_filter($settings, is_string(...), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @return array<string, mixed>
     */
    private function storedSettings(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? array_filter($decoded, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * The keys of the stored layout, read once per request.
     *
     * @return list<string>
     */
    private function storedKeys(): array
    {
        if ($this->storedKeys === null) {
            $keys = [];

            foreach (resolve(FooterLayoutServiceInterface::class)->layout()->columns as $column) {
                foreach ($column->blocks as $block) {
                    $keys[] = $block->key;
                }
            }

            $this->storedKeys = array_values(array_unique($keys));
        }

        return $this->storedKeys;
    }
}
