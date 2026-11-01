<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Application\Content\Services\TrixContentMigratorInterface;
use App\Application\Content\Services\TrixHtmlConverterInterface;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Application\Mail\Services\MailBrandingServiceInterface;
use App\Application\Mail\Services\MailConfigurationServiceInterface;
use App\Application\Mail\Services\MailStatisticsServiceInterface;
use App\Application\Mail\Services\MailTestServiceInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\HostEnvironmentProviderInterface;
use App\Application\Modules\Services\ModuleCompatibilityChecker;
use App\Application\Modules\Services\ModuleCompatibilityServiceInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Modules\Services\ModulePageRegistryInterface;
use App\Application\Modules\Services\ModulePermissionRegistryInterface;
use App\Application\Modules\Services\ModuleRouteRegistryInterface;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;
use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Application\Navigation\Services\RouteRegistryInterface;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\ContactServiceInterface;
use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Application\Services\ImageOptimizationServiceInterface;
use App\Application\Services\LegalPageServiceInterface;
use App\Application\Services\LogContextProviderInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\SitemapQueryServiceInterface;
use App\Application\Services\SlugRedirectService;
use App\Application\Services\SlugRedirectServiceInterface;
use App\Application\Services\StorageTimezoneConverterInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface;
use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Application\Updates\Services\GitHubReleaseFetcherInterface;
use App\Application\Updates\Services\ModuleBackupServiceInterface;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Application\Updates\Services\ModulePackageInstallerInterface;
use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Application\Updates\Services\ReleaseChannelPolicy;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Domain\Repositories\ArticleRepositoryInterface;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\Repositories\GalleryRepositoryInterface;
use App\Domain\Repositories\PhotoRepositoryInterface;
use App\Domain\Repositories\SlugRedirectRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Infrastructure\Auth\UuidEloquentUserProvider;
use App\Infrastructure\Calendar\Services\CalendarAggregatorService;
use App\Infrastructure\Calendar\Services\CalendarSourceRegistry;
use App\Infrastructure\Content\Services\TrixContentMigrator;
use App\Infrastructure\Content\Services\TrixHtmlConverter;
use App\Infrastructure\Cookies\CookieDeclarationRegistry;
use App\Infrastructure\Factories\EloquentResponseDTOFactory;
use App\Infrastructure\Legal\LegalMailNoticeRegistry;
use App\Infrastructure\Legal\LegalMailNoticeService;
use App\Infrastructure\Mail\Listeners\CheckQuotaBeforeSending;
use App\Infrastructure\Mail\Listeners\LogSentEmail;
use App\Infrastructure\Mail\Services\EmailQuotaService;
use App\Infrastructure\Mail\Services\MailBrandingService;
use App\Infrastructure\Mail\Services\MailConfigurationService;
use App\Infrastructure\Mail\Services\MailStatisticsService;
use App\Infrastructure\Mail\Services\MailTestService;
use App\Infrastructure\Mail\Ses\SnsMessageValidator;
use App\Infrastructure\Mail\Ses\SnsMessageValidatorInterface;
use App\Infrastructure\Modules\Listeners\BuildModuleAssetsOnEnabled;
use App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange;
use App\Infrastructure\Modules\Listeners\SyncPermissionsOnModuleChange;
use App\Infrastructure\Modules\Services\EnabledModulesResolver;
use App\Infrastructure\Modules\Services\HostEnvironmentProvider;
use App\Infrastructure\Modules\Services\ModuleAssetBuilder;
use App\Infrastructure\Modules\Services\ModuleCompatibilityService;
use App\Infrastructure\Modules\Services\ModuleContextService;
use App\Infrastructure\Modules\Services\ModuleDependencyResolver;
use App\Infrastructure\Modules\Services\ModuleDiscoveryService;
use App\Infrastructure\Modules\Services\ModuleManagerService;
use App\Infrastructure\Modules\Services\ModuleManifestReader;
use App\Infrastructure\Modules\Services\ModuleMigrationAnalyzer;
use App\Infrastructure\Modules\Services\ModuleMigrationRunner;
use App\Infrastructure\Modules\Services\ModuleNavigationRegistry;
use App\Infrastructure\Modules\Services\ModulePageRegistry;
use App\Infrastructure\Modules\Services\ModulePermissionRegistry;
use App\Infrastructure\Modules\Services\ModuleRouteRegistry;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use App\Infrastructure\Modules\Services\ModuleSlotRegistry;
use App\Infrastructure\Modules\Services\StubRenderer;
use App\Infrastructure\Navigation\Listeners\ActivateMenuItemsOnModuleEnabled;
use App\Infrastructure\Navigation\Listeners\DeactivateMenuItemsOnModuleDisabled;
use App\Infrastructure\Navigation\Listeners\DeleteMenuItemsOnModuleUninstalled;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Navigation\Persistence\Eloquent\Repositories\EloquentMenuItemRepository;
use App\Infrastructure\Navigation\Services\MenuItemHrefResolver;
use App\Infrastructure\Navigation\Services\MenuService;
use App\Infrastructure\Navigation\Services\RouteRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentArticleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEmailLogRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentGalleryRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentModuleRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPhotoRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentSlugRedirectRepository;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use App\Infrastructure\Seo\SeoDescriptionBuilder;
use App\Infrastructure\Seo\SeoMetadataFactory;
use App\Infrastructure\Seo\StructuredDataBuilder;
use App\Infrastructure\Services\AboutPageService;
use App\Infrastructure\Services\ArticleQueryService;
use App\Infrastructure\Services\AuthService;
use App\Infrastructure\Services\ContactService;
use App\Infrastructure\Services\DashboardWidgetConfigService;
use App\Infrastructure\Services\EmailVerificationPolicy;
use App\Infrastructure\Services\EventQueryService;
use App\Infrastructure\Services\GalleryQueryService;
use App\Infrastructure\Services\HeroSlideQueryService;
use App\Infrastructure\Services\ImageOptimizationService;
use App\Infrastructure\Services\LegalPageService;
use App\Infrastructure\Services\Logging\HttpLogContextProvider;
use App\Infrastructure\Services\SettingsService;
use App\Infrastructure\Services\SiteBrandingService;
use App\Infrastructure\Services\SitemapQueryService;
use App\Infrastructure\Services\TagQueryService;
use App\Infrastructure\Services\ThemeSettingsService;
use App\Infrastructure\Services\TimezoneService;
use App\Infrastructure\Services\UserModelQueryService;
use App\Infrastructure\Services\UserService;
use App\Infrastructure\Storage\Cloudinary\CloudinaryPathMapper;
use App\Infrastructure\Storage\Cloudinary\CloudinaryStorageAdapter;
use App\Infrastructure\Surveys\Services\NullSubjectEndSurveyChoice;
use App\Infrastructure\Surveys\Services\NullSurveySubjectLinks;
use App\Infrastructure\Surveys\Services\SurveySubjectSourceRegistry;
use App\Infrastructure\Time\StorageTimezoneConverter;
use App\Infrastructure\Updates\Services\CoreUpdateChecker;
use App\Infrastructure\Updates\Services\CoreVersionService;
use App\Infrastructure\Updates\Services\GitHubReleaseFetcher;
use App\Infrastructure\Updates\Services\ModuleBackupService;
use App\Infrastructure\Updates\Services\ModuleHealthChecker;
use App\Infrastructure\Updates\Services\ModulePackageInstaller;
use App\Infrastructure\Updates\Services\ModuleUpdateChecker;
use App\Infrastructure\Updates\Services\ModuleUpdater;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use App\Modules\ModuleLoader;
use App\Policies\ArticlePolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\HeroSlidePolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\RolePolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Cloudinary\Cloudinary;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Domain Repository bindings
        $this->app->bind(ArticleRepositoryInterface::class, EloquentArticleRepository::class);
        $this->app->bind(EventRepositoryInterface::class, EloquentEventRepository::class);
        $this->app->bind(GalleryRepositoryInterface::class, EloquentGalleryRepository::class);
        $this->app->bind(PhotoRepositoryInterface::class, EloquentPhotoRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SlugRedirectRepositoryInterface::class, EloquentSlugRedirectRepository::class);

        // Slug redirect system
        $this->app->singleton(SlugRedirectServiceInterface::class, SlugRedirectService::class);

        // Application Service bindings
        $this->app->singleton(SettingsServiceInterface::class, SettingsService::class);
        $this->app->singleton(
            TimezoneServiceInterface::class,
            fn (): TimezoneService => new TimezoneService((string) config('app.display_timezone')),
        );
        $this->app->bind(StorageTimezoneConverterInterface::class, StorageTimezoneConverter::class);
        $this->app->singleton(LegalMailNoticeRegistryInterface::class, LegalMailNoticeRegistry::class);
        $this->app->singleton(CookieDeclarationRegistryInterface::class, CookieDeclarationRegistry::class);
        $this->app->singleton(LegalMailNoticeServiceInterface::class, LegalMailNoticeService::class);
        $this->app->singleton(DashboardWidgetConfigServiceInterface::class, DashboardWidgetConfigService::class);
        $this->app->singleton(ThemeSettingsServiceInterface::class, ThemeSettingsService::class);
        $this->app->singleton(ImageOptimizationServiceInterface::class, ImageOptimizationService::class);
        $this->app->singleton(AuthServiceInterface::class, AuthService::class);
        $this->app->singleton(EmailVerificationPolicyInterface::class, EmailVerificationPolicy::class);
        $this->app->singleton(UserModelQueryServiceInterface::class, UserModelQueryService::class);
        $this->app->singleton(UserServiceInterface::class, UserService::class);
        $this->app->singleton(LegalPageServiceInterface::class, LegalPageService::class);
        $this->app->singleton(ContactServiceInterface::class, ContactService::class);

        // Mail system bindings
        $this->app->singleton(MailConfigurationServiceInterface::class, MailConfigurationService::class);
        $this->app->singleton(MailTestServiceInterface::class, MailTestService::class);
        $this->app->scoped(MailBrandingServiceInterface::class, MailBrandingService::class);
        $this->app->bind(EmailLogRepositoryInterface::class, EloquentEmailLogRepository::class);
        $this->app->singleton(EmailQuotaServiceInterface::class, EmailQuotaService::class);
        $this->app->singleton(MailStatisticsServiceInterface::class, MailStatisticsService::class);
        $this->app->singleton(SnsMessageValidatorInterface::class, SnsMessageValidator::class);

        // Log context provider (uses request scoped binding)
        $this->app->bind(LogContextProviderInterface::class, fn ($app) => new HttpLogContextProvider($app['request'] ?? null));

        // Factory bindings
        $this->app->singleton(ResponseDTOFactoryInterface::class, EloquentResponseDTOFactory::class);

        // Query Service bindings (singletons for performance)
        $this->app->singleton(EventQueryServiceInterface::class, EventQueryService::class);
        $this->app->singleton(ArticleQueryServiceInterface::class, ArticleQueryService::class);
        $this->app->singleton(GalleryQueryServiceInterface::class, GalleryQueryService::class);
        $this->app->singleton(HeroSlideQueryServiceInterface::class, HeroSlideQueryService::class);
        $this->app->singleton(SitemapQueryServiceInterface::class, SitemapQueryService::class);
        $this->app->singleton(TagQueryServiceInterface::class, TagQueryService::class);
        $this->app->singleton(AboutPageServiceInterface::class, AboutPageService::class);
        $this->app->singleton(SiteBrandingServiceInterface::class, SiteBrandingService::class);

        // SEO: bind, not singleton — they read config and the current request URL
        $this->app->bind(CloudinaryImageUrlBuilder::class, fn (): CloudinaryImageUrlBuilder => new CloudinaryImageUrlBuilder(
            (string) config('cloudinary.cloud_name', ''),
            (string) config('cloudinary.prefix', ''),
        ));
        $this->app->bind(SeoDescriptionBuilder::class, fn (): SeoDescriptionBuilder => new SeoDescriptionBuilder((string) config('app.display_timezone')));
        $this->app->bind(StructuredDataBuilder::class, fn (): StructuredDataBuilder => new StructuredDataBuilder((string) config('app.display_timezone')));
        $this->app->bind(SeoMetadataFactoryInterface::class, SeoMetadataFactory::class);

        // Module system bindings
        $this->app->bind(ModuleRepositoryInterface::class, EloquentModuleRepository::class);

        $this->app->singleton(ModuleDiscoveryService::class, fn ($app) => new ModuleDiscoveryService(
            modulesPath: config('modules.path'),
            logger: $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(ModuleAssetBuilder::class, fn ($app) => new ModuleAssetBuilder(
            modulesPath: config('modules.path'),
        ));

        $this->app->singleton(ModuleDependencyResolver::class, fn () => new ModuleDependencyResolver);

        $this->app->singleton(CoreTableRegistry::class);
        $this->app->singleton(ModuleSchemaGuard::class);
        $this->app->bind(ModuleMigrationAnalyzerInterface::class, ModuleMigrationAnalyzer::class);

        $this->app->singleton(ModuleMigrationRunner::class, fn ($app) => new ModuleMigrationRunner(
            modulesPath: config('modules.path'),
            analyzer: $app->make(ModuleMigrationAnalyzerInterface::class),
            schemaGuard: $app->make(ModuleSchemaGuard::class),
        ));

        $this->app->singleton(ModuleSeederRunner::class, fn ($app) => new ModuleSeederRunner(
            modulesPath: config('modules.path'),
            analyzer: $app->make(ModuleMigrationAnalyzerInterface::class),
        ));

        $this->app->singleton(ModuleManagerServiceInterface::class, ModuleManagerService::class);

        // Module compatibility gate: resolved while AdminPanelProvider registers, so none of these
        // may depend on services that capture modules.path when they are built
        $this->app->singleton(ConstraintMatcher::class);
        $this->app->singleton(ModuleManifestReader::class, fn ($app): ModuleManifestReader => new ModuleManifestReader($app->make(LoggerInterface::class)));
        $this->app->singleton(ModuleCompatibilityChecker::class);
        $this->app->singleton(HostEnvironmentProviderInterface::class, HostEnvironmentProvider::class);
        $this->app->singleton(ModuleCompatibilityServiceInterface::class, ModuleCompatibilityService::class);
        $this->app->singleton(EnabledModulesResolverInterface::class, EnabledModulesResolver::class);

        // Module SDK services
        $this->app->singleton(StubRenderer::class, fn () => new StubRenderer(base_path('stubs/modules')));

        $this->app->singleton(ModuleScaffoldingServiceInterface::class, fn ($app) => new ModuleScaffoldingService(
            $app->make(StubRenderer::class),
            config('modules.path'),
            $app->make(CoreVersionServiceInterface::class),
            resource_path('js/pages'),
        ));

        $this->app->singleton(ModuleContextServiceInterface::class, ModuleContextService::class);
        $this->app->singleton(ModulePermissionRegistryInterface::class, ModulePermissionRegistry::class);
        $this->app->singleton(ModuleNavigationRegistryInterface::class, ModuleNavigationRegistry::class);
        $this->app->singleton(ModuleSlotRegistryInterface::class, ModuleSlotRegistry::class);
        $this->app->singleton(ModulePageRegistryInterface::class, ModulePageRegistry::class);
        $this->app->singleton(ModuleRouteRegistryInterface::class, ModuleRouteRegistry::class);

        // Calendar system bindings
        $this->app->singleton(CalendarSourceRegistryInterface::class, CalendarSourceRegistry::class);
        $this->app->singleton(CalendarAggregatorServiceInterface::class, CalendarAggregatorService::class);

        // Survey subject sources offered by modules
        $this->app->singleton(SurveySubjectSourceRegistryInterface::class, SurveySubjectSourceRegistry::class);
        // No links from a subject's page until the surveys module binds its own, whichever registers first
        $this->app->singletonIf(SurveySubjectLinksInterface::class, NullSurveySubjectLinks::class);
        // Nor an end survey to choose in a subject's form
        $this->app->singletonIf(SubjectEndSurveyChoiceInterface::class, NullSubjectEndSurveyChoice::class);

        // Navigation system bindings
        $this->app->bind(MenuItemRepositoryInterface::class, EloquentMenuItemRepository::class);
        $this->app->singleton(MenuItemHrefResolverInterface::class, MenuItemHrefResolver::class);
        $this->app->singleton(MenuServiceInterface::class, MenuService::class);
        $this->app->singleton(RouteRegistryInterface::class, RouteRegistry::class);

        // Rich text written with the Trix editor (admin panel before core 3.0)
        $this->app->singleton(TrixHtmlConverterInterface::class, TrixHtmlConverter::class);
        $this->app->singleton(TrixContentMigratorInterface::class, fn ($app) => new TrixContentMigrator(
            $app->make(TrixHtmlConverterInterface::class),
            $app->make(SettingsServiceInterface::class),
            storage_path('app/backups/rich-text'),
        ));

        // Update system bindings
        $this->app->singleton(GitHubReleaseFetcherInterface::class, GitHubReleaseFetcher::class);
        $this->app->singleton(ModuleBackupServiceInterface::class, ModuleBackupService::class);
        $this->app->singleton(ModuleHealthCheckerInterface::class, ModuleHealthChecker::class);
        $this->app->singleton(ReleaseChannelPolicy::class, fn (): ReleaseChannelPolicy => new ReleaseChannelPolicy(
            (bool) config('updates.behavior.allow_prereleases', false),
        ));
        $this->app->singleton(ModuleUpdateCheckerInterface::class, ModuleUpdateChecker::class);
        $this->app->singleton(ModulePackageInstallerInterface::class, ModulePackageInstaller::class);
        $this->app->singleton(ModulePostUpdateRunnerInterface::class, ProcessModulePostUpdateRunner::class);
        $this->app->singleton(ModuleUpdaterInterface::class, ModuleUpdater::class);
        $this->app->singleton(CoreVersionServiceInterface::class, CoreVersionService::class);
        $this->app->singleton(CoreUpdateCheckerInterface::class, CoreUpdateChecker::class);

        // Module loader (for booting enabled modules)
        $this->app->singleton(ModuleLoader::class, fn ($app) => new ModuleLoader(
            $app,
            $app->make(EnabledModulesResolverInterface::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register custom UUID-aware user provider to handle old integer-based cookies gracefully
        Auth::provider('uuid-eloquent', fn ($app, array $config) => new UuidEloquentUserProvider($app['hash'], $config['model']));

        // Rate limiter for login: 5 attempts per minute per IP + email combo
        RateLimiter::for(
            'login',
            static fn (Request $request) => Limit::perMinute(5)->by(
                ($request->input('email', '') ?: '').'|'.($request->ip() ?? 'unknown')
            )
        );

        // Rate limiter for contact form: 3 submissions per minute per IP
        RateLimiter::for(
            'contact',
            static fn (Request $request) => Limit::perMinute(3)->by($request->ip() ?? 'unknown')
        );

        Gate::policy(UserModel::class, UserPolicy::class);
        Gate::policy(RoleModel::class, RolePolicy::class);
        Gate::policy(EventModel::class, EventPolicy::class);
        Gate::policy(ArticleModel::class, ArticlePolicy::class);
        Gate::policy(GalleryModel::class, GalleryPolicy::class);
        Gate::policy(HeroSlideModel::class, HeroSlidePolicy::class);
        Gate::policy(TagModel::class, TagPolicy::class);
        Gate::policy(MenuItemModel::class, MenuItemPolicy::class);

        Event::listen(
            ModuleDisabled::class,
            DeactivateMenuItemsOnModuleDisabled::class,
        );
        Event::listen(
            ModuleEnabled::class,
            ActivateMenuItemsOnModuleEnabled::class,
        );

        // Clear Laravel caches when modules change (required for cached routes in production)
        Event::listen(
            ModuleEnabled::class,
            ClearCachesOnModuleChange::class,
        );
        // Build module Vue component assets when enabled (runs async via queue)
        Event::listen(
            ModuleEnabled::class,
            BuildModuleAssetsOnEnabled::class,
        );
        Event::listen(
            ModuleDisabled::class,
            ClearCachesOnModuleChange::class,
        );
        Event::listen(
            ModuleInstalled::class,
            ClearCachesOnModuleChange::class,
        );
        Event::listen(
            ModuleUpdated::class,
            ClearCachesOnModuleChange::class,
        );
        // New permissions of an enabled or updated module reach the role editor right away
        Event::listen(
            ModuleEnabled::class,
            SyncPermissionsOnModuleChange::class,
        );
        Event::listen(
            ModuleUpdated::class,
            SyncPermissionsOnModuleChange::class,
        );
        Event::listen(
            ModuleUninstalled::class,
            DeleteMenuItemsOnModuleUninstalled::class,
        );

        // Email quota enforcement: check quota before sending
        Event::listen(MessageSending::class, CheckQuotaBeforeSending::class);

        // Email logging: log every sent email to the database
        Event::listen(MessageSent::class, LogSentEmail::class);

        // Email theme: site name, logo, colors and fonts for every mail view
        View::composer(['mail::*', 'emails.*'], static function (ViewContract $view): void {
            $view->with('mailBranding', app(MailBrandingServiceInterface::class)->branding());
        });

        // Notifications without a salutation of their own sign with the site name, not APP_NAME
        View::composer('notifications::email', static function (ViewContract $view): void {
            if (empty($view->getData()['salutation'] ?? null)) {
                $view->with('salutation', new HtmlString(
                    e(__('Regards,')).'<br>'.e(app(MailBrandingServiceInterface::class)->branding()->siteName),
                ));
            }
        });

        // Calendars start on Monday (European convention)
        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component->firstDayOfWeek(1);
        });

        DatePicker::configureUsing(function (DatePicker $component): void {
            $component->firstDayOfWeek(1);
        });

        $this->preserveFilamentV3Defaults();
        $this->showFilamentDatesInDisplayTimezone();
        $this->keepSentenceCaseInBulkDeleteHeadings();

        // Cloudinary driver of the "images" disk (App\Infrastructure\Storage\Cloudinary):
        // URLs built locally, images optimized before upload, missing assets ignored on delete
        Storage::extend('cloudinary', function ($app, $config) {
            $cloudinaryUrl = $config['url'] ?? config('cloudinary.cloud_url');
            $prefix = $config['prefix'] ?? null;

            $cloudinary = new Cloudinary($cloudinaryUrl);
            $imageOptimization = $app->make(ImageOptimizationServiceInterface::class);
            $adapter = new CloudinaryStorageAdapter($cloudinary, new CloudinaryPathMapper($prefix), $imageOptimization);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }

    /**
     * Filament 4 changed several defaults; keep the Filament 3 behaviour the admin panel was built on.
     */
    private function preserveFilamentV3Defaults(): void
    {
        // Filters applied on change, not behind an "Apply" button; no hidden key sort
        // (UUID keys would shuffle rows); every page size, including "all"
        Table::configureUsing(fn (Table $table): Table => $table
            ->deferFilters(false)
            ->defaultKeySort(false)
            ->paginationPageOptions([5, 10, 25, 50, 'all']));

        // Layout components span the full width of their parent grid
        Section::configureUsing(fn (Section $section): Section => $section->columnSpanFull());
        Grid::configureUsing(fn (Grid $grid): Grid => $grid->columnSpanFull());
        Fieldset::configureUsing(fn (Fieldset $fieldset): Fieldset => $fieldset->columnSpanFull());

        // The "images" disk (Cloudinary) serves public URLs; private visibility would try temporary URLs first
        FileUpload::configureUsing(fn (FileUpload $upload): FileUpload => $upload->visibility('public'));
        ImageColumn::configureUsing(fn (ImageColumn $column): ImageColumn => $column->visibility('public'));
        ImageEntry::configureUsing(fn (ImageEntry $entry): ImageEntry => $entry->visibility('public'));
    }

    /**
     * Filament reads and shows dates in the display zone, whatever zone they are stored in.
     */
    private function showFilamentDatesInDisplayTimezone(): void
    {
        $zone = (string) config('app.display_timezone');

        // Pickers only; columns and entries keep the storage zone unless told otherwise
        FilamentTimezone::set($zone);
        TextColumn::configureUsing(fn (TextColumn $column): TextColumn => $column->timezone($zone));
        TextEntry::configureUsing(fn (TextEntry $entry): TextEntry => $entry->timezone($zone));
    }

    /**
     * Spanish sentence case: the bulk delete heading title-cases the plural label, which BaseResource cannot reach.
     */
    private function keepSentenceCaseInBulkDeleteHeadings(): void
    {
        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action): DeleteBulkAction => $action
            ->modalHeading(fn (DeleteBulkAction $action): string => __(
                'filament-actions::delete.multiple.modal.heading',
                ['label' => mb_lcfirst((string) $action->getPluralModelLabel())],
            )));
    }
}
