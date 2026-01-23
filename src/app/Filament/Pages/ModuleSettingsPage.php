<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
final class ModuleSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.module-settings';

    protected static bool $shouldRegisterNavigation = false;

    public string $module = '';

    // Primitive properties for module data (Livewire-compatible)
    public ?string $moduleDisplayName = null;

    public ?string $moduleVersion = null;

    public ?string $moduleAuthor = null;

    public ?string $moduleDescription = null;

    public ?string $moduleProvider = null;

    public ?string $moduleNamespace = null;

    public bool $moduleIsEnabled = false;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getRoutePath(): string
    {
        return '/modules/{module}/settings';
    }

    public static function getSlug(): string
    {
        return 'module-settings';
    }

    public function getTitle(): string
    {
        return __('modules.filament.settings_page.title', ['name' => $this->moduleDisplayName ?? $this->module]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(string $module): void
    {
        $this->module = $module;

        /** @var ModuleManagerServiceInterface $moduleManager */
        $moduleManager = app(ModuleManagerServiceInterface::class);

        try {
            $moduleEntity = $moduleManager->findByName($module);
        } catch (ModuleNotFoundException) {
            $moduleEntity = null;
        }

        if ($moduleEntity === null) {
            $this->redirect(ModulesPage::getUrl());

            return;
        }

        // Redirect if module is disabled
        if ($moduleEntity->isDisabled()) {
            Notification::make()
                ->title(__('modules.filament.notifications.cannot_enable', ['error' => 'Module is disabled']))
                ->warning()
                ->send();

            $this->redirect(ModulesPage::getUrl());

            return;
        }

        // Store primitive properties for Livewire compatibility
        $this->moduleDisplayName = $moduleEntity->displayName();
        $this->moduleVersion = $moduleEntity->version()->value();
        $this->moduleAuthor = $moduleEntity->author();
        $this->moduleDescription = $moduleEntity->description();
        $this->moduleProvider = $moduleEntity->provider();
        $this->moduleNamespace = $moduleEntity->namespace();
        $this->moduleIsEnabled = $moduleEntity->isEnabled();

        // Load current settings
        $settings = $moduleManager->getSettings(new ModuleName($module));
        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        $schema = $this->getModuleSettingsSchema();

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getModuleSettingsSchema(): array
    {
        if ($this->module === '') {
            return [];
        }

        // Get the already-loaded provider from the ModuleLoader
        /** @var ModuleLoader $moduleLoader */
        $moduleLoader = app(ModuleLoader::class);

        $provider = $moduleLoader->getProvider($this->module);

        if ($provider === null) {
            return [];
        }

        return $provider->getSettingsSchema();
    }

    public function hasSettings(): bool
    {
        return !empty($this->getModuleSettingsSchema());
    }

    public function save(): void
    {
        /** @var ModuleManagerServiceInterface $moduleManager */
        $moduleManager = app(ModuleManagerServiceInterface::class);

        $formData = $this->form->getState();

        $moduleManager->updateSettings(new ModuleName($this->module), $formData);

        Notification::make()
            ->title(__('modules.filament.settings_page.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        if (!$this->hasSettings()) {
            return [];
        }

        return [
            \Filament\Actions\Action::make('save')
                ->label(__('modules.filament.settings_page.save'))
                ->submit('save'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ModulesPage::getUrl() => __('modules.filament.page.navigation_label'),
            '' => $this->getTitle(),
        ];
    }
}
