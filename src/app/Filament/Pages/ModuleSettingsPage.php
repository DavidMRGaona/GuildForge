<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Support\ModuleSettingsPages;
use App\Modules\ModuleLoader;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

/**
 * @property Schema $form
 */
final class ModuleSettingsPage extends Page implements HasForms
{
    use ChecksPermissions;
    use HasKnowledgeGuideAction;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.module-settings';

    protected static bool $shouldRegisterNavigation = false;

    /** form() and save() follow it: a client must not swap it for another module. */
    #[Locked]
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

    public static function getRoutePath(Panel $panel): string
    {
        return '/modules/{module}/settings';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'module-settings';
    }

    public function getTitle(): string
    {
        return __('modules.filament.settings_page.title', ['name' => $this->moduleDisplayName ?? $this->module]);
    }

    public static function canAccess(): bool
    {
        return self::userCan('modules.configure');
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
                ->title(__('modules.filament.settings_page.module_disabled'))
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

        // A module with its own settings page is edited there, under the module's permission:
        // old links land on it, and a declared page that cannot be used leaves nothing to edit
        $pages = app(ModuleSettingsPages::class);

        if ($pages->declaresPage($module)) {
            $page = $pages->usablePage($module);

            abort_if($page === null, 404);

            $this->redirect($page::getUrl());

            return;
        }

        // The generic page only edits getSettingsSchema(): a module without one has nothing here
        if (! $this->hasSettings()) {
            Notification::make()
                ->title(__('modules.filament.settings_page.no_settings'))
                ->warning()
                ->send();

            $this->redirect(ModulesPage::getUrl());

            return;
        }

        // Load current settings
        $settings = $moduleManager->getSettings(new ModuleName($module));
        $this->form->fill($settings);
    }

    public function form(Schema $form): Schema
    {
        $schema = $this->getModuleSettingsSchema();

        return $form
            ->components($schema)
            ->statePath('data');
    }

    /**
     * @return array<Component>
     */
    protected function getModuleSettingsSchema(): array
    {
        // Fallback only: a module that declares its own settings page is never edited here
        if ($this->module === '' || app(ModuleSettingsPages::class)->declaresPage($this->module)) {
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
        return ! empty($this->getModuleSettingsSchema());
    }

    public function save(): void
    {
        // Nothing to save, and an empty state would blank the module's settings file
        if (! $this->hasSettings()) {
            return;
        }

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
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if (! $this->hasSettings()) {
            return [];
        }

        return [
            Action::make('save')
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

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([$this->knowledgeGuideAction('core.panel.modules')]);
    }
}
