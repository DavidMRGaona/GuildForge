<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Services\TimezoneServiceInterface;
use App\Filament\Actions\RowDetailsAction;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The read-only impersonation history on the user page (core#578), in both directions. It is
 * only offered to whoever holds users.impersonate, and to them only on accounts they may view.
 */
trait ListsImpersonationEntries
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof UserModel
            && $ownerRecord instanceof UserModel
            && $viewer->can('view', $ownerRecord)
            && app(AuthorizationServiceInterface::class)->can($viewer, ImpersonationEligibilityRule::PERMISSION);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $showsTarget = $this->showsTarget();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['impersonator', 'target']))
            ->columns([
                // The main column wraps, so it narrows to what is left (core#383)
                TextColumn::make($showsTarget ? 'target_id' : 'impersonator_id')
                    ->label(__($showsTarget
                        ? 'filament.impersonation.history.columns.target'
                        : 'filament.impersonation.history.columns.impersonator'))
                    ->state(fn (ImpersonationLogEntryModel $record): string => self::accountName($showsTarget ? $record->target : $record->impersonator))
                    ->wrap(),
                TextColumn::make('started_at')
                    ->label(__('filament.impersonation.history.columns.started_at'))
                    ->formatStateUsing(fn (mixed $state): string => self::formatInstant($state))
                    ->sortable(),
                TextColumn::make('mode')
                    ->label(__('filament.impersonation.history.columns.mode'))
                    ->formatStateUsing(fn (mixed $state): string => __('filament.impersonation.history.modes.'.($state->value ?? $state)))
                    ->visibleFrom('md'),
                TextColumn::make('ended_at')
                    ->label(__('filament.impersonation.history.columns.ended_at'))
                    ->formatStateUsing(fn (mixed $state): string => self::formatInstant($state))
                    ->placeholder(__('filament.impersonation.history.open'))
                    ->visibleFrom('xl'),
                TextColumn::make('ended_reason')
                    ->label(__('filament.impersonation.history.columns.reason'))
                    ->formatStateUsing(fn (mixed $state): string => __('filament.impersonation.history.reasons.'.($state->value ?? $state)))
                    ->placeholder('—')
                    ->visibleFrom('2xl'),
            ])
            ->recordActions([RowDetailsAction::makeIconButton()])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('started_at')->orderByDesc('id'))
            ->emptyStateHeading(__('filament.impersonation.history.empty'))
            ->paginated([10, 25, 50]);
    }

    /**
     * True on the administrator's page (it names whom they viewed); false on the page of the
     * account that was viewed (it names who viewed it).
     */
    abstract protected function showsTarget(): bool;

    private static function accountName(?UserModel $account): string
    {
        if (! $account instanceof UserModel || $account->anonymized_at !== null) {
            return __('filament.impersonation.history.deleted_account');
        }

        return $account->display_name ?: $account->name;
    }

    private static function formatInstant(mixed $state): string
    {
        return $state instanceof DateTimeInterface
            ? app(TimezoneServiceInterface::class)->format($state, 'd/m/Y H:i')
            : '';
    }
}
