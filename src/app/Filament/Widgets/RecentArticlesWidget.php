<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Filament\Resources\ArticleResource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentArticlesWidget extends TableWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ArticleResource::canViewAny();
    }

    public function getTableHeading(): string
    {
        return __('Artículos recientes');
    }

    public function table(Table $table): Table
    {
        $limit = app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(static::class, 5);

        return $table
            ->query(
                ArticleModel::query()
                    ->where('is_published', true)
                    ->orderedByPublication()
                    ->limit($limit)
            )
            ->columns([
                TextColumn::make('title')
                    ->label(__('Título'))
                    ->limit(50)
                    ->url(fn (ArticleModel $record): ?string => ArticleResource::canEdit($record)
                        ? ArticleResource::getUrl('edit', ['record' => $record])
                        : null),
                TextColumn::make('author.display_name')
                    ->label(__('Autor')),
                TextColumn::make('published_at')
                    ->label(__('Publicado'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->paginated(false);
    }
}
