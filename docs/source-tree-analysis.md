# Source tree analysis

> Generated: 2026-03-21 | Project: GuildForge | Type: Full-stack web monolith

## General structure

```
runesword/                              # Repository root
├── .github/
│   └── workflows/                      # CI/CD pipelines (GitHub Actions)
├── docker/                             # Container configuration
│   ├── nginx/                          # Nginx configuration
│   ├── php/                            # PHP-FPM config and Dockerfile
│   └── supervisord/                    # Supervisord for production
├── docs/                               # Generated documentation (this directory)
├── src/                                # ★ MAIN APPLICATION
├── Makefile                            # 40+ automation commands
├── docker-compose.yml                  # Development stack (9 services)
├── docker-compose.prod.yml             # Production stack
├── Dockerfile.prod                     # Production Docker image
├── README.md                           # Quick start guide
└── VERSION                             # Current version (1.0.0)
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
├── vite.config.ts                      # Vite configuration + modules
├── eslint.config.js                    # ESLint for TypeScript/Vue
├── phpstan.neon                        # PHPStan/Larastan level 7
├── rector.php                          # Rector for automated refactoring
│
├── app/                                # ★ APPLICATION CODE
│   ├── Domain/                         # Domain layer (no external dependencies)
│   │   ├── Entities/                   # Entities: User, Event, Article, Gallery, Photo, SlugRedirect
│   │   ├── ValueObjects/               # VOs: EntityId, UserId, EventId, Slug, Price, HexColor, etc.
│   │   ├── Enums/                      # Enums: PublicationStatus, UserRole
│   │   ├── Events/                     # Domain events: UserRegistered, UserLoggedIn, etc.
│   │   ├── Exceptions/                 # Domain exceptions
│   │   ├── Repositories/              # Repository interfaces
│   │   ├── Authorization/              # Entities: Permission, Role | VOs: RoleId, PermissionKey
│   │   ├── Mail/                       # Entities: EmailLog | Enums: MailDriver, EmailStatus
│   │   ├── Modules/                    # Entities: Module | VOs: ModuleName, ModuleVersion
│   │   ├── Navigation/                 # Entities: MenuItem | Enums: MenuLocation, MenuVisibility
│   │   └── Updates/                    # VOs: GitHubReleaseInfo | Enums: UpdateStatus
│   │
│   ├── Application/                    # Application layer (DTOs, service interfaces)
│   │   ├── DTOs/                       # Input DTOs: ContactMessageDTO, CreateUserDTO, etc.
│   │   │   └── Response/              # Response DTOs: EventResponseDTO, ArticleResponseDTO, etc.
│   │   ├── Services/                   # Interfaces: AuthServiceInterface, SettingsServiceInterface, etc.
│   │   ├── Factories/                  # ResponseDTOFactory interface
│   │   ├── Authorization/              # AuthorizationServiceInterface, RoleServiceInterface
│   │   ├── Mail/                       # MailConfigurationServiceInterface, etc.
│   │   ├── Modules/                    # ModuleManagerServiceInterface, etc.
│   │   ├── Navigation/                 # MenuServiceInterface, RouteRegistryInterface
│   │   └── Updates/                    # CoreUpdateCheckerInterface, ModuleUpdaterInterface
│   │
│   ├── Infrastructure/                 # Infrastructure layer (implementations)
│   │   ├── Persistence/Eloquent/       # Eloquent models, repositories
│   │   │   ├── Models/                 # UserModel, EventModel, ArticleModel, etc.
│   │   │   └── Repositories/           # EloquentEventRepository, etc.
│   │   ├── Services/                   # Implementations: AuthService, SettingsService, etc.
│   │   ├── Auth/                       # UuidEloquentUserProvider (UUID auth)
│   │   ├── Authorization/              # AuthorizationService, EloquentRoleRepository
│   │   ├── Mail/                       # MailConfigurationService, SesUsageTracker
│   │   ├── Modules/                    # ModuleManagerService, ModuleDiscoveryService
│   │   ├── Navigation/                 # MenuService, MenuItemHrefResolver
│   │   ├── Updates/                    # CoreUpdateChecker, ModuleUpdater, ModuleBackupService
│   │   ├── Factories/                  # ResponseDTOFactory (implementation)
│   │   └── Support/                    # QueryHelpers, shared traits
│   │
│   ├── Http/                           # HTTP presentation layer
│   │   ├── Controllers/                # HomeController, EventController, ArticleController, etc.
│   │   │   ├── Api/                    # SesWebhookController, CalendarController
│   │   │   └── Auth/                   # LoginController, RegisterController, ProfileController
│   │   ├── Middleware/                 # SecurityHeaders, BlockBots, HandleInertia, etc.
│   │   ├── Requests/                   # FormRequests: SearchRequest, ContactFormRequest, etc.
│   │   │   ├── Api/                    # CalendarRequest
│   │   │   └── Auth/                   # LoginRequest, RegisterRequest, etc.
│   │   ├── Resources/                  # API Resources: EventResource, ArticleResource, etc.
│   │   └── Concerns/                   # BuildsPaginatedResponse trait
│   │
│   ├── Filament/                       # Admin panel
│   │   ├── Resources/                  # EventResource, ArticleResource, GalleryResource, etc.
│   │   │   └── */Pages/               # CRUD pages per resource (List, Create, Edit)
│   │   ├── Pages/                      # Dashboard, SiteSettings, MailSettings, ModulesPage, etc.
│   │   ├── Widgets/                    # UpcomingEventsWidget, RecentArticlesWidget, etc.
│   │   ├── Forms/Components/           # TimePicker (custom Alpine.js component)
│   │   └── Concerns/                   # HasExtendableFormSections, ManagesPageSettings
│   │
│   ├── Policies/                       # EventPolicy, ArticlePolicy, UserPolicy, etc. (8 policies)
│   ├── Notifications/                  # WelcomeNotification, VerifyPendingEmailNotification
│   ├── Mail/                           # ContactFormMail, TestMail
│   ├── Modules/                        # ModuleLoader, ModuleServiceProvider base
│   ├── Providers/                      # AppServiceProvider, AuthorizationServiceProvider, etc.
│   ├── Console/                        # Artisan commands and Kernel
│   └── View/                           # View Components
│
├── config/                             # 20 Laravel configuration files
│   ├── app.php                         # Application (name, locale: es, timezone)
│   ├── database.php                    # PostgreSQL (dev/prod), SQLite (testing)
│   ├── mail.php                        # Drivers: smtp, ses, resend, postmark, log
│   ├── cloudinary.php                  # Cloudinary for images
│   ├── elasticsearch.php               # ES cluster for logging
│   ├── modules.php                     # Module system
│   ├── bot-protection.php              # Bot protection
│   ├── images.php                      # Image optimization
│   ├── purifier.php                    # HTMLPurifier for XSS
│   └── updates.php                     # Update system
│
├── database/
│   ├── migrations/                     # 28 core migrations
│   ├── factories/                      # Factories for testing
│   └── seeders/                        # Seeders (initial data, roles, permissions)
│
├── lang/                               # Translations
│   ├── es/                             # Spanish (primary language)
│   └── en/                             # English (fallback)
│
├── modules/                            # ★ EXTENSIBLE MODULES (9 modules)
│   ├── announcements/                  # Guild announcements
│   ├── channel-notifications/          # Channel notifications
│   ├── cookie-consent/                 # Cookie consent (GDPR)
│   ├── event-registrations/            # Event registrations
│   ├── game-tables/                    # ★ RPG tables and campaigns (most complex)
│   ├── memberships/                    # Membership and dues system
│   ├── security-test/                  # Security testing
│   ├── tournaments/                    # Tournaments with Swiss pairing
│   └── venue-bookings/                 # Venue bookings
│
├── resources/
│   ├── css/                            # Tailwind CSS (app.css)
│   ├── js/                             # ★ VUE 3 FRONTEND
│   │   ├── app.ts                      # ★ Entry point (Inertia + Vue + Pinia + i18n)
│   │   ├── pages/                      # 17 Inertia pages
│   │   │   ├── Home.vue                # Landing with hero, events, articles, gallery
│   │   │   ├── About.vue               # Guild information
│   │   │   ├── Auth/                   # Login, Register, ForgotPassword, ResetPassword, VerifyEmail
│   │   │   ├── Events/                 # Index, Show
│   │   │   ├── Articles/               # Index, Show
│   │   │   ├── Gallery/                # Index, Show
│   │   │   ├── Calendar/               # Index (interactive calendar)
│   │   │   ├── Profile/                # Show (with extensible tabs)
│   │   │   ├── Search/                 # Index (global search)
│   │   │   └── Legal/                  # Show (legal pages)
│   │   ├── components/                 # 50 Vue components
│   │   │   ├── layout/                 # TheHeader, TheFooter, TheNavigation, UserDropdown, etc.
│   │   │   ├── ui/                     # BaseButton, BaseCard, LoadingSpinner, TagBadge, etc.
│   │   │   ├── events/                 # EventCard, EventList, EventsSection, EventCalendar
│   │   │   ├── articles/               # ArticleCard, ArticleList
│   │   │   ├── gallery/                # GalleryCard, GalleryGrid, PhotoLightbox
│   │   │   ├── calendar/               # CalendarWidget, EventDetailPanel, EventTooltip
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
│   │   │   ├── useSeo.ts              # Meta tags and OG tags
│   │   │   ├── useNotifications.ts     # Toast notification system
│   │   │   ├── useModuleSlots.ts       # Dynamic loading of module components
│   │   │   ├── useProfileTabs.ts       # Extensible profile tabs
│   │   │   └── ...                     # useLightbox, useHeroSlider, useFavicons, etc.
│   │   ├── stores/                     # useAppStore (Pinia) — theme, locale, sidebar
│   │   ├── types/                      # TypeScript interfaces (models, navigation, profile, etc.)
│   │   ├── layouts/                    # DefaultLayout, AuthLayout
│   │   ├── utils/                      # cloudinary.ts, html.ts, moduleTranslations.ts, etc.
│   │   ├── locales/                    # Translation YAML files (es, en)
│   │   └── vendor-exports/             # Re-exports of Vue, Inertia, Pinia, vue-i18n
│   └── views/                          # Blade templates (app.blade.php, admin)
│
├── routes/
│   ├── web.php                         # ★ Public routes + authentication
│   ├── api.php                         # API routes (SES webhooks)
│   └── console.php                     # Artisan commands
│
├── tests/
│   ├── Unit/                           # Domain and Application tests
│   │   ├── Domain/Entities/            # Entity tests
│   │   ├── Domain/ValueObjects/        # Value Object tests
│   │   └── Application/DTOs/           # DTO tests
│   ├── Integration/                    # Infrastructure tests
│   │   └── Infrastructure/Persistence/ # Eloquent repository tests
│   └── Feature/                        # HTTP Controller tests
│       └── Http/Controllers/           # End-to-end tests
│
├── public/
│   └── index.php                       # ★ Web entry point
│
├── storage/                            # Logs, cache, temporary files
├── stubs/                              # Code generation stubs
└── vendor/                             # Composer dependencies
```

---

## Module structure (example: game-tables)

```
modules/game-tables/
├── src/
│   ├── GameTablesServiceProvider.php    # ★ Module service provider
│   ├── Domain/
│   │   ├── Entities/                   # GameTable, Campaign, Participant, GameMaster
│   │   ├── Enums/                      # 18 enums (TableType, TableFormat, Genre, etc.)
│   │   ├── ValueObjects/               # GameTableId, GameSystemId, TimeSlot
│   │   ├── Events/                     # Module domain events
│   │   └── Exceptions/                 # GameTableNotFoundException, etc.
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
│   │   ├── Pages/                      # GameTablesSettingsPage
│   │   └── Widgets/                    # PendingModerationWidget
│   ├── Policies/                       # GameTablePolicy
│   ├── Listeners/                      # Event listeners
│   └── Notifications/                  # Module notifications
├── resources/js/                       # Module Vue frontend
│   ├── pages/                          # Module Inertia pages
│   ├── components/                     # Module Vue components
│   └── types/                          # Module TypeScript types
├── database/
│   ├── migrations/                     # 11 migrations
│   ├── factories/                      # Testing factories
│   └── seeders/                        # Module seeders
├── routes/
│   └── web.php                         # Module routes
├── tests/                              # Module tests
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
├── lang/                               # Module translations
├── module.json                         # Manifest (name, version, permissions, dependencies)
├── composer.json                       # Module PHP dependencies
├── package.json                        # Module Node dependencies
├── vite.config.ts                      # Module Vite configuration
└── README.md                           # Module documentation
```

---

## Entry points

| Entry point | File | Purpose |
|-------------|------|---------|
| **Web** | `src/public/index.php` | HTTP requests via Nginx → PHP-FPM |
| **CLI** | `src/artisan` | Artisan commands (migrations, modules, etc.) |
| **Frontend** | `src/resources/js/app.ts` | Vue 3 + Inertia + Pinia + i18n bootstrap |
| **Queue** | Worker via Supervisord | Background job processing |

---

## Critical configuration files

| File | Purpose |
|------|---------|
| `src/config/app.php` | Name, locale (es), timezone |
| `src/config/database.php` | PostgreSQL + SQLite testing |
| `src/config/modules.php` | Module paths and discovery |
| `src/config/cloudinary.php` | Image CDN |
| `src/config/elasticsearch.php` | Centralized logging |
| `src/config/mail.php` | Email drivers (resend, ses, smtp) |
| `src/config/images.php` | Image optimization |
| `src/config/purifier.php` | HTML sanitization |
| `src/config/bot-protection.php` | Bot protection |
| `src/config/updates.php` | Update system |
| `src/phpstan.neon` | Static analysis level 7 |
| `src/phpunit.xml` | 3 testing suites |
| `Makefile` | 40+ automation commands |
| `docker-compose.yml` | 9 development services |
