<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\CoreUpdateHistoryModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * The core is deployed continuously from a branch, so this page shows the deployed
 * commit, the commits on that branch that have not reached production yet and the
 * deployment log written by the container entrypoint (core:record-deployment).
 */
final class CoreUpdatesPage extends Page implements HasTable
{
    use InteractsWithTable;

    private const string LAST_CHECK_CACHE_KEY = 'updates.core.last_check';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.core-updates';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 101;

    public string $deployedCommit = '';

    public string $branch = '';

    public string $repositoryUrl = '';

    /** @var array{deployed_commit: string, branch: string, behind_by: int, latest_commit: string, commits: array<int, array{sha: string, message: string, date: string|null, url: string}>, diverged: bool}|null */
    public ?array $status = null;

    public ?string $checkedAt = null;

    public ?string $checkError = null;

    public ?string $deployedAt = null;

    public function mount(CoreVersionServiceInterface $versionService): void
    {
        $this->deployedCommit = $versionService->getCurrentCommit();
        $this->branch = (string) config('updates.core.branch', 'main');
        $this->repositoryUrl = 'https://github.com/'.config('updates.core.owner').'/'.config('updates.core.repo');
        $this->deployedAt = CoreUpdateHistoryModel::query()
            ->where('git_commit_after', $this->deployedCommit)
            ->where('status', UpdateStatus::Completed)
            ->latest('created_at')
            ->first()
            ?->created_at
            ?->format('d/m/Y H:i');

        // Show the last check, unless it was made for a deployment that has since been replaced
        $lastCheck = Cache::get(self::LAST_CHECK_CACHE_KEY);

        if (is_array($lastCheck) && ($lastCheck['status']['deployed_commit'] ?? null) === $this->deployedCommit) {
            $this->status = $lastCheck['status'];
            $this->checkedAt = $lastCheck['checked_at'];
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.updates.core.navigation');
    }

    public function getTitle(): string
    {
        return __('filament.updates.core.title');
    }

    public function getHeading(): string
    {
        return __('filament.updates.core.heading');
    }

    public function getSubheading(): string
    {
        return __('filament.updates.core.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkUpdates')
                ->label(__('filament.updates.core.actions.check'))
                ->icon('heroicon-o-magnifying-glass')
                ->action('checkForUpdates'),
        ];
    }

    public function checkForUpdates(): void
    {
        try {
            $status = app(CoreUpdateCheckerInterface::class)->check();
        } catch (\Throwable $e) {
            $this->status = null;
            $this->checkError = $e->getMessage();

            Notification::make()
                ->title(__('filament.updates.core.notifications.check_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->status = $status->toArray();
        $this->checkedAt = now()->toIso8601String();
        $this->checkError = null;
        Cache::forever(self::LAST_CHECK_CACHE_KEY, ['status' => $this->status, 'checked_at' => $this->checkedAt]);

        Notification::make()
            ->title($status->isUpToDate()
                ? __('filament.updates.core.notifications.up_to_date')
                : trans_choice('filament.updates.core.notifications.behind', $status->behindBy, ['count' => $status->behindBy]))
            ->color($status->isUpToDate() ? 'success' : 'warning')
            ->icon($status->isUpToDate() ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CoreUpdateHistoryModel::query())
            ->heading(__('filament.updates.core.history.title'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('filament.updates.core.history.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('git_commit_before')
                    ->label(__('filament.updates.core.history.from'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null && $state !== '' ? substr($state, 0, 7) : '—')
                    ->fontFamily('mono')
                    ->default('—'),

                TextColumn::make('git_commit_after')
                    ->label(__('filament.updates.core.history.to'))
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 7))
                    ->fontFamily('mono')
                    ->url(fn (CoreUpdateHistoryModel $record): string => "{$this->repositoryUrl}/commit/{$record->git_commit_after}", shouldOpenInNewTab: true),

                TextColumn::make('changes')
                    ->label(__('filament.updates.core.history.changes'))
                    ->state(fn (CoreUpdateHistoryModel $record): ?string => $record->git_commit_before !== ''
                        ? (string) __('filament.updates.core.history.view_changes')
                        : null)
                    ->url(fn (CoreUpdateHistoryModel $record): ?string => $record->git_commit_before !== ''
                        ? "{$this->repositoryUrl}/compare/{$record->git_commit_before}...{$record->git_commit_after}"
                        : null, shouldOpenInNewTab: true)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('filament.updates.core.history.status'))
                    ->badge()
                    ->formatStateUsing(fn (UpdateStatus $state): string => $state === UpdateStatus::Applying
                        ? __('filament.updates.core.history.in_progress')
                        : $state->label())
                    ->color(fn (UpdateStatus $state): string => $state->color()),

                // Flags failures only, with the error on hover
                IconColumn::make('error_message')
                    ->label(__('filament.updates.core.history.error'))
                    ->getStateUsing(fn (CoreUpdateHistoryModel $record): bool => $record->error_message !== null)
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('danger')
                    ->tooltip(fn (CoreUpdateHistoryModel $record): ?string => $record->error_message),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
