<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Updates\ValueObjects\GitHubReleaseInfo;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\CoreUpdateHistoryModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

final class CoreUpdatesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.core-updates';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 101;

    public string $currentVersion = '';

    public string $currentCommit = '';

    public ?GitHubReleaseInfo $latestRelease = null;

    public bool $isChecking = false;

    public function mount(CoreVersionServiceInterface $versionService): void
    {
        $this->currentVersion = $versionService->getCurrentVersion()->value();
        $this->currentCommit = $versionService->getCurrentCommit();
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
                ->action('checkForUpdates')
                ->disabled(fn (): bool => $this->isChecking),
        ];
    }

    public function checkForUpdates(): void
    {
        $this->isChecking = true;

        try {
            $updateChecker = app(CoreUpdateCheckerInterface::class);
            $this->latestRelease = $updateChecker->checkForUpdate();

            if ($this->latestRelease === null) {
                Notification::make()
                    ->title(__('filament.updates.core.notifications.up_to_date'))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('filament.updates.core.notifications.update_available', [
                        'version' => $this->latestRelease->version->value(),
                    ]))
                    ->info()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament.updates.core.notifications.check_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isChecking = false;
        }
    }

    public function isMajorUpdate(): bool
    {
        if ($this->latestRelease === null) {
            return false;
        }

        $currentParts = explode('.', $this->currentVersion);
        $newParts = explode('.', $this->latestRelease->version->value());

        return $currentParts[0] !== $newParts[0];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CoreUpdateHistoryModel::query()->latest('created_at'))
            ->columns([
                TextColumn::make('from_version')
                    ->label(__('filament.updates.core.history.from_version'))
                    ->formatStateUsing(fn (string $state): string => "v{$state}"),

                TextColumn::make('to_version')
                    ->label(__('filament.updates.core.history.to_version'))
                    ->formatStateUsing(fn (string $state): string => "v{$state}"),

                TextColumn::make('status')
                    ->label(__('filament.updates.core.history.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed', 'rolled_back' => 'danger',
                        'started' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('git_commit_before')
                    ->label(__('filament.updates.core.history.commit_before'))
                    ->limit(8)
                    ->tooltip(fn (CoreUpdateHistoryModel $record): string => $record->git_commit_before),

                TextColumn::make('git_commit_after')
                    ->label(__('filament.updates.core.history.commit_after'))
                    ->limit(8)
                    ->tooltip(fn (CoreUpdateHistoryModel $record): ?string => $record->git_commit_after)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label(__('filament.updates.core.history.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
