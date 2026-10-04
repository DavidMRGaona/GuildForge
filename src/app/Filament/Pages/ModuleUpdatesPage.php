<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

final class ModuleUpdatesPage extends Page implements HasTable
{
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

    public bool $isUpdating = false;

    public ?string $updatingModule = null;

    /** @var array<string, string> Module name => why its repository could not be checked */
    public array $checkErrors = [];

    /** @var array<int, string> */
    public array $modulesWithoutSource = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
                ->disabled(fn (): bool => $this->isChecking || $this->isUpdating),

            Action::make('updateAll')
                ->label(__('filament.updates.modules.actions.update_all'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('updateAllModules')
                ->requiresConfirmation()
                ->modalHeading(__('filament.updates.modules.confirm.update_all_heading'))
                ->modalDescription(__('filament.updates.modules.confirm.update_all_description'))
                ->disabled(fn (): bool => $this->availableUpdates === [] || $this->isUpdating),
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

    public function updateModule(string $moduleName): void
    {
        $this->isUpdating = true;
        $this->updatingModule = $moduleName;

        try {
            $updater = app(ModuleUpdaterInterface::class);
            $result = $updater->update(new ModuleName($moduleName));

            if ($result->isSuccess()) {
                Notification::make()
                    ->title(__('filament.updates.modules.notifications.update_success', [
                        'module' => $moduleName,
                        'version' => $result->toVersion,
                    ]))
                    ->success()
                    ->send();

                $this->loadPersistedState();
            } else {
                $message = $result->wasRolledBack()
                    ? __('filament.updates.modules.notifications.update_rolled_back', [
                        'error' => $result->errorMessage,
                    ])
                    : $result->errorMessage;

                Notification::make()
                    ->title(__('filament.updates.modules.notifications.update_failed', [
                        'module' => $moduleName,
                    ]))
                    ->body(is_string($message) ? $message : null)
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament.updates.modules.notifications.update_failed', [
                    'module' => $moduleName,
                ]))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isUpdating = false;
            $this->updatingModule = null;
        }
    }

    public function updateAllModules(): void
    {
        foreach ($this->availableUpdates as $update) {
            $this->updateModule((string) $update['module_name']);

            if ($this->isUpdating === false) {
                // If updating was stopped, break the loop
                break;
            }
        }
    }

    public function previewUpdate(string $moduleName): void
    {
        try {
            $updater = app(ModuleUpdaterInterface::class);
            $preview = $updater->preview(new ModuleName($moduleName));

            $this->dispatch('open-modal', id: 'preview-update', data: [
                'moduleName' => $preview->moduleName,
                'fromVersion' => $preview->fromVersion,
                'toVersion' => $preview->toVersion,
                'isMajorUpdate' => $preview->isMajorUpdate,
                'coreCompatible' => $preview->coreCompatible,
                'coreRequirement' => $preview->coreRequirement,
                'pendingMigrations' => $preview->pendingMigrations,
                'changelog' => $preview->changelog,
            ]);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('filament.updates.modules.notifications.preview_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed', 'rolled_back' => 'danger',
                        'pending', 'downloading', 'applying', 'migrating' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('started_at')
                    ->label(__('filament.updates.modules.history.started_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                IconColumn::make('error_message')
                    ->label(__('filament.updates.modules.history.has_error'))
                    ->boolean()
                    ->getStateUsing(fn (ModuleUpdateHistoryModel $record): bool => $record->error_message !== null)
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->defaultSort('started_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
