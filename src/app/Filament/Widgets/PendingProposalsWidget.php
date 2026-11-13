<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Proposals\ProposalReviewPanel;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Proposals from the web awaiting review, oldest first (core#575). Only for whoever may review a
 * type, with that type's proposals, and only while one is pending.
 */
class PendingProposalsWidget extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        foreach (self::reviewableTypes() as [, $model]) {
            if (ProposalReviewPanel::pendingCount($model) > 0) {
                return true;
            }
        }

        return false;
    }

    public function getTableHeading(): string
    {
        return __('filament.proposals.widget.heading');
    }

    public function table(Table $table): Table
    {
        $limit = app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(static::class, 5);

        return $table
            ->records(fn (): array => $this->rows($limit))
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament.proposals.widget.title'))
                    ->description(fn (array $record): string => is_string($record['by'] ?? null) ? $record['by'] : '')
                    ->wrap(),
                TextColumn::make('submitted_at')
                    ->label(__('filament.proposals.widget.submitted_at'))
                    ->dateTime('d/m/Y'),
            ])
            ->recordUrl(fn (array $record): ?string => is_string($record['url'] ?? null) ? $record['url'] : null)
            ->paginated(false);
    }

    /**
     * @return list<array{ProposalType, class-string<EventModel|ArticleModel>}>
     */
    private static function reviewableTypes(): array
    {
        return array_values(array_filter(
            [[ProposalType::Event, EventModel::class], [ProposalType::Article, ArticleModel::class]],
            static fn (array $type): bool => ProposalReviewPanel::canReview($type[0]),
        ));
    }

    /**
     * @return array<string, array{title: string, by: string, submitted_at: ?DateTimeInterface, url: ?string}>
     */
    private function rows(int $limit): array
    {
        $rows = [];

        foreach (self::reviewableTypes() as [$type, $model]) {
            $proposals = $model::query()
                ->with('proposer')
                ->where('proposal_status', ProposalStatus::PendingReview->value)
                ->orderBy('submitted_at')
                ->limit($limit)
                ->get();

            foreach ($proposals as $proposal) {
                $rows[$type->value.':'.$proposal->getKey()] = [
                    'title' => (string) $proposal->getAttribute('title'),
                    'by' => __('filament.proposals.widget.by', [
                        'type' => __('filament.proposals.widget.types.'.$type->value),
                        'name' => $this->proposerName($proposal),
                    ]),
                    'submitted_at' => $proposal->getAttribute('submitted_at'),
                    'url' => $this->editUrl($proposal),
                ];
            }
        }

        uasort($rows, static fn (array $a, array $b): int => ($a['submitted_at']?->getTimestamp() ?? 0) <=> ($b['submitted_at']?->getTimestamp() ?? 0));

        return array_slice($rows, 0, $limit, true);
    }

    /**
     * The proposer's account may be gone (proposed_by is set to null on delete).
     */
    private function proposerName(EventModel|ArticleModel $proposal): string
    {
        $proposer = $proposal->getRelationValue('proposer');

        return $proposer instanceof UserModel && is_string($proposer->display_name)
            ? $proposer->display_name
            : (string) __('filament.proposals.section.no_account');
    }

    private function editUrl(EventModel|ArticleModel $proposal): ?string
    {
        $resource = $proposal instanceof ArticleModel ? ArticleResource::class : EventResource::class;

        return $resource::canEdit($proposal) ? $resource::getUrl('edit', ['record' => $proposal]) : null;
    }
}
