<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeGuideResource\Widgets;

use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\KnowledgeGuideResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Help searches of the last days that found nothing, most frequent first, below the list of guides.
 */
class KnowledgeSearchMissesWidget extends TableWidget
{
    use ChecksPermissions;

    private const int LIMIT = 15;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::userCan('knowledge.manage');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament.knowledge_guides.search_misses.heading'))
            ->description(__('filament.knowledge_guides.search_misses.description', ['days' => KnowledgeGuideResource::METRICS_DAYS]))
            ->records(fn (): array => resolve(KnowledgeMetricsServiceInterface::class)->topMisses(KnowledgeGuideResource::METRICS_DAYS, self::LIMIT))
            ->columns([
                TextColumn::make('query')
                    ->label(__('filament.knowledge_guides.search_misses.query')),
                TextColumn::make('audience')
                    ->label(__('filament.knowledge_guides.search_misses.audience'))
                    ->formatStateUsing(fn (string $state): string => __('filament.knowledge_guides.audiences.'.$state)),
                TextColumn::make('count')
                    ->label(__('filament.knowledge_guides.search_misses.count'))
                    ->numeric(),
            ])
            ->emptyStateHeading(__('filament.knowledge_guides.search_misses.empty'))
            ->paginated(false);
    }
}
