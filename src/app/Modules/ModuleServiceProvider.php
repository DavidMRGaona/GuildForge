<?php

declare(strict_types=1);

namespace App\Modules;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Modules\DTOs\ModuleRouteDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PagePrefixDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Realtime\Contracts\RealtimeChannelInterface;
use App\Application\Registration\Contracts\RegistrationFieldInterface;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Surveys\Contracts\SurveySubjectSourceInterface;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Get the module name (kebab-case).
     */
    abstract public function moduleName(): string;

    /**
     * Get the module's base path.
     */
    protected function modulePath(string $path = ''): string
    {
        $basePath = config('modules.path').'/'.$this->moduleName();

        return $path !== '' ? $basePath.'/'.ltrim($path, '/') : $basePath;
    }

    /**
     * Get the module's base path (public accessor).
     */
    public function getModulePath(): string
    {
        return $this->modulePath();
    }

    public function register(): void
    {
        $configPath = $this->modulePath('config/module.php');

        if (file_exists($configPath)) {
            $this->forceLoadConfig($configPath, 'modules.'.$this->moduleName());
        }

        // Release defaults (config/settings.php) with the values saved in the panel on top
        $settings = $this->app->make(ModuleSettingsStoreInterface::class)
            ->effective($this->moduleName(), $this->modulePath());

        if ($settings !== []) {
            config()->set("modules.settings.{$this->moduleName()}", $settings);
        }
    }

    /**
     * Load a config file even when config:cache is active.
     *
     * mergeConfigFrom() is a no-op after config:cache because
     * the repository ignores new files. This method uses
     * config()->set() which always works.
     */
    protected function forceLoadConfig(string $path, string $key): void
    {
        if (! file_exists($path)) {
            return;
        }

        $config = require $path;
        config()->set($key, array_merge($config, config($key, [])));
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();
        $this->loadPolicies();
    }

    protected function loadRoutes(): void
    {
        $webRoutes = $this->modulePath('routes/web.php');
        $apiRoutes = $this->modulePath('routes/api.php');

        if (file_exists($webRoutes)) {
            Route::middleware('web')
                ->group($webRoutes);
        }

        if (file_exists($apiRoutes)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($apiRoutes);
        }
    }

    protected function loadViews(): void
    {
        $viewsPath = $this->modulePath('resources/views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleName());
        }
    }

    protected function loadTranslations(): void
    {
        $langPath = $this->modulePath('lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleName());
        }
    }

    protected function loadMigrations(): void
    {
        $migrationsPath = $this->modulePath('database/migrations');

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    protected function loadPolicies(): void
    {
        foreach ($this->registerPolicies() as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    // ==================
    // Lifecycle Hooks
    // ==================

    /**
     * Not called by the core: enabling a module runs its migrations and seeders,
     * not this hook. Kept so existing overrides still compile.
     */
    public function onEnable(): void
    {
        // Override in subclass
    }

    /**
     * Not called by the core: disabling a module does not run this hook.
     * Kept so existing overrides still compile.
     */
    public function onDisable(): void
    {
        // Override in subclass
    }

    // ==================
    // Module Integration
    // ==================

    /**
     * Register policies provided by this module.
     * Override in subclass to provide model policies.
     *
     * @return array<class-string, class-string> Map of model class to policy class
     */
    public function registerPolicies(): array
    {
        return [];
    }

    /**
     * Register permissions provided by this module.
     *
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [];
    }

    /**
     * Register navigation items provided by this module.
     *
     * @return array<NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [];
    }

    /**
     * Register slot components provided by this module.
     * Slots allow modules to inject Vue components into layout positions.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [];
    }

    /**
     * Get the Filament form schema for module settings, rendered by the core's generic module
     * settings page. That page is only a fallback for modules without a page of their own:
     * once getSettingsPage() declares one, this schema is no longer used.
     *
     * @return array<Component>
     */
    public function getSettingsSchema(): array
    {
        return [];
    }

    /**
     * The module's own settings page in the «Configuración» cluster, guarded by a permission of
     * the module. The «Configuración» button of the modules page opens it, and the generic
     * settings page no longer edits the module.
     *
     * The page must be one of this provider's registerFilamentPages() (never a page of the core or of
     * another module) and join App\Filament\Clusters\SettingsCluster; otherwise neither page edits
     * the module's settings (it fails closed and logs a warning).
     *
     * @return class-string<Page>|null
     */
    public function getSettingsPage(): ?string
    {
        return null;
    }

    /**
     * Register page prefixes provided by this module.
     * Allows module Vue pages to be resolved by Inertia. With usesCoreLayout the core renders
     * its persistent DefaultLayout around them, and the pages must not wrap one of their own.
     *
     * @return array<PagePrefixDTO>
     */
    public function registerPagePrefixes(): array
    {
        return [];
    }

    /**
     * Register public routes for menu item configuration.
     * Allows module routes to appear in the menu item dropdown.
     *
     * @return array<ModuleRouteDTO>
     */
    public function registerRoutes(): array
    {
        return [];
    }

    /**
     * Register calendar sources provided by this module.
     * Allows module entries to appear in the public calendars.
     *
     * @return array<class-string<CalendarEntrySourceInterface>>
     */
    public function registerCalendarSources(): array
    {
        return [];
    }

    /**
     * Register calendar attendance sources provided by this module.
     * Tell the core which public calendar entries each person takes part in, for their
     * personal calendar ("only mine" filter and subscription feed).
     *
     * @return array<class-string<CalendarAttendanceSourceInterface>>
     */
    public function registerCalendarAttendanceSources(): array
    {
        return [];
    }

    /**
     * Register indexed search sources provided by this module.
     * Allows module records to be found in the global site search; each type is prefixed with the module.
     *
     * @return array<class-string<IndexedSearchSourceInterface>>
     */
    public function registerSearchSources(): array
    {
        return [];
    }

    /**
     * Register media usage sources provided by this module.
     * Tells the panel media library which of the module's records use stored files and which folders it claims;
     * each type is prefixed with the module.
     *
     * @return array<class-string<MediaUsageSourceInterface>>
     */
    public function registerMediaSources(): array
    {
        return [];
    }

    /**
     * Register editor text sources provided by this module.
     * Tells the core which of the module's texts are written with a rich editor, so the command that moves
     * editor images to Cloudinary moves theirs too and never deletes a local copy one of them still names;
     * each type is prefixed with the module.
     *
     * @return array<class-string<EditorTextSourceInterface>>
     */
    public function registerEditorTextSources(): array
    {
        return [];
    }

    /**
     * Register the private real-time channels this module offers (core#493).
     * Each name starts with the module name and a dot (`<module>.discussion.{discussion}`); the core checks every
     * subscription with the channel's authorize() and fails closed. Messages carry only ids and numbers.
     *
     * @return array<class-string<RealtimeChannelInterface>>
     */
    public function registerRealtimeChannels(): array
    {
        return [];
    }

    /**
     * Register survey subject sources provided by this module.
     * Allows module records (tables, tournaments…) to be surveyed by the surveys module.
     *
     * @return array<class-string<SurveySubjectSourceInterface>>
     */
    public function registerSurveySubjectSources(): array
    {
        return [];
    }

    /**
     * Register publication sources provided by this module.
     * Lets other modules announce the module's records elsewhere (a forum, a channel) and follow their changes.
     *
     * @return array<class-string<PublicationSourceInterface>>
     */
    public function registerPublicationSources(): array
    {
        return [];
    }

    /**
     * Register linkable record sources provided by this module.
     * Lets other modules link their data to the module's records (drafts and panel-only ones
     * included, as each viewer may see them) without depending on the module.
     *
     * @return array<class-string<LinkableRecordSourceInterface>>
     */
    public function registerLinkableRecordSources(): array
    {
        return [];
    }

    /**
     * Register the requests this module adds to event proposals made from the web (core#581).
     * Each key starts with the module name and a dot (`<module>.registrations`); the core draws
     * their fields, checks the values and calls apply() inside the approval transaction.
     *
     * @return array<class-string<EventProposalRequestInterface>>
     */
    public function registerEventProposalRequests(): array
    {
        return [];
    }

    /**
     * Register the optional fields this module adds to the registration form (core#699).
     * Each key starts with the module name and a dot (`<module>.subscribe`); the core draws them
     * (checkboxes, always unticked), checks the values and, once the account exists, dispatches
     * RegistrationFieldsSubmitted for a queued listener. A field that throws is left out.
     *
     * @return array<class-string<RegistrationFieldInterface>>
     */
    public function registerRegistrationFields(): array
    {
        return [];
    }

    /**
     * Register knowledge capabilities provided by this module.
     * Guides use them to show only what a person can do; each reuses the domain rule that decides the action.
     *
     * @return array<class-string<KnowledgeCapabilityInterface>>
     */
    public function registerKnowledgeCapabilities(): array
    {
        return [];
    }

    /**
     * Register the mail categories of this module's notifications.
     * Each key starts with "<module name>."; every mail notification of the module returns one
     * of them from HasMailCategory::mailCategory(), so people can turn the optional ones off.
     *
     * @return list<MailCategoryDTO>
     */
    public function registerMailCategories(): array
    {
        return [];
    }

    /**
     * Register Filament pages provided by this module.
     * Override in subclass to provide Filament pages.
     *
     * @return array<class-string<Page>>
     */
    public function registerFilamentPages(): array
    {
        return [];
    }
}
