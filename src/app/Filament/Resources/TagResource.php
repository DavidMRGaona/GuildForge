<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Application\Services\TagQueryServiceInterface;
use App\Filament\Resources\TagResource\Pages;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TagResource extends BaseResource
{
    protected static ?string $model = TagModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('filament.tags.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.tags.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.tags.sections.general'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament.tags.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')
                            ->label(__('filament.tags.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        Select::make('parent_id')
                            ->label(__('filament.tags.fields.parent'))
                            ->options(fn (): array => self::getTagQueryService()->getOptionsForSelect())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabledOn('edit')
                            ->hint(fn (?TagModel $record): ?string => $record?->parent_id !== null
                                ? (string) __('filament.tags.hints.parent_not_editable')
                                : null),
                        Textarea::make('description')
                            ->label(__('filament.tags.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make(__('filament.tags.sections.display'))
                    ->schema([
                        CheckboxList::make('applies_to')
                            ->label(__('filament.tags.fields.applies_to'))
                            ->options([
                                'events' => __('filament.tags.applies_to_options.events'),
                                'articles' => __('filament.tags.applies_to_options.articles'),
                                'galleries' => __('filament.tags.applies_to_options.galleries'),
                            ])
                            ->required()
                            ->default(['events', 'articles', 'galleries'])
                            ->columns(3),
                        ColorPicker::make('color')
                            ->label(__('filament.tags.fields.color'))
                            ->default('#6b7280'),
                        TextInput::make('sort_order')
                            ->label(__('filament.tags.fields.sort_order'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.tags.fields.name'))
                    ->formatStateUsing(fn (TagModel $record): string => self::formatIndentedNameForTable($record))
                    ->html()
                    ->description(function (TagModel $record): string {
                        if ($record->parent_id === null) {
                            return '';
                        }
                        $parentName = $record->parent->name ?? '';

                        return '↳ '.__('filament.tags.child_of', ['parent' => $parentName]);
                    })
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->label(__('filament.tags.fields.color')),
                TextColumn::make('applies_to')
                    ->label(__('filament.tags.fields.applies_to'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'events' => __('filament.tags.applies_to_options.events'),
                        'articles' => __('filament.tags.applies_to_options.articles'),
                        'galleries' => __('filament.tags.applies_to_options.galleries'),
                        default => $state,
                    }),
                TextColumn::make('events_count')
                    ->label(__('filament.tags.fields.events_count'))
                    ->counts('events')
                    ->sortable(),
                TextColumn::make('articles_count')
                    ->label(__('filament.tags.fields.articles_count'))
                    ->counts('articles')
                    ->sortable(),
                TextColumn::make('galleries_count')
                    ->label(__('filament.tags.fields.galleries_count'))
                    ->counts('galleries')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('filament.tags.fields.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament.tags.fields.created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('applies_to')
                    ->label(__('filament.tags.fields.applies_to'))
                    ->options([
                        'events' => __('filament.tags.applies_to_options.events'),
                        'articles' => __('filament.tags.applies_to_options.articles'),
                        'galleries' => __('filament.tags.applies_to_options.galleries'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $q, string $value): Builder => $q->whereJsonContains('applies_to', $value)
                    )),
                SelectFilter::make('parent_id')
                    ->label(__('filament.tags.fields.parent'))
                    ->options(fn (): array => TagModel::whereNull('parent_id')
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $q, string $value): Builder => $q->where('parent_id', $value)
                    )),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (TagModel $record): bool => ! self::getTagQueryService()->canDelete($record->id)),
            ])
            ->paginated(false)
            ->defaultSort(null);
    }

    /**
     * Get tags in hierarchical order.
     *
     * @return Builder<TagModel>
     */
    public static function getEloquentQuery(): Builder
    {
        $tagService = self::getTagQueryService();
        $hierarchicalTags = $tagService->getAllInHierarchicalOrder();

        if (empty($hierarchicalTags)) {
            return parent::getEloquentQuery();
        }

        $orderedIds = array_map(fn ($tag) => $tag->id, $hierarchicalTags);

        // Build CASE statement for ordering
        $orderCase = 'CASE id ';
        foreach ($orderedIds as $index => $id) {
            $orderCase .= "WHEN '{$id}' THEN {$index} ";
        }
        $orderCase .= 'END';

        /** @var Builder<TagModel> $query */
        $query = parent::getEloquentQuery()
            ->with('parent')
            ->orderByRaw($orderCase);

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }

    /**
     * Get the tag query service from the container.
     */
    private static function getTagQueryService(): TagQueryServiceInterface
    {
        return app(TagQueryServiceInterface::class);
    }

    /**
     * Format the tag name with indentation for table display.
     */
    private static function formatIndentedNameForTable(TagModel $record): string
    {
        $name = e($record->name);

        if ($record->parent_id === null) {
            return '<strong>'.$name.'</strong>';
        }

        return $name;
    }
}
