<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\DTOs\BlockedReleaseDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Filament\Concerns\ChecksPermissions;
use App\Infrastructure\Updates\Jobs\UpdateModuleJob;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use App\View\Modules\CompatibilityIssueFormatter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ModuleUpdatesPage extends Page implements HasTable
{
    use ChecksPermissions;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.module-updates';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 100;

    /**
     * Stored as arrays: Livewire cannot serialize DTO objects in public properties.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $availableUpdates = [];

    public bool $isChecking = false;

    /** @var array<int, string> Modules whose queued update is still being followed */
    public array $queuedModules = [];

    /** When the oldest followed update was queued (ISO 8601) */
    public ?string $queuedSince = null;

    /** @var array<string, string> Module name => why its repository could not be checked */
    public array $checkErrors = [];

    /** @var array<int, string> */
    public array $modulesWithoutSource = [];

    /**
     * Newer releases this host cannot run (BlockedReleaseDTO::toArray()); shown, never offered.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $blockedReleases = [];

    public static function canAccess(): bool
    {
        return self::userCan('updates.apply');
    }

    /**
     * Updates replace module code and run migrations, so the public site must be closed first.
     */
    public function isMaintenanceModeEnabled(): bool
    {
        return app(SettingsServiceInterface::class)->isMaintenanceModeEnabled();
    }

    private static function maintenanceRequiredMessage(): string
    {
        return __('filament.updates.modules.maintenance_required.title');
    }

    public function getMaintenanceSettingsUrl(): ?string
    {
        return SiteSettings::canAccess() ? SiteSettings::getMaintenanceTabUrl() : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::pendingModules()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function mount(): void
    {
        $this->loadPersistedState();
    }

    /**
     * Pending updates and missing repositories as recorded by the last check,
     * so opening the page never waits on GitHub.
     */
    private function loadPersistedState(): void
    {
        $this->availableUpdates = self::pendingModules()
            ->map(fn (Module $module): array => AvailableUpdateDTO::fromModule($module)->toArray())
            ->values()
            ->all();

        $this->modulesWithoutSource = collect(app(ModuleRepositoryInterface::class)->all()->all())
            ->reject(fn (Module $module): bool => $module->hasUpdateSource())
            ->map(fn (Module $module): string => $module->name()->value)
            ->values()
            ->all();

        $this->blockedReleases = collect(app(ModuleRepositoryInterface::class)->all()->all())
            ->filter(fn (Module $module): bool => $module->hasBlockedRelease())
            ->map(fn (Module $module): array => BlockedReleaseDTO::fromModule($module)->toArray())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Module>
     */
    private static function pendingModules(): Collection
    {
        return collect(app(ModuleRepositoryInterface::class)->all()->all())
            ->filter(fn (Module $module): bool => $module->hasAvailableUpdate())
            ->values();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.updates.modules.navigation');
    }

    public function getTitle(): string
    {
        return __('filament.updates.modules.title');
    }

    public function getHeading(): string
    {
        return __('filament.updates.modules.heading');
    }

    public function getSubheading(): string
    {
        return __('filament.updates.modules.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkUpdates')
                ->label(__('filament.updates.modules.actions.check'))
                ->icon('heroicon-o-magnifying-glass')
                ->action('checkForUpdates')
                ->disabled(fn (): bool => $this->isChecking),

            Action::make('updateAll')
                ->label(__('filament.updates.modules.actions.update_all'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('updateAllModules')
                ->requiresConfirmation()
                ->modalHeading(__('filament.updates.modules.confirm.update_all_heading'))
                ->modalDescription(__('filament.updates.modules.confirm.update_all_description'))
                ->disabled(fn (): bool => $this->availableUpdates === []
                    || $this->queuedModules !== []
                    || ! $this->isMaintenanceModeEnabled())
                ->tooltip(fn (): ?string => $this->isMaintenanceModeEnabled() ? null : self::maintenanceRequiredMessage()),
        ];
    }

    public function checkForUpdates(): void
    {
        $this->isChecking = true;

        try {
            $result = app(ModuleUpdateCheckerInterface::class)->checkAll(fresh: true);
            $this->availableUpdates = $result->updates
                ->map(fn (AvailableUpdateDTO $update): array => $update->toArray())
                ->values()
                ->all();
            $this->checkErrors = $result->errors;
            $this->modulesWithoutSource = $result->modulesWithoutSource;
            $this->blockedReleases = array_map(
                static fn (BlockedReleaseDTO $blocked): array => $blocked->toArray(),
                $result->blocked,
            );

            if ($result->hasErrors()) {
                Notification::make()
                    ->title(__('filament.updates.modules.notifications.check_failed'))
                    ->body(implode("\n", array_map(
                        fn (string $module, string $error): string => "{$module}: {$error}",
                        array_keys($result->errors),
                        $result->errors,
                    )))
                    ->danger()
                    ->send();
            } elseif ($this->availableUpdates === []) {
                Notification::make()
                    ->title(__('filament.updates.modules.notifications.no_updates'))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('filament.updates.modules.notifications.updates_found', [
                        'count' => count($this->availableUpdates),
                    ]))
                    ->info()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament.updates.modules.notifications.check_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isChecking = false;
        }
    }

    /**
     * Updates run in the queue: a web request would hit the 60 s PHP/nginx limit.
     */
    public function updateModule(string $moduleName): void
    {
        if (! $this->ensureUpdatesCanBeApplied()) {
            return;
        }

        $this->queueUpdate($moduleName);
    }

    public function updateAllModules(): void
    {
        if (! $this->ensureUpdatesCanBeApplied()) {
            return;
        }

        foreach ($this->availableUpdates as $update) {
            $this->queueUpdate((string) $update['module_name']);
        }
    }

    /**
     * Livewire methods can be called without the (disabled) buttons, so check again here.
     */
    private function ensureUpdatesCanBeApplied(): bool
    {
        abort_unless(self::canAccess(), 403);

        if ($this->isMaintenanceModeEnabled()) {
            return true;
        }

        Notification::make()
            ->title(__('filament.updates.modules.notifications.maintenance_required'))
            ->danger()
            ->send();

        return false;
    }

    private function queueUpdate(string $moduleName): void
    {
        if (in_array($moduleName, $this->queuedModules, true)) {
            return;
        }

        UpdateModuleJob::dispatch($moduleName);

        $this->queuedModules[] = $moduleName;
        $this->queuedSince ??= now()->toIso8601String();

        Notification::make()
            ->title(__('filament.updates.modules.notifications.update_queued', ['module' => $moduleName]))
            ->info()
            ->send();
    }

    /**
     * Called by wire:poll while updates are queued: reports the ones that finished.
     */
    public function pollUpdates(): void
    {
        if ($this->queuedModules === [] || $this->queuedSince === null) {
            return;
        }

        $finished = false;
        $since = Carbon::parse($this->queuedSince);
        // Longer than UpdateModuleJob's timeout: past this, the job died without reporting
        $givenUp = $since->copy()->addMinutes(15)->isPast();

        foreach ($this->queuedModules as $index => $moduleName) {
            // An update already running when the page queued it (e.g. after a reload) also counts
            $history = ModuleUpdateHistoryModel::query()
                ->where('module_name', $moduleName)
                ->where(fn ($query) => $query->where('started_at', '>=', $since)->orWhere('completed_at', '>=', $since))
                ->latest('started_at')
                ->first();

            if ($history !== null && $history->status->isTerminal()) {
                $this->notifyFinished($moduleName, $history);
            } elseif ($givenUp) {
                Notification::make()
                    ->title(__('filament.updates.modules.notifications.update_lost', ['module' => $moduleName]))
                    ->warning()
                    ->send();
            } else {
                continue;
            }

            unset($this->queuedModules[$index]);
            $finished = true;
        }

        $this->queuedModules = array_values($this->queuedModules);

        if ($this->queuedModules === []) {
            $this->queuedSince = null;
        }

        if ($finished) {
            $this->loadPersistedState();
        }
    }

    private function notifyFinished(string $moduleName, ModuleUpdateHistoryModel $history): void
    {
        if ($history->status === UpdateStatus::Completed) {
            Notification::make()
                ->title(__('filament.updates.modules.notifications.update_success', [
                    'module' => $moduleName,
                    'version' => $history->to_version,
                ]))
                ->success()
                ->send();

            return;
        }

        $body = $history->status === UpdateStatus::RolledBack
            ? __('filament.updates.modules.notifications.update_rolled_back', ['error' => (string) $history->error_message])
            : (string) $history->error_message;

        Notification::make()
            ->title(__('filament.updates.modules.notifications.update_failed', ['module' => $moduleName]))
            ->body($body)
            ->danger()
            ->send();
    }

    public function previewAction(): Action
    {
        return Action::make('preview')
            ->modalHeading(fn (array $arguments): string => __('filament.updates.modules.preview.heading', [
                'module' => (string) ($arguments['module'] ?? ''),
            ]))
            ->modalContent(fn (array $arguments): View => $this->previewContent((string) ($arguments['module'] ?? '')))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament.updates.modules.preview.close'))
            ->modalWidth(MaxWidth::TwoExtraLarge);
    }

    private function previewContent(string $moduleName): View
    {
        try {
            $preview = app(ModuleUpdaterInterface::class)->preview(new ModuleName($moduleName));
        } catch (\Throwable $e) {
            return view('filament.pages.partials.module-update-preview', ['preview' => null, 'error' => $e->getMessage()]);
        }

        return view('filament.pages.partials.module-update-preview', [
            'preview' => [
                'from_version' => $preview->fromVersion,
                'to_version' => $preview->toVersion,
                'is_major_update' => $preview->isMajorUpdate,
                // Release notes come from GitHub: render their Markdown, never their raw HTML
                'changelog_html' => trim($preview->changelog) === ''
                    ? null
                    : Str::markdown($preview->changelog, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                'core_compatible' => $preview->coreCompatible,
                'core_requirement' => $preview->coreRequirement,
                'compatibility_reasons' => array_map(
                    static fn (array $issue): string => app(CompatibilityIssueFormatter::class)->format($issue),
                    $preview->compatibilityIssues,
                ),
            ],
            'error' => null,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ModuleUpdateHistoryModel::query()->latest('started_at'))
            ->columns([
                TextColumn::make('module_name')
                    ->label(__('filament.updates.modules.history.module'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('from_version')
                    ->label(__('filament.updates.modules.history.from_version')),

                TextColumn::make('to_version')
                    ->label(__('filament.updates.modules.history.to_version')),

                TextColumn::make('status')
                    ->label(__('filament.updates.modules.history.status'))
                    ->badge()
                    ->formatStateUsing(fn (UpdateStatus|string $state): string => ($state instanceof UpdateStatus ? $state : UpdateStatus::from($state))->label())
                    ->color(fn (UpdateStatus|string $state): string => match ($state instanceof UpdateStatus ? $state->value : $state) {
                        'completed' => 'success',
                        'failed', 'rolled_back' => 'danger',
                        'pending', 'downloading', 'applying', 'migrating' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('started_at')
                    ->label(__('filament.updates.modules.history.started_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Only failures get an icon: a green check under "Error" read as "yes, it failed"
                IconColumn::make('error_message')
                    ->label(__('filament.updates.modules.history.has_error'))
                    ->getStateUsing(fn (ModuleUpdateHistoryModel $record): bool => $record->error_message !== null)
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('danger')
                    ->tooltip(fn (ModuleUpdateHistoryModel $record): ?string => $record->error_message),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
