<?php

declare(strict_types=1);

namespace App\Filament\Proposals;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What the event and article resources show of a web proposal (core#575): its status, who proposed
 * it, the «En revisión» tab and navigation badge, and the record's review section. Drafts never
 * submitted are already out of every panel query (HidesUnsubmittedProposals), so a draft seen
 * here was always returned by a reviewer.
 */
final class ProposalReviewPanel
{
    public const string PENDING_TAB = 'pending';

    public const string ALL_TAB = 'all';

    public static function statusLabel(ProposalStatus $status): string
    {
        return self::text('filament.proposals.status.'.$status->value);
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }

    public static function statusColor(ProposalStatus $status): string
    {
        return match ($status) {
            ProposalStatus::PendingReview => 'warning',
            ProposalStatus::Draft => 'gray',
            ProposalStatus::Approved => 'success',
            ProposalStatus::Rejected => 'danger',
        };
    }

    /**
     * Whether the record is a proposal not approved yet: its «Publicado» toggle is locked and the
     * event form leaves out what modules add, which only an approved proposal may configure.
     */
    public static function isUnapproved(?Model $record): bool
    {
        return ($record instanceof EventModel || $record instanceof ArticleModel)
            && ProposalStatus::isUnapproved($record->proposalStatus());
    }

    public static function mayPublishWithToggle(?Model $record): bool
    {
        return ! self::isUnapproved($record);
    }

    /**
     * Why the «Publicado» toggle is locked, or null when it is not.
     */
    public static function publishLockedHint(?Model $record): ?string
    {
        return self::mayPublishWithToggle($record) ? null : self::text('filament.proposals.publish_locked');
    }

    public static function canReview(ProposalType $type): bool
    {
        $user = auth()->user();

        return $user instanceof UserModel
            && app(AuthorizationServiceInterface::class)->can($user, $type->reviewPermission());
    }

    /**
     * @param  class-string<EventModel|ArticleModel>  $model
     */
    public static function pendingCount(string $model): int
    {
        return $model::query()->where('proposal_status', ProposalStatus::PendingReview->value)->count();
    }

    /**
     * The navigation badge: proposals awaiting review, only for whoever may review them.
     *
     * @param  class-string<EventModel|ArticleModel>  $model
     */
    public static function navigationBadge(ProposalType $type, string $model): ?string
    {
        if (! self::canReview($type)) {
            return null;
        }

        $count = self::pendingCount($model);

        return $count > 0 ? (string) $count : null;
    }

    /**
     * «Todos» and «En revisión», while the type accepts proposals or something still awaits review.
     *
     * @param  class-string<EventModel|ArticleModel>  $model
     * @return array<string, Tab>
     */
    public static function tabs(ProposalType $type, string $model): array
    {
        $pending = self::pendingCount($model);

        if ($pending === 0 && ! app(ProposalSettingsServiceInterface::class)->settings()->forType($type)->enabled) {
            return [];
        }

        return [
            self::ALL_TAB => Tab::make(__('filament.proposals.tabs.all')),
            self::PENDING_TAB => Tab::make(__('filament.proposals.tabs.pending'))
                ->badge($pending)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('proposal_status', ProposalStatus::PendingReview->value)),
        ];
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('proposal_status')
            ->label(__('filament.proposals.columns.status'))
            ->badge()
            ->formatStateUsing(fn (ProposalStatus $state): string => self::statusLabel($state))
            ->color(fn (ProposalStatus $state): string => self::statusColor($state))
            ->placeholder('—');
    }

    public static function proposerColumn(): TextColumn
    {
        return TextColumn::make('proposer.display_name')
            ->label(__('filament.proposals.columns.proposer'))
            ->placeholder('—')
            ->wrap();
    }

    /**
     * «Devueltas» and «Rechazadas»; the ones in review have their own tab.
     */
    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('proposal_status')
            ->label(__('filament.proposals.filters.status'))
            ->options([
                ProposalStatus::Draft->value => __('filament.proposals.filters.returned'),
                ProposalStatus::Rejected->value => __('filament.proposals.filters.rejected'),
            ]);
    }

    /**
     * What the author wrote for the administration, who proposed it and when, and the last
     * reason it was returned or rejected with. Only on proposals.
     */
    public static function reviewSection(): Section
    {
        return Section::make(__('filament.proposals.section.heading'))
            ->icon('heroicon-o-inbox-arrow-down')
            ->schema([
                TextEntry::make('proposal_status')
                    ->label(__('filament.proposals.section.status'))
                    ->badge()
                    ->formatStateUsing(fn (ProposalStatus $state): string => self::statusLabel($state))
                    ->color(fn (ProposalStatus $state): string => self::statusColor($state)),
                TextEntry::make('proposer.display_name')
                    ->label(__('filament.proposals.section.proposed_by'))
                    ->placeholder(__('filament.proposals.section.no_account')),
                TextEntry::make('submitted_at')
                    ->label(__('filament.proposals.section.submitted_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('reviewed_at')
                    ->label(__('filament.proposals.section.reviewed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('proposal_note')
                    ->label(__('filament.proposals.section.note'))
                    ->placeholder(__('filament.proposals.section.no_note'))
                    ->columnSpanFull(),
                TextEntry::make('proposal_reason')
                    ->label(__('filament.proposals.section.reason'))
                    ->visible(fn (?Model $record): bool => filled($record?->getAttribute('proposal_reason')))
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->visible(fn (?Model $record): bool => ($record instanceof EventModel || $record instanceof ArticleModel)
                && $record->proposalStatus() instanceof ProposalStatus)
            ->columnSpanFull();
    }
}
