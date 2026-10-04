<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/**
 * The core is deployed continuously from a branch, so this page shows the deployed
 * commit and the commits on that branch that have not reached production yet.
 */
final class CoreUpdatesPage extends Page
{
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

    public function mount(CoreVersionServiceInterface $versionService): void
    {
        $this->deployedCommit = $versionService->getCurrentCommit();
        $this->branch = (string) config('updates.core.branch', 'main');
        $this->repositoryUrl = 'https://github.com/'.config('updates.core.owner').'/'.config('updates.core.repo');

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
}
