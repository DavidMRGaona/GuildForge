# Source tree analysis

> Updated: 2026-10-13 | Project: GuildForge core 3.5.1 | Type: Full-stack web monolith

## General structure

```
guildforge/                             # Repository root
├── .github/
│   └── workflows/                      # ci.yml, deploy.yml, reusable-module-release.yml
├── docker/                             # Container configuration
│   ├── nginx/                          # Nginx configuration (development)
│   ├── node/                           # Node container (Vite dev server)
│   ├── php/                            # PHP-FPM Dockerfile and local.ini
│   └── prod/                           # Production entrypoint, nginx, php.ini, supervisord (nginx, php-fpm, queue worker, scheduler)
├── docs/                               # Project documentation (this directory)
├── src/                                # ★ MAIN APPLICATION
├── Makefile                            # 69 targets (make help)
├── docker-compose.yml                  # Development stack (9 services)
├── docker-compose.prod.yml             # Production stack
├── Dockerfile.prod                     # Production Docker image
└── README.md                           # Quick start guide
```

---

## src/ — Laravel application

```
src/
├── artisan                             # ★ Laravel CLI (CLI entry point)
├── composer.json                       # PHP dependencies
├── package.json                        # Node.js dependencies
├── phpunit.xml                         # PHPUnit configuration (3 suites)
├── tsconfig.json                       # TypeScript strict mode
├── vite.config.ts                      # Vite 8: entries, vendor-exports, shared vendor chunks
├── eslint.config.js                    # ESLint for TypeScript/Vue
├── phpstan.neon                        # PHPStan/Larastan level 8
├── pint.json                           # Pint (PHP code style, checked in CI)
├── rector.php                          # Rector for automated refactoring
├── VERSION                             # Core version (modules' requires.core)
├── scripts/
│   └── verify-build.mjs                # Checks the build against the module import map (npm run verify:build, CI)
│
├── app/                                # ★ APPLICATION CODE
│   ├── Domain/                         # Domain layer (no external dependencies)
│   │   ├── Entities/                   # Entities: User, Event, Article, Gallery, Photo, SlugRedirect
│   │   ├── ValueObjects/               # VOs: EntityId, UserId, EventId, Slug, Price, HexColor, etc.
│   │   ├── Enums/                      # Enums: PublicationStatus, UserRole
│   │   ├── Events/                     # Domain events: UserRegistered, UserLoggedIn, etc.
│   │   ├── Exceptions/                 # Domain exceptions
│   │   ├── Repositories/               # Repository interfaces
│   │   ├── Authorization/              # Entities: Permission, Role | VOs: RoleId, PermissionKey
│   │   ├── Calendar/                   # Enums: CalendarSourceColor
│   │   ├── Mail/                       # Entities: EmailLog | Enums: MailDriver, EmailStatus | Events
│   │   ├── Modules/                    # Entities: Module | VOs: ModuleName, ModuleVersion, ModuleRequirements, CompatibilityResult | CoreModuleMinimumVersions
│   │   ├── Navigation/                 # Entities: MenuItem | Enums: MenuLocation, MenuVisibility, LinkTarget
│   │   └── Updates/                    # VOs: GitHubReleaseInfo, ReleaseSelection, GitHubCommitComparison | Enums: UpdateStatus | Events
│   │
│   ├── Application/                    # Application layer (DTOs, service interfaces)
│   │   ├── DTOs/                       # Input DTOs: ContactMessageDTO, CreateUserDTO, etc.
│   │   │   └── Response/               # Response DTOs: EventResponseDTO, ArticleResponseDTO, etc.
│   │   ├── Services/                   # Interfaces: AuthServiceInterface, SettingsServiceInterface, etc.
│   │   ├── Factories/                  # ResponseDTOFactory interface
│   │   ├── Authorization/              # AuthorizationServiceInterface, RoleServiceInterface
│   │   ├── Calendar/                   # CalendarAggregatorServiceInterface, CalendarEntrySourceInterface, CalendarEntryDTO
│   │   ├── Content/                    # TrixHtmlConverterInterface, TrixContentMigratorInterface
│   │   ├── Mail/                       # MailConfigurationServiceInterface, etc.
│   │   ├── Modules/                    # ModuleManagerServiceInterface, EnabledModulesResolverInterface, ModuleCompatibilityServiceInterface, etc.
│   │   ├── Navigation/                 # MenuServiceInterface, RouteRegistryInterface
│   │   ├── Seo/                        # SeoMetadataFactoryInterface, SeoMetadata, SeoPageDTO
│   │   └── Updates/                    # CoreUpdateCheckerInterface, ModuleUpdaterInterface, ReleaseChannelPolicy
│   │
│   ├── Infrastructure/                 # Infrastructure layer (implementations)
│   │   ├── Persistence/Eloquent/       # Eloquent models, repositories
│   │   │   ├── Models/                 # UserModel, EventModel, ArticleModel, etc.
│   │   │   ├── Repositories/           # EloquentEventRepository, etc.
│   │   │   └── Concerns/               # HasSlug, DeletesCloudinaryImages
│   │   ├── Services/                   # Implementations: AuthService, SettingsService, etc. (+ Color/, Logging/)
│   │   ├── Auth/                       # UuidEloquentUserProvider (UUID auth)
│   │   ├── Authorization/              # AuthorizationService, CorePermissionDefinitions, EloquentRoleRepository
│   │   ├── Calendar/                   # CalendarAggregatorService, CalendarSourceRegistry, EventCalendarSource
│   │   ├── Content/                    # TrixHtmlConverter, TrixContentMigrator
│   │   ├── Mail/                       # MailConfigurationService, SesUsageTracker, SnsMessageValidator
│   │   ├── Modules/                    # ModuleManagerService, EnabledModulesResolver, ModuleCompatibilityService, ModuleInstaller
│   │   ├── Navigation/                 # MenuService, MenuItemHrefResolver, MenuItemModel and its repository
│   │   ├── Seo/                        # SeoMetadataFactory, StructuredDataBuilder
│   │   ├── Storage/Cloudinary/         # CloudinaryStorageAdapter (images disk), CloudinaryPathMapper
│   │   ├── Updates/                    # CoreUpdateChecker, ModuleUpdater, ModuleBackupService, update history models
│   │   │   └── Jobs/                   # UpdateModuleJob, CheckModuleUpdatesJob
│   │   ├── Factories/                  # ResponseDTOFactory (implementation)
│   │   └── Support/                    # QueryHelpers, SanitizesHtml, FrameworkCacheCommands
│   │
│   ├── Http/                           # HTTP presentation layer
│   │   ├── Controllers/                # HomeController, EventController, ArticleController, etc.
│   │   │   ├── Api/                    # SesWebhookController, CalendarController
│   │   │   └── Auth/                   # LoginController, RegisterController, ProfileController, etc.
│   │   ├── Middleware/                 # HandleInertiaRequests, SecurityHeaders, BlockBots, EnsureSiteIsNotInMaintenance, etc.
│   │   │   └── Concerns/               # IdentifiesLivewireRequests
│   │   ├── Requests/                   # FormRequests: SearchRequest, ContactFormRequest, etc.
│   │   │   ├── Api/                    # CalendarRequest
│   │   │   └── Auth/                   # LoginRequest, RegisterRequest, etc.
│   │   ├── Resources/                  # API Resources: EventResource, ArticleResource, CalendarEntryResource, etc.
│   │   └── Concerns/                   # BuildsPaginatedResponse trait
│   │
│   ├── Filament/                       # Admin panel
│   │   ├── Resources/                  # EventResource, ArticleResource, GalleryResource, MenuItemResource, etc.
│   │   │   └── */Pages/                # CRUD pages per resource (List, Create, Edit)
│   │   ├── Pages/                      # Dashboard, SiteSettings, ModulesPage, ModuleSettingsPage, ModuleUpdatesPage, CoreUpdatesPage
│   │   │   └── Settings/               # AboutPageSettings, DashboardSettings, LegalPagesSettings, MailSettings, MailStatisticsPage
│   │   ├── Widgets/                    # UpcomingEventsWidget, RecentArticlesWidget, etc.
│   │   ├── Forms/Components/           # TimePicker (custom Alpine.js component)
│   │   └── Concerns/                   # ChecksPermissions, ManagesPageSettings, HasExtendableFormSections, etc.
│   │
│   ├── Policies/                       # EventPolicy, ArticlePolicy, UserPolicy, etc. (8 policies)
│   ├── Notifications/                  # WelcomeNotification, VerifyPendingEmailNotification
│   ├── Mail/                           # ContactFormMail, TestMail
│   ├── Modules/                        # ModuleLoader, ModuleServiceProvider base
│   ├── Providers/                      # AppServiceProvider, AuthorizationServiceProvider, ModulesServiceProvider, Filament/AdminPanelProvider
│   ├── Console/                        # Artisan commands (schedule in routes/console.php)
│   └── View/
│       ├── Components/                # ModuleImportMap, SeoHead, SiteFavicons
│       └── Modules/                    # CompatibilityIssueFormatter
│
├── config/                             # 21 configuration files
│   ├── app.php                         # Application (name, locale: es, timezone)
│   ├── database.php                    # PostgreSQL (dev/prod), SQLite (testing)
│   ├── mail.php                        # Mailers; the driver is chosen in the admin panel (MailDriver)
│   ├── cloudinary.php                  # Cloudinary for images (also sent to the frontend as the cloudinary prop)
│   ├── elasticsearch.php               # ES cluster for logging
│   ├── inertia.php                     # Inertia (SSR disabled)
│   ├── modules.php                     # Module system
│   ├── module-build.php                # Module asset build
│   ├── bot-protection.php              # Bot protection
│   ├── images.php                      # Image optimization
│   ├── purifier.php                    # HTMLPurifier for XSS
│   └── updates.php                     # Update system (GitHub, core branch and commit, behavior)
│
├── database/
│   ├── migrations/                     # 31 core migrations
│   ├── factories/                      # Factories for testing
│   └── seeders/                        # Seeders (initial data, roles, permissions)
│
├── lang/                               # PHP translations
│   ├── es/                             # Spanish (primary language)
│   └── en/                             # English
│
├── modules/                            # ★ EXTENSIBLE MODULES (not tracked: src/modules/* is gitignored)
│   └── <module>/                       # Each one cloned from its own repository or installed from a release ZIP
│
├── resources/
│   ├── css/                            # Tailwind CSS (app.css) + filament/admin/theme.css (panel theme)
│   ├── js/                             # ★ VUE 3 FRONTEND
│   │   ├── app.ts                      # ★ Entry point (reads the initial page, then Inertia + Vue + Pinia + i18n + unhead)
│   │   ├── pages/                      # 18 Inertia pages
│   │   │   ├── Home.vue                # Landing with hero, events, articles, gallery
│   │   │   ├── About.vue               # Guild information
│   │   │   ├── Maintenance.vue         # 503 page in maintenance mode
│   │   │   ├── Auth/                   # Login, Register, ForgotPassword, ResetPassword, VerifyEmail
│   │   │   ├── Events/                 # Index, Show
│   │   │   ├── Articles/               # Index, Show
│   │   │   ├── Gallery/                # Index, Show
│   │   │   ├── Calendar/               # Index (interactive calendar)
│   │   │   ├── Profile/                # Show (with extensible tabs)
│   │   │   ├── Search/                 # Index (global search)
│   │   │   └── Legal/                  # Show (legal pages)
│   │   ├── components/                 # 49 Vue components
│   │   │   ├── layout/                 # TheHeader, TheFooter, TheNavigation, UserDropdown, ModuleSlot, etc.
│   │   │   ├── ui/                     # BaseButton, BaseCard, LoadingSpinner, TagBadge, etc.
│   │   │   ├── events/                 # EventCard, EventList, EventsSection
│   │   │   ├── articles/               # ArticleCard, ArticleList
│   │   │   ├── gallery/                # GalleryCard, GalleryGrid, PhotoLightbox
│   │   │   ├── calendar/               # CalendarWidget, EventCalendar, EventDetailPanel, EventTooltip
│   │   │   ├── hero/                   # HeroSlider
│   │   │   ├── profile/                # ProfileHeader, ProfileSidebar, ProfileTabBar, etc.
│   │   │   ├── form/                   # FormToggle, FormCheckbox, FormSelect, FormCombobox, etc.
│   │   │   ├── contact/                # ContactForm
│   │   │   ├── map/                    # LocationMap
│   │   │   └── search/                 # SearchInput
│   │   ├── composables/                # 19 composables
│   │   │   ├── useAuth.ts              # Authentication and permissions
│   │   │   ├── useEvents.ts            # Event formatting
│   │   │   ├── useArticles.ts          # Article utilities
│   │   │   ├── useGallery.ts           # Gallery utilities
│   │   │   ├── useRoutes.ts            # Centralized route definitions
│   │   │   ├── usePagination.ts        # Pagination
│   │   │   ├── useSeo.ts               # Applies the server's seo prop (head tags)
│   │   │   ├── useNotifications.ts     # Toast notification system
│   │   │   ├── useModuleSlots.ts       # Dynamic loading of module components
│   │   │   ├── useProfileTabs.ts       # Extensible profile tabs
│   │   │   └── ...                     # useLightbox, useHeroSlider, useFavicons, etc.
│   │   ├── stores/                     # useAppStore (Pinia) — theme, locale, sidebar
│   │   ├── types/                      # TypeScript interfaces (models, navigation, profile, seo, slots, etc.)
│   │   ├── layouts/                    # DefaultLayout, AuthLayout
│   │   ├── utils/                      # cloudinary.ts, csrf.ts, resolveModulePage.ts, moduleTranslations.ts, etc.
│   │   ├── locales/                    # Translations (es.ts, en.ts)
│   │   ├── contracts/                  # Import map entries shared with modules (@/contracts/*): cookieConsent
│   │   └── vendor-exports/             # Import map entries: vue, pinia, vue-i18n, @inertiajs/vue3, @unhead/vue
│   └── views/                          # Blade: app.blade.php, components/ (module-import-map, seo-head, site-favicons), emails/, filament/, sitemap
│
├── routes/
│   ├── web.php                         # ★ Public routes + authentication (+ /eventos/calendario JSON)
│   ├── api.php                         # API routes (SES webhooks)
│   └── console.php                     # Schedule: CheckModuleUpdatesJob daily at 04:00
│
├── tests/
│   ├── Unit/                           # Domain, Application, Infrastructure, Architecture, etc.
│   ├── Integration/                    # Infrastructure, database and config tests
│   ├── Feature/                        # Feature tests (HTTP, Filament, modules, navigation, console)
│   ├── Support/                        # Test helpers
│   └── Fixtures/modules/               # Fixture modules for module system tests
│
├── public/
│   └── index.php                       # ★ Web entry point
│
├── storage/                            # Logs, cache, temporary files
├── stubs/modules/                      # Module scaffolding stubs (module:make*)
└── vendor/                             # Composer dependencies
```

Module tests are not part of the host suite: each module runs its own `phpunit.xml`.

---

## Module structure (example: game-tables 2.3.0)

Each module lives in its own repository; this tree reflects `game-tables` at 2.3.0 and changes with the module.

```
modules/game-tables/
├── src/
│   ├── GameTablesServiceProvider.php    # ★ Module service provider
│   ├── Domain/
│   │   ├── Entities/                   # GameTable, Campaign, Participant, GameSystem, ContentWarning, etc.
│   │   ├── Enums/                      # 20 enums (TableType, TableFormat, Genre, CampaignStatus, etc.)
│   │   ├── ValueObjects/               # GameTableId, GameSystemId, TimeSlot, etc.
│   │   ├── Events/                     # Module domain events
│   │   ├── Exceptions/                 # GameTableNotFoundException, etc.
│   │   └── Repositories/               # Repository interfaces
│   ├── Application/
│   │   ├── DTOs/                       # Input and response DTOs
│   │   └── Services/                   # Service interfaces
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/       # Models and repositories
│   │   └── Services/                   # Service implementations
│   ├── Http/
│   │   ├── Controllers/                # GameTableController, CampaignController, etc.
│   │   └── Requests/                   # FrontendCreateGameTableRequest, etc.
│   ├── Filament/
│   │   ├── Resources/                  # GameTableResource, CampaignResource, etc.
│   │   ├── Pages/                      # GameTablesSettings
│   │   └── Widgets/                    # PendingModerationWidget
│   ├── Policies/                       # GameTablePolicy, CampaignPolicy, etc.
│   ├── Listeners/                      # Event listeners
│   └── Notifications/                  # Module notifications
├── resources/
│   ├── js/                             # Module Vue frontend: pages, components, locales, types
│   └── views/filament/                 # Blade views for the panel
├── config/                             # module.php, settings.php (release defaults; panel values live in the settings table)
├── database/
│   ├── migrations/                     # 11 migrations
│   ├── factories/                      # Testing factories
│   └── seeders/                        # Module seeders
├── routes/
│   └── web.php                         # Module routes
├── tests/                              # Module tests (Unit, Integration, Feature)
├── lang/                               # Module translations
├── module.json                         # Manifest (name, version, requires.core/php/laravel/filament, dependencies, repository)
├── phpunit.xml                         # Module test suite
├── package.json                        # Module Node dependencies
├── vite.config.ts                      # Module Vite configuration (vue, pinia, vue-i18n, Inertia, unhead external)
└── README.md                           # Module documentation
```

---

## Entry points

| Entry point | File | Purpose |
|-------------|------|---------|
| **Web** | `src/public/index.php` | HTTP requests via Nginx → PHP-FPM |
| **CLI** | `src/artisan` | Artisan commands (migrations, modules, etc.) |
| **Frontend** | `src/resources/js/app.ts` | Reads the initial page, then boots Vue 3 + Inertia + Pinia + i18n + unhead |
| **Queue** | `queue:work` (supervisord in production, `queue` service in development) | Background jobs (module asset builds, module updates) |
| **Scheduler** | `schedule:work` (supervisord in production) | Scheduled jobs (daily module update check) |

---

## Critical configuration files

| File | Purpose |
|------|---------|
| `src/config/app.php` | Name, locale (es), timezone |
| `src/config/database.php` | PostgreSQL + SQLite testing |
| `src/config/modules.php` | Module paths and discovery |
| `src/config/cloudinary.php` | Image CDN |
| `src/config/elasticsearch.php` | Centralized logging |
| `src/config/mail.php` | Mailers (driver chosen in the admin panel: smtp, mail, ses, resend, log, array) |
| `src/config/images.php` | Image optimization |
| `src/config/purifier.php` | HTML sanitization |
| `src/config/bot-protection.php` | Bot protection |
| `src/config/updates.php` | Update system |
| `src/phpstan.neon` | Static analysis level 8 |
| `src/phpunit.xml` | 3 testing suites |
| `src/vite.config.ts` | Frontend build, import map entries and shared vendor chunks |
| `Makefile` | 69 targets (`make help`) |
| `docker-compose.yml` | 9 development services |
