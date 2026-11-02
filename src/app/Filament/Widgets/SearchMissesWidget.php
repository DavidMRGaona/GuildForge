<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Filament\Concerns\ChecksPermissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * What visitors searched for in the last 30 days without finding anything, most frequent first.
 */
class SearchMissesWidget extends TableWidget
{
    use ChecksPermissions;

    private const int DAYS = 30;

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::userCan('search.view_stats');
    }

    public function getTableHeading(): string
    {
        return __('filament.search_misses.heading');
    }

    public function table(Table $table): Table
    {
        $limit = app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(static::class, 10);

        return $table
            ->records(fn (): array => app(SearchMetricsServiceInterface::class)->topMisses(self::DAYS, $limit))
            ->columns([
                TextColumn::make('query')
                    ->label(__('filament.search_misses.query')),
                TextColumn::make('count')
                    ->label(__('filament.search_misses.count'))
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
