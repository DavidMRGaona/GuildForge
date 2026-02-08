<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleAlreadyDisabledException;
use App\Domain\Modules\Exceptions\ModuleAlreadyEnabledException;
use App\Domain\Modules\Exceptions\ModuleCannotUninstallException;
use App\Domain\Modules\Exceptions\ModuleDependencyException;
use App\Domain\Modules\Exceptions\ModuleInstallationException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property \Filament\Forms\Form $form
 */
final class ModulesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.modules';

    public string $filter = 'all';

    public string $search = '';

    /**
     * @var array<int, TemporaryUploadedFile>|null
     */
    public ?array $zipFile = null;

    public ?string $confirmingUninstall = null;

    public bool $confirmDeleteData = false;

    public function mount(): void
    {
        // Show notification from module enable/disable action
        $notification = session('module_action_notification');

        if ($notification !== null) {
            $builder = Notification::make()
                ->title($notification['title']);

            if (($notification['status'] ?? 'info') === 'success') {
                $builder->success();
            }

            $builder->send();
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('modules.filament.page.navigation_label');
    }

    public function getTitle(): string
    {
        return __('modules.filament.page.title');
    }

    public function getDescription(): string
    {
        return __('modules.filament.page.description');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * @return Collection<int, Module>
     */
    public function getModules(ModuleManagerServiceInterface $moduleManager): Collection
    {
        $modules = match ($this->filter) {
            'enabled' => $moduleManager->enabled()->all(),
            'disabled' => collect($moduleManager->all()->all())->filter(fn (Module $m) => $m->isDisabled())->values()->all(),
            default => $moduleManager->all()->all(),
        };

        $collection = collect($modules);

        if ($this->search !== '') {
            $search = strtolower($this->search);
            $collection = $collection->filter(function (Module $module) use ($search): bool {
                return str_contains(strtolower($module->name()->value), $search)
                    || str_contains(strtolower($module->displayName()), $search)
                    || str_contains(strtolower($module->description()), $search);
            });
        }

        return $collection->sortBy(fn (Module $m) => $m->name()->value);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('discover')
                ->label(__('modules.filament.actions.discover'))
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(function (ModuleManagerServiceInterface $moduleManager): void {
                    $discovered = $moduleManager->discover();
                    $count = $discovered->count();

                    if ($count > 0) {
                        Notification::make()
                            ->title(__('modules.filament.notifications.discovered', ['count' => $count]))
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title(__('modules.filament.notifications.no_new_modules'))
                            ->info()
                            ->send();
                    }
                }),

            Action::make('install')
                ->label(__('modules.filament.install_form.submit'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('zipFile')
                        ->label(__('modules.filament.install_form.file_label'))
                        ->helperText(__('modules.filament.install_form.file_help', ['size' => 50]))
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize(50 * 1024) // 50MB in KB
                        ->required()
                        ->storeFiles(false), // Don't store, get the TemporaryUploadedFile directly
                ])
                ->modalHeading(__('modules.filament.install_form.title'))
                ->modalDescription(__('modules.filament.install_form.description'))
                ->modalSubmitActionLabel(__('modules.filament.install_form.submit'))
                ->action(function (array $data, ModuleInstallerInterface $installer, ModuleManagerServiceInterface $moduleManager): void {
                    try {
                        $uploadedFile = $this->resolveUploadedFile($data['zipFile']);

                        // Peek at the manifest to determine if this is an install or update
                        $manifest = $installer->peekManifest($uploadedFile);
                        $isUpdate = $installer->moduleExists($manifest->name);

                        if ($isUpdate) {
                            $manifest = $installer->updateFromZip($uploadedFile);

                            session()->flash('module_action_notification', [
                                'title' => __('modules.filament.notifications.updated', [
                                    'name' => $manifest->name,
                                    'version' => $manifest->version,
                                ]),
                                'status' => 'success',
                            ]);
                        } else {
                            $manifest = $installer->installFromZip($uploadedFile);

                            // Discover to register in DB
                            $moduleManager->discover();

                            session()->flash('module_action_notification', [
                                'title' => __('modules.filament.notifications.installed', ['name' => $manifest->name]),
                                'status' => 'success',
                            ]);
                        }

                        $this->js('window.location.href = '.json_encode(static::getUrl()));
                    } catch (ModuleInstallationException $e) {
                        Notification::make()
                            ->title(__('modules.filament.notifications.cannot_install', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function enableModule(string $name, ModuleManagerServiceInterface $moduleManager): void
    {
        try {
            // Get display name and installation status before enabling
            $moduleName = new ModuleName($name);
            $module = $moduleManager->find($moduleName);
            $displayName = $module?->displayName() ?? $name;
            $wasInstalled = $module?->isInstalled() ?? false;

            // Enable will run migrations only if not already installed
            $moduleManager->enable($moduleName);

            // Build notification message
            $message = __('modules.filament.notifications.enabled', ['name' => $displayName]);

            // Only mention migrations if this was a fresh install
            if (! $wasInstalled) {
                $message .= ' '.__('modules.filament.notifications.migrations_run_first_install');
            }

            Notification::make()
                ->title($message)
                ->success()
                ->send();
        } catch (ModuleNotFoundException|ModuleAlreadyEnabledException|ModuleDependencyException $e) {
            Notification::make()
                ->title(__('modules.filament.notifications.cannot_enable', ['error' => $e->getMessage()]))
                ->danger()
                ->send();
        }
    }

    public function disableModule(string $name, ModuleManagerServiceInterface $moduleManager): void
    {
        try {
            // Get display name before disabling
            $module = $moduleManager->find(new ModuleName($name));
            $displayName = $module?->displayName() ?? $name;

            $moduleManager->disable(new ModuleName($name));

            Notification::make()
                ->title(__('modules.filament.notifications.disabled', ['name' => $displayName]))
                ->success()
                ->send();
        } catch (ModuleNotFoundException|ModuleAlreadyDisabledException|ModuleDependencyException $e) {
            Notification::make()
                ->title(__('modules.filament.notifications.cannot_disable', ['error' => $e->getMessage()]))
                ->danger()
                ->send();
        }
    }

    public function confirmUninstall(string $name): void
    {
        $this->confirmingUninstall = $name;
        $this->confirmDeleteData = false;
        $this->dispatch('open-modal', id: 'confirm-uninstall');
    }

    public function cancelUninstall(): void
    {
        $this->confirmingUninstall = null;
        $this->confirmDeleteData = false;
        $this->dispatch('close-modal', id: 'confirm-uninstall');
    }

    public function uninstallModule(ModuleManagerServiceInterface $moduleManager): void
    {
        $name = $this->confirmingUninstall;

        if ($name === null) {
            return;
        }

        try {
            // Get display name before uninstalling
            $module = $moduleManager->find(new ModuleName($name));
            $displayName = $module?->displayName() ?? $name;

            $moduleManager->uninstall(new ModuleName($name), $this->confirmDeleteData);

            // Build notification message
            $message = __('modules.filament.notifications.uninstalled', ['name' => $displayName]);
            if ($this->confirmDeleteData) {
                $message .= ' '.__('modules.filament.notifications.data_deleted');
            }

            $this->confirmingUninstall = null;
            $this->confirmDeleteData = false;

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'confirm-uninstall');
        } catch (ModuleNotFoundException|ModuleCannotUninstallException $e) {
            Notification::make()
                ->title(__('modules.filament.notifications.cannot_uninstall', ['error' => $e->getMessage()]))
                ->danger()
                ->send();
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function updatedSearch(): void
    {
        // Livewire will re-render automatically
    }

    /**
     * @return array<string, Module>
     */
    public function getDependents(string $name, ModuleManagerServiceInterface $moduleManager): array
    {
        try {
            $dependents = $moduleManager->getDependents(new ModuleName($name));

            return collect($dependents->all())
                ->mapWithKeys(fn (Module $m) => [$m->name()->value => $m])
                ->all();
        } catch (ModuleNotFoundException) {
            return [];
        }
    }

    private function resolveUploadedFile(mixed $tempFile): UploadedFile
    {
        if ($tempFile instanceof TemporaryUploadedFile) {
            return new UploadedFile(
                $tempFile->getRealPath(),
                $tempFile->getClientOriginalName(),
                $tempFile->getMimeType(),
                null,
                true
            );
        }

        if (is_string($tempFile)) {
            $filePath = storage_path('app/'.$tempFile);

            if (! file_exists($filePath)) {
                throw ModuleInstallationException::invalidZip();
            }

            return new UploadedFile(
                $filePath,
                basename($filePath),
                'application/zip',
                null,
                true
            );
        }

        throw ModuleInstallationException::invalidZip();
    }
}
