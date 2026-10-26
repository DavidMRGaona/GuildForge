# System architecture

> Updated: 2026-10-13 | Project: GuildForge core 3.5.1 (`src/VERSION`)

---

## 1. Executive summary

GuildForge is a web platform for wargames and role-playing games guilds. It combines a public frontend (events, articles, galleries, calendar) with an admin panel (Filament 5) for content management by guild members.

The architecture follows Clean Architecture principles with four concentric layers (Domain, Application, Infrastructure, Presentation), an extensible module system, and strict separation between reads (Query Services) and writes (Repositories). The main technology stack is Laravel 13, Vue 3 with TypeScript, Inertia.js 3 as the bridge between Laravel and Vue (client-side rendering: SSR is disabled in `config/inertia.php`, and the server renders only the SEO head tags), PostgreSQL as the database, and Filament 5 (Livewire 4) for the admin panel.

---

## 2. Architectural principles

### Clean Architecture

```
Domain (inner layer) -> Application -> Infrastructure -> Presentation (outer layer)
```

Inner layers never import from outer layers. Dependencies always point inward through interfaces defined in the inner layers and implementations in the outer layers (dependency inversion).

### Applied SOLID principles

| Principle | Application in GuildForge |
|-----------|--------------------------|
| **S** - Single responsibility | Each class has one responsibility: `EventQueryService` only reads, `EloquentEventRepository` only persists |
| **O** - Open/closed | The module system allows extending functionality without modifying the core |
| **L** - Liskov substitution | Domain interfaces (`EventRepositoryInterface`) are implemented with Eloquent without breaking contracts |
| **I** - Interface segregation | Separate interfaces for reading (`EventQueryServiceInterface`) and writing (`EventRepositoryInterface`) |
| **D** - Dependency inversion | All dependencies are injected via interfaces; the service container resolves implementations |

### Module system for extensibility

Modules are independent packages in `src/modules/` that can add routes, permissions, navigation, Vue components (slots), pages and Filament widgets without modifying core code.

### TDD (test-driven development)

The development flow follows the red-green-refactor cycle:
1. Write a failing test (red)
2. Implement the minimum code to make it pass (green)
3. Refactor while keeping tests green

Tests are organized in `tests/Unit/`, `tests/Integration/` and `tests/Feature/`.

---

## 3. Architecture layers

### 3.1. Domain

**Location**: `src/app/Domain/`

The innermost layer contains pure business rules with no framework dependencies. It includes:

#### Entities

Objects with identity that encapsulate business logic:

- `Event` - Guild event (date validation, publication, pricing)
- `Article` - Article or news item
- `Gallery`, `Photo` - Galleries and photos
- `User` - System user
- `SlugRedirect` - Old slug redirection
- `Module` - System module (`Domain/Modules/`)
- `MenuItem` - Navigation menu item (`Domain/Navigation/`)
- `EmailLog` - Sent email log entry (`Domain/Mail/`)
- `Role`, `Permission` - Roles and permissions (`Domain/Authorization/`)

Entity example with business logic (`Event`):

```php
final class Event
{
    public function __construct(
        private readonly EventId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly string $description,
        private readonly DateTimeImmutable $startDate,
        private readonly DateTimeImmutable $endDate,
        // ...
    ) {
        $this->validateDates(); // Invariant: endDate >= startDate
    }

    public function publish(): void
    {
        if ($this->isPast()) {
            throw CannotPublishPastEventException::create();
        }
        $this->isPublished = true;
    }
}
```

#### Value objects

Immutable objects without identity that represent domain concepts:

| Value object | Purpose |
|-------------|---------|
| `EntityId`, `EventId`, `ArticleId`, `GalleryId`, `PhotoId`, `UserId` | Typed identifiers (UUID) |
| `Slug` | Validated URL slug with pattern `[a-z0-9]+(-[a-z0-9]+)*` |
| `Price` | Price with validation |
| `HexColor`, `ColorPalette` | Visual theme colors |
| `DownloadLink` | Download link associated with events |
| `CoreTableRegistry` | Core table registry (module protection) |
| `RoleId`, `RoleName`, `PermissionId`, `PermissionKey` | Authorization identifiers (`Domain/Authorization/`) |
| `ModuleId`, `ModuleName`, `ModuleVersion`, `ModuleRequirements` | Module identity, semantic version and `requires` constraints (`Domain/Modules/`) |
| `HostEnvironment`, `CompatibilityResult`, `CompatibilityIssue` | Versions of the running host, and the result of checking a module against them |
| `GitHubReleaseInfo`, `ReleaseSelection` | A module release on GitHub, and the release picked for an update (`Domain/Updates/`) |
| `GitHubCommitComparison` | Deployed core commit compared with the deploy branch |
| `EmailQuota`, `SmtpPort` | Mail quota limits and SMTP port (`Domain/Mail/`) |

#### Enums

- `PublicationStatus` - Content publication status
- `UserRole` - User roles (simple type with `label()`)
- `MenuLocation`, `MenuVisibility`, `LinkTarget` - Menu location, visibility and link target
- `ModuleStatus`, `RequirementType` - Module state and the kind of a `requires` constraint
- `UpdateStatus` - Steps of a module update (downloading, verifying, applying, migrating...)
- `MailDriver`, `EmailStatus`, `SmtpEncryption` - Mail configuration and delivery status
- `CalendarSourceColor` - Color of a calendar source (`Domain/Calendar/`)

#### Domain events

Events that notify significant state changes:

- **User**: `UserRegistered`, `UserLoggedIn`, `UserLoggedOut`, `UserPasswordChanged`, `UserProfileUpdated`
- **Modules**: `ModuleEnabled`, `ModuleDisabled`, `ModuleInstalled`, `ModuleUninstalled`, `ModuleUpdated`, `ModuleDiscovered`, `ModuleMigrated`
- **Updates**: `ModuleUpdateStarted`, `ModuleUpdateCompleted`, `ModuleUpdateFailed`, `UpdatesAvailable`
- **Mail**: `MailConfigurationUpdated`, `QuotaLimitReached`, `QuotaThresholdReached`, `SesBounceReceived`, `SesComplaintReceived`

#### Domain exceptions

Specific exceptions that express business rule violations:

- `InvalidEventDatesException`, `CannotPublishPastEventException`
- `InvalidSlugException`, `InvalidPriceException`, `InvalidHexColorException`
- `ModuleNotFoundException`, `ModuleDependencyException`, `ModuleCircularDependencyException`, `ModuleIncompatibleException`
- `InvalidRoleNameException`, `RoleProtectedException`, `PermissionNotFoundException`
- `QuotaExceededException`, `InvalidMailConfigurationException`
- `UpdateException`

#### Repository interfaces

Contracts for persistence, defined in the domain and implemented in infrastructure:

```php
interface EventRepositoryInterface
{
    public function findById(EventId $id): ?Event;
    public function findBySlug(string $slug): ?Event;
    public function findUpcoming(int $limit = 10): Collection;
    public function findPublished(): Collection;
    public function findByDateRange(DateTimeImmutable $start, DateTimeImmutable $end): Collection;
    public function save(Event $event): void;
    public function delete(Event $event): void;
}
```

Core repositories: `ArticleRepositoryInterface`, `EventRepositoryInterface`, `GalleryRepositoryInterface`, `PhotoRepositoryInterface`, `UserRepositoryInterface`, `SlugRedirectRepositoryInterface`, `MenuItemRepositoryInterface`, `ModuleRepositoryInterface`, `EmailLogRepositoryInterface`, `RoleRepositoryInterface`, `PermissionRepositoryInterface`.

### 3.2. Application

**Location**: `src/app/Application/`

Use case layer that orchestrates domain logic. It does not contain business logic, but coordinates entities, repositories and services.

#### Input DTOs

Data transfer objects for creation and update:

- `CreateEventDTO`, `CreateArticleDTO`, `CreateGalleryDTO`, `CreatePhotoDTO`, `CreateUserDTO`
- `UpdateUserDTO`, `AnonymizeUserDTO`
- `ContactMessageDTO`, `ImageOptimizationSettingsDTO`, `ThemeSettingsDTO`

#### Response DTOs

Immutable objects for returning data to the frontend (`Application/DTOs/Response/`):

- `EventResponseDTO`, `ArticleResponseDTO`, `GalleryResponseDTO`, `GalleryDetailResponseDTO`
- `PhotoResponseDTO`, `HeroSlideResponseDTO`, `AuthorResponseDTO`
- `UserResponseDTO`, `LegalPageResponseDTO`, `AboutPageResponseDTO`
- `TagResponseDTO`, `TagHierarchyDTO`, `SocialLinksDTO`
- `PaginatedResponseDTO`, `SitemapEntryDTO`, `ActivityDTO`
- `LocationSettingsDTO`, `JoinStepDTO`

#### Service interfaces

Contracts that define application layer operations:

**Query Services** (read-only, singletons):
- `EventQueryServiceInterface` - Published event queries, pagination, search
- `ArticleQueryServiceInterface` - Article queries
- `GalleryQueryServiceInterface` - Gallery queries
- `HeroSlideQueryServiceInterface` - Hero carousel slides
- `TagQueryServiceInterface` - Tag queries
- `SitemapQueryServiceInterface` - Sitemap generation
- `AboutPageServiceInterface` - "About us" page data

**Write/action services**:
- `AuthServiceInterface` - Authentication (login, registration, logout)
- `EmailVerificationPolicyInterface` - Whether an account must verify its email before using the authenticated part of the site
- `UserServiceInterface` - User management
- `ContactServiceInterface` - Contact form
- `LegalPageServiceInterface` - Legal pages
- `SettingsServiceInterface` - Site configuration
- `ThemeSettingsServiceInterface` - Visual theme
- `DashboardWidgetConfigServiceInterface` - Dashboard widget configuration
- `ImageOptimizationServiceInterface` - Image optimization
- `SlugRedirectServiceInterface` - Slug redirects
- `SiteBrandingServiceInterface` - The site's own logo, favicons and social profiles (empty when the GuildForge defaults are used)
- `UserModelQueryServiceInterface` - Eloquent `UserModel` lookups for framework features (notifications, auth)
- `ColorPaletteGeneratorInterface` - Theme palette generation from a base color (OKLCH)
- `LogContextProviderInterface` - Request context added to log entries

**Module services** (`Application/Modules/Services/`):
- `ModuleManagerServiceInterface` - Module lifecycle management
- `ModuleContextServiceInterface` - Current module context
- `ModulePermissionRegistryInterface` - Module permission registry
- `ModuleNavigationRegistryInterface` - Module navigation registry
- `ModuleSlotRegistryInterface` - Module Vue slot registry
- `ModulePageRegistryInterface` - Module page registry
- `ModuleRouteRegistryInterface` - Module route registry
- `ModuleScaffoldingServiceInterface` - Module code generation
- `ModuleMigrationAnalyzerInterface` - Module migration analysis
- `EnabledModulesResolverInterface` - Which modules this process loads (enabled and compatible), and which enabled ones were rejected
- `ModuleCompatibilityServiceInterface` - Checks a module's `module.json` against the host (with `ModuleCompatibilityChecker` and `HostEnvironmentProviderInterface`)
- `ModuleInstallerInterface` - Installs or updates a module from a ZIP uploaded in the panel

**Navigation services** (`Application/Navigation/Services/`):
- `MenuServiceInterface` - Menu service
- `MenuItemHrefResolverInterface` - Menu URL resolution
- `RouteRegistryInterface` - Public route registry

**Mail services** (`Application/Mail/Services/`):
- `MailConfigurationServiceInterface` - Mail configuration
- `MailTestServiceInterface` - Test email sending
- `EmailQuotaServiceInterface` - Send quota control
- `MailStatisticsServiceInterface` - Mail statistics

**Update services** (`Application/Updates/Services/`):
- `CoreUpdateCheckerInterface`, `CoreVersionServiceInterface`
- `ModuleUpdateCheckerInterface`, `ModuleUpdaterInterface`
- `ModuleBackupServiceInterface`, `ModuleHealthCheckerInterface`
- `GitHubReleaseFetcherInterface`
- `ModulePackageInstallerInterface` - Stages a release next to the installed module, swaps it in and reverts it on failure
- `ModulePostUpdateRunnerInterface` - Runs migrations, seeders and the health check of an updated module in a separate process
- `ReleaseChannelPolicy` - Whether prereleases count as updates (`updates.behavior.allow_prereleases`, or the module is already on a prerelease)

**SEO** (`Application/Seo/`):
- `SeoMetadataFactoryInterface` - Builds the `seo` prop (`forSite`, `forHome`, `forPage`, `forEvent`, `forArticle`, `forGallery`)

**Calendar** (`Application/Calendar/`):
- `CalendarAggregatorServiceInterface` - Merges the entries of every calendar source
- `CalendarSourceRegistryInterface` - Registered sources (core events plus module sources)
- `CalendarEntrySourceInterface` - Contract a source implements to return `CalendarEntryDTO`s; `details` is merged into the JSON and the detail panel renders `description`, `location`, `imagePublicId`, `tags` and the translated `meta` rows (`icon`, `label`, `value`) generically

**Content** (`Application/Content/`):
- `TrixHtmlConverterInterface` - Converts rich text written with the old Trix editor to TipTap HTML
- `TrixContentMigratorInterface` - Converts stored core rich text once (`content:convert-trix`)

**Authorization services** (`Application/Authorization/Services/`):
- `AuthorizationServiceInterface` - Permission verification
- `PermissionRegistryInterface` - In-memory permission registry
- `RoleServiceInterface` - Role management
- `MemberRoleServiceInterface` - Gives `member`, the base role of every account, to new accounts and to existing ones (`users:assign-member-role`)
- `AssociationMembershipServiceInterface` - The association member role chosen in the site settings and whether a user holds it

#### Factory

- `ResponseDTOFactoryInterface` - Factory for converting Eloquent models to response DTOs

### 3.3. Infrastructure

**Location**: `src/app/Infrastructure/`

Concrete implementations that depend on frameworks, databases and external services.

#### Eloquent models

Located in `Infrastructure/Persistence/Eloquent/Models/`:

`UserModel`, `EventModel`, `ArticleModel`, `GalleryModel`, `PhotoModel`, `TagModel`, `HeroSlideModel`, `RoleModel`, `PermissionModel`, `SettingModel`, `SlugRedirectModel`, `ModuleModel`, `EmailLogModel`, `SesUsageRecordModel`

`MenuItemModel` lives in `Infrastructure/Navigation/Persistence/Eloquent/Models/`. The update history models (`CoreUpdateHistoryModel`, `CoreSeederHistoryModel`, `ModuleUpdateHistoryModel`, `ModuleUpdateLogModel`, `ModuleSeederHistoryModel`) live in `Infrastructure/Updates/Persistence/Eloquent/Models/`.

#### Eloquent repositories

Implementations of domain interfaces:

- `EloquentEventRepository` implements `EventRepositoryInterface`
- `EloquentArticleRepository` implements `ArticleRepositoryInterface`
- `EloquentGalleryRepository` implements `GalleryRepositoryInterface`
- `EloquentPhotoRepository` implements `PhotoRepositoryInterface`
- `EloquentUserRepository` implements `UserRepositoryInterface`
- `EloquentSlugRedirectRepository` implements `SlugRedirectRepositoryInterface`
- `EloquentModuleRepository` implements `ModuleRepositoryInterface`
- `EloquentEmailLogRepository` implements `EmailLogRepositoryInterface`
- `EloquentMenuItemRepository` implements `MenuItemRepositoryInterface`
- `EloquentRoleRepository` implements `RoleRepositoryInterface`
- `EloquentPermissionRepository` implements `PermissionRepositoryInterface`

#### Model traits (Concerns)

In `Infrastructure/Persistence/Eloquent/Concerns/`:

- **`HasSlug`** (used by `EventModel`) - Automatic slug generation with short UUID (`titulo-abc12345`). Supports mode with UUID (uniqueness guarantee) and without UUID (numeric suffix for collisions). Creates a redirect when the slug changes. Articles and galleries take their slug from the admin form (`Str::slug` of the title), with no UUID suffix and no redirect.
- **`DeletesCloudinaryImages`** (used by `UserModel`, `EventModel`, `ArticleModel`, `PhotoModel`, `HeroSlideModel`) - Automatic Cloudinary image deletion when a model is deleted or an image field changes. Configured with `$cloudinaryImageFields`.

#### External services

- **`Storage\Cloudinary\CloudinaryStorageAdapter`** - Flysystem adapter of the `images` disk, built on the official SDK (`cloudinary/cloudinary_php`, no Laravel package) with `CloudinaryPathMapper` (path to public id and resource type, prefix applied once). Optimizes images before upload, builds URLs without Admin API calls, ignores "not found" on deletion, and answers "exists" when the Admin API fails, so a rate-limited form never drops (and later deletes) a stored image
- **`SnsMessageValidator`** - Validation of Amazon SES SNS notifications
- **`HttpLogContextProvider`** - Log context for HTTP requests

#### Other implementations

- **`Seo/SeoMetadataFactory`** - Builds `SeoMetadata` (title, description, canonical URL, Open Graph, JSON-LD) for each page
- **`Calendar/CalendarAggregatorService`** - Merges `EventCalendarSource` (core events) with module sources for `/eventos/calendario`
- **`Content/TrixHtmlConverter`** - Trix to TipTap HTML conversion
- **`Modules/Services/*`** - Module manager, loader support, compatibility (`ModuleCompatibilityService`, `EnabledModulesResolver`, `HostEnvironmentProvider`), ZIP installer, registries
- **`Updates/Services/*`** and **`Updates/Jobs/*`** - Update checks, `ModuleUpdater`, `UpdateModuleJob`, `CheckModuleUpdatesJob` (see [Compatibility and updates](#compatibility-and-updates))

#### DTO factory

`EloquentResponseDTOFactory` converts Eloquent models to response DTOs. Uses the `SanitizesHtml` trait with HTMLPurifier to sanitize rich HTML content before sending it to the frontend.

### 3.4. Presentation

**Location**: `src/app/Http/`, `src/app/Filament/`, `src/resources/js/`

#### Controllers (Inertia)

Controllers that receive HTTP requests and return Inertia responses:

- `HomeController` - Home page
- `EventController` - Event listing and detail
- `ArticleController` - Article listing and detail
- `GalleryController` - Gallery listing and detail
- `CalendarPageController` - Calendar page
- `ContactController` - Contact form
- `LegalPageController` - Legal pages
- `SearchController` - Global search
- `SitemapController` - Sitemap generation
- `AboutController` - "About us" page

JSON controllers in `Http/Controllers/Api/`: `CalendarController` (`GET /eventos/calendario`, entries of every calendar source) and `SesWebhookController` (Amazon SES notifications).

Authentication controllers in `Http/Controllers/Auth/`:
- `LoginController`, `RegisterController`, `ProfileController`, `ForgotPasswordController`, `ResetPasswordController`, `EmailVerificationController`

Controller pattern: Query Service injection, data query, Inertia response. Every page also sends its own `seo` prop, built by `SeoMetadataFactoryInterface`:

```php
final class EventController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly TagQueryServiceInterface $tagQuery,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function index(TagFilterRequest $request): Response
    {
        $events = $this->eventQuery->getPublishedEventsPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->eventQuery->getPublishedEventsTotal($tagSlugs);

        return Inertia::render('Events/Index', [
            'events' => $this->buildPaginatedResponse(/* ... */),
            'tags' => TagResource::collection($availableTags)->resolve(),
            'currentTags' => $tagSlugs ?? [],
            'seo' => $this->seo->forPage(new SeoPageDTO(title: (string) __('seo.titles.events')))->toArray(),
        ]);
    }
}
```

#### Middleware

Registered in `bootstrap/app.php`:

| Middleware | Placement | Purpose |
|------------|-----------|---------|
| `BlockBotsMiddleware` | Global (every request, alias `bot-protection`) | Malicious bot blocking by User-Agent |
| `HandleInertiaRequests` | `web` group | Shared props (auth, theme, navigation, module slots/pages/translations, `cloudinary`, site `seo` defaults) |
| `SecurityHeadersMiddleware` | `web` group | Security headers (CSP, X-Frame-Options, HSTS on HTTPS, etc.) |
| `EnsureSiteIsNotInMaintenance` | `web` group | Maintenance mode (see below) |
| `EnsureEmailIsVerifiedWhenRequired` | `web` group, and alias `email.verified-if-required` | Required email verification on routes with `auth` or the alias (see [Required email verification](#required-email-verification)) |
| `EnsureLoginIsEnabled` | Alias `login.enabled`, on the login routes | Verification that login is enabled |
| `EnsureRegistrationIsEnabled` | Alias `registration.enabled`, on the registration routes | Verification that registration is enabled |

`/api/*` routes do not run the `web` group, so they get neither Inertia nor the security headers.

**Maintenance mode** is a site setting (`maintenance_enabled`, `maintenance_message`, edited in `SiteSettings`), not `artisan down`: the queue worker must keep running so queued module updates can be applied. While it is on, `EnsureSiteIsNotInMaintenance` answers with the Inertia page `Maintenance` (props `message`, `seo`), status **503** and `Retry-After: 600`. It lets through `admin/*`, `/up`, `/iniciar-sesion`, Livewire requests and users who pass `canAccessPanel()`. The admin panel shows a banner while it is on.

#### View components

Blade components in `app/View/Components/`, used by `resources/views/app.blade.php`:

- `SeoHead` (`<x-seo-head>`) - Server-renders the first page's SEO tags (marked `data-seo-server`) for crawlers
- `SiteFavicons` (`<x-site-favicons>`) - The site's favicons
- `ModuleImportMap` (`<x-module-import-map>`) - Import map for module bundles (see [Runtime loading](#runtime-loading))

---

## 4. Module system

### General architecture

Modules are self-contained packages that extend core functionality without modifying it. Each module is an independent Git repository located in `src/modules/`.

Generic module layout (each directory is optional; most modules have only some of them):

```
src/modules/
  <module>/
    src/                     # PHP code (ServiceProvider, Controllers, Models, etc.)
    database/
      migrations/            # Module-specific migrations
    routes/
      web.php                # Web routes (web middleware group)
      api.php                # API routes (prefix /api)
    resources/
      views/                 # Blade views (Filament)
      js/                    # Vue components, pages, locales
    lang/                    # PHP translations
    config/
      module.php             # Module configuration
      settings.php           # Release defaults of the module settings (panel values live in the settings table)
      <module>.php           # Module-specific config
    tests/                   # Module tests (own phpunit.xml)
    module.json              # Module manifest
```

`src/modules/*` is ignored by the core repository: each module is cloned from its own repository (or installed from a release ZIP).

### EnabledModulesResolver

`App\Infrastructure\Modules\Services\EnabledModulesResolver` is the only filter on which modules a process loads. `rejected()` lists the modules enabled in the database but left out as incompatible (`RejectedModuleDTO`). `AdminPanelProvider` (before compiling any module provider or Resource), `ModulesServiceProvider` (module migrations, from `migrationPaths()`: every compatible module on disk, enabled or not) and `ModuleLoader` all ask it. Compatibility comes from `ModuleCompatibilityService`, which reads `module.json` and evaluates it with `ModuleCompatibilityChecker` (`requires.core`, `filament`, `php`, `laravel`, `extensions`); it never loads module code and never writes module state. Enabling a module, installing a ZIP and staging an update go through the same check, and update detection evaluates each release's `module.json` asset with the same checker.

### ModuleLoader

`App\Modules\ModuleLoader` is responsible for booting enabled modules. Its flow:

1. Asks `EnabledModulesResolver` for the modules to load: enabled in the database (with optional cache) and compatible according to their `module.json` on disk
2. For each module, registers an SPL autoloader for its namespace
3. Instantiates and registers the module's `ModuleServiceProvider`
4. Registers the module's hooks: slots, permissions, page prefixes, routes, calendar sources, navigation

### ModuleServiceProvider

`App\Modules\ModuleServiceProvider` is the abstract base class that every module must extend. It provides:

**Automatic lifecycle** (`boot()`):
- Route loading (`routes/web.php`, `routes/api.php`)
- View loading (own namespace)
- Translation loading
- Migration loading
- Policy registration

**Integration hooks** (methods to override):

| Hook | Return type | Purpose |
|------|-------------|---------|
| `registerPermissions()` | `PermissionDTO[]` | Module permissions |
| `registerNavigation()` | `NavigationItemDTO[]` | Admin-panel navigation items, collected in `ModuleNavigationRegistry`. They are never copied into the public menu |
| `registerSlots()` | `SlotRegistrationDTO[]` | Vue components injectable into the layout |
| `registerPagePrefixes()` | `PagePrefixDTO[]` | Page prefixes for Inertia resolution; `usesCoreLayout: true` (core ≥ 3.16) renders the core's persistent layout around their pages |
| `registerRoutes()` | `ModuleRouteDTO[]` | Public pages offered in the menu editor (`MenuItemResource`) |
| `registerCalendarSources()` | `class-string<CalendarEntrySourceInterface>[]` | Entries for the public calendars (`/calendario` and the home widget, through `/eventos/calendario`) |
| `registerPolicies()` | `array<model, policy>` | Authorization policies |
| `registerNavigationGroups()` | `array<label, options>` | Filament navigation groups |
| `registerFilamentPages()` | `Page[]` | Filament pages |
| `getSettingsSchema()` | `Component[]` | Module settings form |
| `onEnable()` / `onDisable()` | `void` | Lifecycle callbacks |

Modules may also define `registerFilamentWidgets()`: it is not declared on `ModuleServiceProvider`, and the panel calls it only when it exists.

**Public menu**: a module's public pages reach the header or footer only when an admin adds a link in the menu editor, picking one of the pages offered by `registerRoutes()` (or the core's own pages, from `RouteRegistry`). Enabling a module creates no menu links. A link whose route is no longer available (its module was uninstalled) still opens in the editor, labelled "Página no disponible: …", so it can be deactivated or repointed; `MenuService` leaves such links out of the rendered menu and logs a warning at most once an hour per link.

### Module lifecycle

```
(install ZIP) -> discover -> enable -> boot -> (disable -> uninstall)
```

1. **Install (optional)**: from `ModulesPage`, an admin uploads a release ZIP (maintenance mode required). `ModuleInstaller::installFromZip()` validates the manifest, rejects incompatible packages, moves the module into `src/modules/`, publishes its pre-built assets and fires `ModuleInstalled`; the page then runs discovery. A module cloned into `src/modules/` skips this step
2. **Discover**: `php artisan module:discover` (or the panel) scans `src/modules/` and registers modules in the database. Fires `ModuleDiscovered`
3. **Enable**: `php artisan module:enable` or the panel. Refuses an incompatible module, checks dependencies, runs the module's migrations and seeders the first time it is enabled (a failing migration stops the enable and reaches the caller), then fires `ModuleEnabled` (clears caches, queues the Vue asset build, reactivates the module's existing menu links)
4. **Boot**: on every request, `ModuleLoader::boot()` loads the ServiceProvider of each module `EnabledModulesResolver` accepts and registers its hooks. Migrations of every compatible module on disk are known to `migrate` (`EnabledModulesResolver::migrationPaths()`); `module:migrate` runs one module's
5. **Disable**: `php artisan module:disable` deactivates the module without deleting data. Fires `ModuleDisabled` (deactivates its menu links)
6. **Uninstall**: `ModuleManagerService::uninstall()` deletes the module directory and, when asked to delete data, rolls back its migrations. Fires `ModuleUninstalled` (deletes its menu links)

### Module system events

| Event | Listeners |
|-------|-----------|
| `ModuleEnabled` | `ClearCachesOnModuleChange`, `BuildModuleAssetsOnEnabled`, `ActivateMenuItemsOnModuleEnabled` |
| `ModuleDisabled` | `ClearCachesOnModuleChange`, `DeactivateMenuItemsOnModuleDisabled` |
| `ModuleInstalled` | `ClearCachesOnModuleChange` |
| `ModuleUpdated` | `ClearCachesOnModuleChange` |
| `ModuleUninstalled` | `DeleteMenuItemsOnModuleUninstalled` |

### Schema protection (Schema Guard)

`ModuleSchemaGuard` and `CoreTableRegistry` prevent module migrations from modifying core tables. `ModuleMigrationAnalyzer` analyzes migrations before execution to detect violations.

### Module commands

**Management**: `module:list`, `module:discover`, `module:enable`, `module:disable`, `module:migrate`, `module:seed`, `module:build`, `module:publish-assets`, `module:publish-build-assets`, `module:sync-from-image`

**Updates**: `module:check-updates`, `module:update`, `module:set-source` (GitHub repository a module is updated from). `module:finish-update` and `module:refresh-caches` are hidden commands the updater runs in a separate process

**Scaffolding**: `module:make` (complete module), `module:make-entity`, `module:make-enum`, `module:make-event`, `module:make-listener`, `module:make-notification`, `module:make-controller`, `module:make-request`, `module:make-service`, `module:make-dto`, `module:make-migration`, `module:make-test`, `module:make-filament-resource`, `module:make-relation-manager`, `module:make-widget`, `module:make-vue-page`, `module:make-vue-component`

**Core**: `core:version`, `core:check-updates`, `core:record-deployment` (run by the container entrypoint), `content:convert-trix`, `permissions:sync`, `make:admin-user`, `users:assign-member-role`, `users:mark-animated-avatars`, `elasticsearch:test`

### Compatibility and updates

**Compatibility.** `ModuleCompatibilityService` reads a module's `module.json` on disk and evaluates its `requires` block (`core`, `filament`, `php`, `laravel`, `extensions`) with `ModuleCompatibilityChecker` against `HostEnvironmentProvider`'s view of the host. `CoreModuleMinimumVersions` adds minimum module versions the core itself needs (`game-tables` 2.2.0). Enabling, ZIP install, update staging and update detection all use the same check. Enabled modules rejected as incompatible (`EnabledModulesResolver::rejected()`) are not loaded; `AdminPanelProvider` lists them in a `BODY_START` banner for signed-in users, and `ModulesPage` shows an "Incompatible" badge with the reasons and a disabled "Habilitar" action.

**Module updates.** `CheckModuleUpdatesJob` is scheduled daily at 04:00 (`routes/console.php`) and `ModuleUpdatesPage` can check on demand. For each module with a repository, `ModuleUpdateChecker` asks GitHub for releases (`GitHubReleaseFetcher`; prereleases only through `ReleaseChannelPolicy`) and evaluates each release's `module.json` asset. The newest compatible release is offered (`ReleaseSelection`); a newer release this core cannot run is stored in `modules.latest_blocked_version` / `latest_blocked_reason` and listed under *Versiones no compatibles*, without an update button. Applying requires the `updates.apply` permission and maintenance mode: `ModuleUpdatesPage` re-checks both server-side and dispatches `UpdateModuleJob` (queued, `ShouldBeUnique`, one try, 600 s timeout), followed with `wire:poll`. `ModuleUpdater` backs up the module, downloads and verifies the checksum, stages and swaps the files (`ModulePackageInstaller`), runs migrations, seeders and the health check in a separate process (`module:finish-update`), refreshes caches, and restores the previous files if a step fails before the database commit. The job then runs `queue:restart` so workers drop the old module classes.

**Core updates.** The core is not updated from the panel: it is deployed continuously from `updates.core.branch` (`main` by default): once CI passes on `main`, `deploy.yml` calls the deployment platform's webhook, which builds and rolls out the image: first on the canary target and, once it is healthy, on the rest. `CoreUpdatesPage` (`updates.apply`) and `core:check-updates` compare the deployed commit (`SOURCE_COMMIT`, injected by the deployment platform into the container environment) with the branch through the GitHub compare API; the page lists the commits not deployed yet and warns when commits are pending and maintenance mode is off. The container entrypoint records each deployment (`core:record-deployment`).

---

## 5. Dependency injection

Laravel's service container manages all dependencies. Bindings are defined in three Service Providers:

### AppServiceProvider (main bindings)

**Domain repositories** (`bind` - new instance per resolution):
- `ArticleRepositoryInterface` -> `EloquentArticleRepository`
- `EventRepositoryInterface` -> `EloquentEventRepository`
- `GalleryRepositoryInterface` -> `EloquentGalleryRepository`
- `PhotoRepositoryInterface` -> `EloquentPhotoRepository`
- `UserRepositoryInterface` -> `EloquentUserRepository`
- `SlugRedirectRepositoryInterface` -> `EloquentSlugRedirectRepository`
- `ModuleRepositoryInterface` -> `EloquentModuleRepository`
- `EmailLogRepositoryInterface` -> `EloquentEmailLogRepository`
- `MenuItemRepositoryInterface` -> `EloquentMenuItemRepository`

**Query Services** (`singleton` - reused within the request):
- `EventQueryServiceInterface` -> `EventQueryService`
- `ArticleQueryServiceInterface` -> `ArticleQueryService`
- `GalleryQueryServiceInterface` -> `GalleryQueryService`
- `HeroSlideQueryServiceInterface` -> `HeroSlideQueryService`
- `SitemapQueryServiceInterface` -> `SitemapQueryService`
- `TagQueryServiceInterface` -> `TagQueryService`
- `AboutPageServiceInterface` -> `AboutPageService`

**Application services** (`singleton`):
- `SettingsServiceInterface`, `DashboardWidgetConfigServiceInterface`, `ThemeSettingsServiceInterface`
- `ImageOptimizationServiceInterface`, `AuthServiceInterface`, `EmailVerificationPolicyInterface`, `UserServiceInterface`
- `UserModelQueryServiceInterface`, `LegalPageServiceInterface`, `ContactServiceInterface`
- `SlugRedirectServiceInterface`, `ResponseDTOFactoryInterface`, `SiteBrandingServiceInterface`
- `LogContextProviderInterface` (`bind`, resolves to `HttpLogContextProvider`)

**SEO** (`bind`):
- `SeoMetadataFactoryInterface` -> `SeoMetadataFactory`, with `CloudinaryImageUrlBuilder`, `SeoDescriptionBuilder` and `StructuredDataBuilder`

**Calendar and content** (`singleton`):
- `CalendarSourceRegistryInterface`, `CalendarAggregatorServiceInterface`
- `TrixHtmlConverterInterface`, `TrixContentMigratorInterface`

**Mail system** (mix of `singleton` and `bind`):
- `MailConfigurationServiceInterface`, `MailTestServiceInterface`, `EmailQuotaServiceInterface`
- `MailStatisticsServiceInterface`, `SnsMessageValidatorInterface`

**Module system** (`singleton` unless noted):
- `ModuleRepositoryInterface` (`bind`), `ModuleMigrationAnalyzerInterface` (`bind`)
- `ModuleManagerServiceInterface`, `ModuleContextServiceInterface`
- `ModulePermissionRegistryInterface`, `ModuleNavigationRegistryInterface`
- `ModuleSlotRegistryInterface`, `ModulePageRegistryInterface`, `ModuleRouteRegistryInterface`
- `ModuleScaffoldingServiceInterface`, `ModuleDiscoveryService`, `ModuleAssetBuilder`
- `ModuleDependencyResolver`, `ModuleMigrationRunner`, `ModuleSeederRunner`
- `CoreTableRegistry`, `ModuleSchemaGuard`, `ModuleLoader`
- Compatibility: `ConstraintMatcher`, `ModuleManifestReader`, `ModuleCompatibilityChecker`, `HostEnvironmentProviderInterface`, `ModuleCompatibilityServiceInterface`, `EnabledModulesResolverInterface`

**Navigation** (`singleton`):
- `MenuServiceInterface`, `MenuItemHrefResolverInterface`, `RouteRegistryInterface`

**Update system** (`singleton`):
- `GitHubReleaseFetcherInterface`, `ModuleBackupServiceInterface`, `ModuleHealthCheckerInterface`
- `ModuleUpdateCheckerInterface`, `ModuleUpdaterInterface`, `ReleaseChannelPolicy`
- `ModulePackageInstallerInterface`, `ModulePostUpdateRunnerInterface` (-> `ProcessModulePostUpdateRunner`)
- `CoreVersionServiceInterface`, `CoreUpdateCheckerInterface`

### AuthorizationServiceProvider

- `RoleRepositoryInterface` -> `EloquentRoleRepository` (`bind`)
- `PermissionRepositoryInterface` -> `EloquentPermissionRepository` (`bind`)
- `AuthorizationServiceInterface` -> `AuthorizationService` (`singleton`)
- `RoleServiceInterface` -> `RoleService` (`singleton`)
- `PermissionRegistryInterface` -> `PermissionRegistry` (`singleton`)
- `AssociationMembershipServiceInterface` -> `AssociationMembershipService` (`singleton`; reads the setting on every call)
- `MemberRoleServiceInterface` -> `MemberRoleService` (`singleton`)
- `AuthorizationCacheVersion` and `AssociationMemberRoleWarning` (`scoped`: one instance per request or queue job)

### ModulesServiceProvider

- `ModuleInstallerInterface` -> `ModuleInstaller` (`bind`)

### Singleton vs bind pattern

- **`singleton`**: For stateless services or those with shared state (Query Services, registries, configuration). Resolved once and reused.
- **`bind`**: For repositories and services that must be fresh instances per resolution (prevents shared state in writes).

---

## 6. Authentication and authorization

### UUID user provider

`UuidEloquentUserProvider` extends the standard Eloquent provider to validate that identifiers are valid UUIDs before querying the database. This prevents errors when old "remember me" cookies contain integer IDs from before a UUID migration.

```php
class UuidEloquentUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->isValidUuid($identifier)) {
            return null; // Forces re-authentication
        }
        return parent::retrieveById($identifier);
    }
}
```

Registered in `AppServiceProvider::boot()`:

```php
Auth::provider('uuid-eloquent', function ($app, array $config) {
    return new UuidEloquentUserProvider($app['hash'], $config['model']);
});
```

### Role and permission system

Authorization is based on a role system with granular permissions:

**Entities**: `Role` and `Permission` (domain), with many-to-many relationship.

**Flow**:
1. Permissions are defined in `CorePermissionDefinitions` and in each module via `registerPermissions()`
2. They are registered in memory through `PermissionRegistry` during boot
3. They are synchronized with the database via `php artisan permissions:sync`, which the production container runs on every start. From the core release with #184 a module's permissions are also synced when it is enabled, updated from the panel (in `module:finish-update`, before its migrations), updated from a ZIP, or found with a new version by `module:discover`. Enabling and ZIP updates run the sync in a new PHP process, because the process that changed the module still has the previous providers loaded. The sync never removes permissions and never grants one to a role
4. Policies verify permissions through `AuthorizationService`

**Permission format**: `resource.action` (example: `events.create`, `articles.update`, `admin.access`)

**Core permissions** (grouped by resource):

| Resource | Actions | Default roles |
|----------|---------|---------------|
| `events` | `view_any`, `view`, `create`, `update`, `delete` | `editor` |
| `articles` | `view_any`, `view`, `create`, `update`, `delete` | `editor` |
| `galleries` | `view_any`, `view`, `create`, `update`, `delete` | `editor` |
| `hero_slides` | `view_any`, `create`, `update`, `delete` | `editor` |
| `tags` | `view_any`, `create`, `update`, `delete` | `editor` |
| `users` | `view_any`, `view`, `create`, `update`, `delete` | (admin only) |
| `roles` | `view_any`, `view`, `create`, `update`, `delete` | (admin only) |
| `settings` | `manage`, `maintenance` | (admin only) |
| `updates` | `apply` | (admin only) |
| `mail` | `configure`, `view_stats` | (admin only) |
| `admin` | `access` | `editor` |

**Panel access** is `admin.access`, from any role: `UserModel::canAccessPanel()` (asked by Filament and by the maintenance bypass) and `UserService::canAccessPanel()` both go through `AuthorizationService::can()`, so admins always pass and no role name is checked. A role such as "Treasury" with `admin.access` and some module permissions opens the panel without being `editor`; an `editor` role without `admin.access` does not. The site's user menu shows the panel link with the same rule (`useAuth().can('admin.access')`).

Modules extend this system with their own permissions using the format `module:resource.action`.

Admins pass every check (fast path in `AuthorizationService`). `MenuItemPolicy` checks the `menu_items.*` permissions defined in `CorePermissionDefinitions` (granted to no role by default; admins grant them in the role editor), and a test fails whenever a policy or panel page checks a permission nobody defines. Cached role and permission lookups carry two version tokens, each read once per request: a global one and one per user. Changes to a role (edits, deletions, permission changes), bulk role assignments and seeders replace the global token (`invalidateCache()`); changes to one user's roles (the user form, `assignRole`/`removeRole`/`syncRoles`, anonymizing) replace only that user's token (`invalidateCacheFor()`), so everyone else keeps their cached lookups. Both are replaced after the database commit, and the cache fails closed: a missing or malformed token, a malformed entry or a store error falls back to the database, and a token that cannot be replaced is forgotten and the error rethrown. Several panel pages check `isAdmin()` instead of a permission: `ModulesPage` and the pages in `Filament/Pages/Settings/`.

### Base role and association member role

Two roles mean different things:

| Role | Who holds it | Used for |
|------|--------------|----------|
| `member` | Every account (the base role) | Permissions any registered user needs; modules grant them to `member` |
| Association member role (`socio` by default) | Members of the association | Members-only content, early access, memberships; modules ask `AssociationMembershipServiceInterface` |

**`member`.** `MemberRoleServiceInterface::assignTo()` gives it to every new account, keeping its other roles: registration (`AuthService::register()`, before the `Registered` and `UserRegistered` events), accounts created in the panel (`CreateUser::afterCreate()`, after the roles checkbox list has synced), `make:admin-user` and `UserSeeder` (admin and editor included). If the role does not exist, the account is still created and a warning is logged. The migration `2026_10_14_100002_create_member_role` creates it when missing (same values as `RolesSeeder`), so it exists right after migrating, before any seeder runs. Accounts created before 3.8.0 get it with `users:assign-member-role` (see the [deployment guide](deployment-guide.md#member-role-and-email-verification-core-38)). `EloquentUserRepository::save()` does not assign it: code that creates accounts there must call `assignTo()`.

**`member` cannot be lost.** From core 3.8.1, `BaseRoleRule` (`App\Application\Authorization\Services`, a concrete class whose `NAME` is `UserRole::Member`) decides whether a role is the base role. It goes by the name, not by the `is_protected` flag, which `member` does not carry:

- **Role editor.** `member` can be neither deleted nor renamed: `RolePolicy::delete()` and `forceDelete()` refuse it, `RoleService::delete()` throws `RoleProtectedException::cannotDeleteBaseRole()`, `RoleResource` hides the delete actions and disables the name field (with a hint), and `EditRole::mutateFormDataBeforeSave()` drops a forged `name`. Its permissions and display name can still be edited.
- **User form.** The roles checkbox list of `UserResource` shows `member` ticked and disabled, with a help text, also for an account that lacked it (it gets it on save); *Deseleccionar todos* skips it. Filament only accepts enabled options by default and would reject the ticked one, so the list validates against every role. Saving syncs the pivot with the submitted state, so `EditUser::afterSave()` calls `assignTo()` again, as `CreateUser::afterCreate()` does: a forged Livewire state cannot remove it. An anonymized account is left without roles: its form does not tick `member` and saving does not give it back.
- **Authorization service.** `AuthorizationService::removeRole($user, 'member')` does nothing: no exception and no cache invalidation. `syncRoles()` keeps `member` when the user holds it (read from the database, not the cache), even if the names leave it out, and never gives it to a user without it. The PHPDoc of `AuthorizationServiceInterface` states both; no signature changed.

Anonymizing an account (`UserService::anonymize()` and `anonymizeWithContentTransfer()`) is the only path that removes `member`, together with every other role, on purpose.

**Association member role.** Chosen in *Configuración del sitio → Autenticación → Socios de la asociación* (setting `association_member_role`, `settings.manage`). The select lists the existing roles except `admin`, `editor` and `member`, and validates the choice server-side against that list. `AssociationMembershipServiceInterface` (`App\Application\Authorization\Services`) is the only place that decides who is an association member:

- `roleName()` returns the configured name, `socio` when the setting is unset or blank, and `null` when the stored value is `admin`, `editor` or `member` (compared case-insensitively). A forbidden value logs a warning at most once per request or queue job (`AssociationMemberRoleWarning`).
- `isAssociationMember($user)` is true only when the user holds that role. When `roleName()` is `null` or the configured role does not exist, nobody is an association member. The settings page then shows a warning on the field and leaves it blank, so a valid role has to be chosen and saved.

The migration `2026_10_14_100001_create_association_member_role` creates `socio` (display name *Socio*) when no role has that name, and never changes an existing one.

The configured role can be neither deleted nor renamed: `RolePolicy::delete()` and `forceDelete()` refuse it, `RoleService::delete()` throws `RoleProtectedException::cannotDeleteAssociationMemberRole()`, `RoleResource` hides the delete actions and disables the name field (with a hint), and `EditRole::mutateFormDataBeforeSave()` drops a forged `name`, as it does for protected roles. Its permissions and display name can still be edited. To use another role, choose it in the site settings first; the old one can then be renamed or deleted.

### Required email verification

The setting *Requerir verificación de email* (`auth_email_verification_required`, *Configuración del sitio → Autenticación*, off by default) is enforced on the server by `EnsureEmailIsVerifiedWhenRequired`, appended to the `web` group, so module web routes get it too. It only acts when there is a signed-in user and the route acts as that user:

- routes whose middleware includes `auth` (or any subclass of Laravel's `Authenticate`);
- routes with the alias `email.verified-if-required`, for routes that also work for guests but act as the signed-in user when there is one. Without a signed-in user the alias does nothing.

Public pages stay open to every account, as they are to guests.

`EmailVerificationPolicyInterface::mustVerify($user)` decides who has to verify. It is true when the setting is on, the email is not verified, the account is not anonymized and the account was created at or after `email_verification_enforced_since`. That setting is the cutoff: the migration `2026_10_14_100003_record_email_verification_cutoff` records it once, in UTC (ISO 8601), when 3.8.0 is deployed, and a later run never moves it. Accounts created before the cutoff keep working without verifying, as they did when the setting was only shown to the frontend. The policy fails closed: without a readable cutoff or without a creation date, every unverified account must verify while the setting is on.

An account that must verify is sent to `/verificar-email` (`verification.notice`), and the page it asked for is remembered: verifying returns to it. A new account that must verify goes there right after signing up. JSON requests that are not Inertia visits get a `403` whose `message` is `auth.email_verification_required` (translated), with the locale-independent code `error: "email_verification_required"` (`EnsureEmailIsVerifiedWhenRequired::JSON_ERROR_CODE`) and the header `X-Email-Verification-Required: 1` (`JSON_ERROR_HEADER`), so callers can recognise it without comparing texts. The notice page (`Auth/VerifyEmail`, props `email` and `pendingEmail`) says which address the link was sent to, shows flashed errors, offers to resend the link, links to the profile to correct a mistyped email, and signs out; an account that has already verified is sent on from the notice.

#### What the interface knows

The shared `auth.user` prop (see `HandleInertiaRequests::getAuthUser()`) tells the frontend whether *this* account must verify, which `authSettings.emailVerificationRequired` cannot, since the cutoff depends on the account:

- `mustVerifyEmail: boolean`, from the same `EmailVerificationPolicyInterface::mustVerify()` the middleware applies. Guests get `auth.user = null`, which the frontend reads as false. If the policy throws, the error is logged as a warning and the prop falls back to "not verified": an unverified account is told to verify and a verified one never is; the server still enforces the rule on its own.
- `verificationEmail: string`, the address a verification link goes to: `pending_email` when an email change awaits confirmation, otherwise `email` (see *Correcting the email*).

On the frontend, `useAuth().mustVerifyEmail` exposes the flag (false without user or on older cores). While it is true, `DefaultLayout` shows `components/layout/VerificationBanner.vue` under the header: it says where the link was sent, offers *Reenviar enlace* (POST `verification.send`, with sending, sent, too-many-requests and error feedback; the banner stays after a resend) and *Más información* (link to the notice). It is never shown in `AuthLayout`, so not on the notice page itself.

Modules use `useVerificationGate()` (`composables/useVerificationGate.ts`, core 3.10 or later) to disable the forms the server would reject: `blocked`, `reason(action)` (translated sentence per `VerificationGateAction`: `register`, `cancelRegistration`, `book`, `cancelBooking`, `createTable`, `createCampaign`, `checkIn`, `withdraw`, `changePassword`, `form`), `message` (= `reason('form')`), `linkLabel`, `noticeUrl`, `goToNotice()` and `isVerificationRequired(error)`, which recognises the 403 from a `fetch` `Response` (by the header, or a redirect to the notice), its parsed body, an axios-like error, the response Inertia passes to `onHttpException` and the notice page an Inertia form reaches `onSuccess` with after the server redirected it. The core uses it for the only core form the middleware blocks, the password change in the profile (`PUT /perfil/contrasena`, `ProfileAccountTab`): disabled with the reason and the link to the notice; the rest of the profile (`profile.update`) stays open. This is interface only: the server's rules and exemptions are the same.

#### Correcting the email

Changing the email in the profile stores it as `pending_email` and mails a signed link (`verification.pending-email`, valid 60 minutes) **to the new address**: `VerifyPendingEmailNotification` implements `App\Notifications\Contracts\NamesMailRecipient`, and `UserModel::routeNotificationForMail()` only lets notifications with that contract choose their recipient. Following the link replaces the email and also marks the account as verified, since it proves the new address belongs to the user.

While an email change is pending, *Reenviar* (`verification.send`) sends that confirmation link again to the pending address instead of the usual link to the current one: the current address is the one being replaced, often a typo. To go back to the current address, save the profile with it, which clears `pending_email`.

Verification and email change links are only accepted for the signed-in account: any other id in the link is treated as an invalid link.

#### Always reachable

- `verification.*`: the notice, resending the link and the verification links;
- `logout`;
- `profile.show` and `profile.update`, to correct the email (`profile.update` is limited to 6 requests a minute, counted apart from `verification.send`);
- `filament.*`: the admin panel has its own middleware stack and access control (`canAccessPanel()`); the exemption keeps an unverified admin out of trouble should the middleware ever reach it.

Livewire requests, `/up` and static assets have no `auth`, so they are never stopped.

### AuthorizesWithPermissions trait

Shared trait used by all policies to delegate verification to `AuthorizationService`:

```php
trait AuthorizesWithPermissions
{
    protected function authorize(object $user, string $permission): bool
    {
        return app(AuthorizationServiceInterface::class)->can($user, $permission);
    }

    protected function authorizeAny(object $user, array $permissions): bool { /* ... */ }
    protected function authorizeAll(object $user, array $permissions): bool { /* ... */ }
}
```

**Policy example**:

```php
class EventPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'events.view_any');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'events.create');
    }
}
```

**Core policies**: `EventPolicy`, `ArticlePolicy`, `GalleryPolicy`, `UserPolicy`, `HeroSlidePolicy`, `RolePolicy`, `TagPolicy`, `MenuItemPolicy`

---

## 7. Data flow

### Request lifecycle

```
Client (browser)
    |
Nginx (reverse proxy)
    |
PHP-FPM (Docker container)
    |
Middleware stack:
    BlockBotsMiddleware (global)
    -> web group (session, CSRF, ...) -> HandleInertiaRequests -> SecurityHeadersMiddleware -> EnsureSiteIsNotInMaintenance -> EnsureEmailIsVerifiedWhenRequired
    |
Router (Laravel)
    |
Controller (QueryService / Service injection)
    |
QueryService -> Repository -> Eloquent Model -> PostgreSQL
```

### Response lifecycle

```
PostgreSQL
    |
Eloquent Model (hydration)
    |
ResponseDTOFactory -> Response DTO (with HTML sanitization)
    |
Controller -> Inertia::render('Page', props)
    |
HandleInertiaRequests (shared data: auth, theme, navigation, slots, cloudinary, site seo)
    |
Inertia (JSON for SPA navigation / HTML for first load: page object in <script data-page="app" type="application/json">)
    |
Vue 3 (component rendering)
    |
Client (browser)
```

### Complete flow example: event listing

1. `GET /eventos` reaches the router
2. `EventController::index()` receives `TagFilterRequest` (validation)
3. `EventQueryServiceInterface::getPublishedEventsPaginated()` queries events
4. Internally, the Query Service uses Eloquent to query PostgreSQL
5. `EloquentResponseDTOFactory::createEventDTO()` converts models to DTOs, sanitizing HTML
6. `EventResource` transforms DTOs for the Inertia response
7. `SeoMetadataFactoryInterface::forPage()` builds the page's `seo` prop, and `Inertia::render('Events/Index', [...])` returns the response
8. `HandleInertiaRequests` adds shared data (auth, theme, navigation)
9. Vue renders `Events/Index.vue` with the received props

---

## 8. Admin panel (Filament)

### Resource structure

Location: `src/app/Filament/Resources/`

CRUD resources for content management:

| Resource | Model | Features |
|----------|-------|----------|
| `EventResource` | `EventModel` | Full CRUD, date picker, image upload, download links |
| `ArticleResource` | `ArticleModel` | Full CRUD, rich text editor, author relation |
| `GalleryResource` | `GalleryModel` | CRUD with nested photo management |
| `UserResource` | `UserModel` | User management (admin only) |
| `HeroSlideResource` | `HeroSlideModel` | Hero carousel slides, image upload, ordering |
| `TagResource` | `TagModel` | Tag management |
| `RoleResource` | `RoleModel` | Role and permission management |
| `MenuItemResource` | `MenuItemModel` | Menu item management |

### BaseResource

All resources extend `App\Filament\Resources\BaseResource` (not `Filament\Resources\Resource` directly). This base resource applies Spanish-style capitalization by disabling Title Case:

```php
abstract class BaseResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;
}
```

### Admin pages

Location: `src/app/Filament/Pages/`

| Page | Purpose |
|------|---------|
| `Dashboard` | Main panel with configurable widgets |
| `SiteSettings` | General site configuration |
| `ModulesPage` | Listing and management of installed modules |
| `ModuleSettingsPage` | Individual settings for each module, stored in the `settings` table under `module_settings:<name>` and deep-merged over the module's `config/settings.php` release defaults (stored values win, lists replace defaults whole), so they survive module updates, redeploys and rollbacks |
| `ModuleUpdatesPage` | Checking module updates and queueing them (`updates.apply`, maintenance mode required) |
| `CoreUpdatesPage` | Deployed commit and commits pending on the deploy branch (`updates.apply`); deploys run from `deploy.yml`, the page only reports |

Settings pages in `src/app/Filament/Pages/Settings/` (admin only):

| Page | Purpose |
|------|---------|
| `AboutPageSettings` | Content of the "About us" page |
| `LegalPagesSettings` | Legal entity data and the content of the legal pages |
| `DashboardSettings` | Dashboard widgets: on/off, order, table row limits |
| `MailSettings` | Mail driver, SMTP/SES/Resend credentials, send quotas, test email |
| `MailStatisticsPage` | Log of sent emails (`email_logs`), filterable by status |

`SiteSettings` is open to `settings.manage` (all tabs except maintenance) and `settings.maintenance` (maintenance tab).

### Widget system

Dashboard widgets, configurable by the administrator via `DashboardWidgetConfigServiceInterface`:

**Core widgets**:
- `UpcomingEventsWidget` - Upcoming events counter (stats)
- `RecentArticlesWidget` - Recent articles (table with configurable limit)
- `GalleryStatsWidget` - Photo/gallery statistics (stats)
- `MailHealthWidget` - Mail system status (admin only)
- `MailStatsOverviewWidget` - Mail delivery statistics (admin only)

**Module widgets**: Registered via `registerFilamentWidgets()` in the module's ServiceProvider (an optional method: it is not declared on `ModuleServiceProvider`, and the panel calls it only when it exists).

**Dashboard configuration**: The `DashboardSettings` page (`/admin/dashboard-settings`) allows enabling/disabling widgets, setting order, and configuring table row limits.

### Module integration in Filament

Modules can:
- Register their own Filament resources
- Add widgets to the dashboard
- Register Filament pages via `registerFilamentPages()`
- Add navigation groups via `registerNavigationGroups()`
- Provide settings forms via `getSettingsSchema()`

---

## 9. Frontend architecture

### Technology stack

- **Vue 3.5** with Composition API and `<script setup>`
- **TypeScript 6** in strict mode (no `any`)
- **Inertia.js 3** as the bridge between Laravel and Vue (no separate REST API; SSR disabled)
- **Pinia 4** for state management
- **vue-i18n 11** for translations (`locales/es.ts`, `locales/en.ts`)
- **@unhead/vue 3** for the document head
- **@lucide/vue** for icons (`utils/icons.ts`)
- **Tailwind CSS 4** for styling
- **Vite 8** (Rolldown) for build and HMR

### Runtime loading

**First load (Inertia 3).** `app.blade.php` renders `@inertia`, which embeds the page object in `<script data-page="app" type="application/json">` next to `<div id="app">`. Before Inertia boots, `app.ts` reads that script (`readInitialPageJson()`) and takes three shared props from it: `cloudinary` (passed to `setCloudinaryConfig()`), `modulePages` (page prefix to module mapping) and `moduleTranslations`.

**SEO.** Every page sends a `seo` prop built by `SeoMetadataFactoryInterface`; the shared `seo` prop carries the site defaults (`forSite()`). On the first request `<x-seo-head>` server-renders the tags for crawlers (`data-seo-server`). `app.ts` removes them on boot and calls `useServerSeo()` once in the app root, so unhead owns the head and follows every navigation.

**Cloudinary.** The cloud name and folder prefix come at runtime from the shared `cloudinary` prop (`{ cloudName, prefix }`, from `config/cloudinary.php`). `setCloudinaryConfig()` stores them on `globalThis`, so module bundles built in CI carry no installation config. `VITE_CLOUDINARY_*` is only a fallback.

**Module import map and public frontend API.** `src/resources/js/public-api.json` is the core's public frontend API for modules: the shared packages (`vue`, `pinia`, `vue-i18n`, `@inertiajs/vue3`, `@unhead/vue`, FullCalendar) and the public entries of `resources/js` (form and base UI components, `ModuleSlot`, composables such as `useAuth` and `useNotifications`, `@/contracts/cookieConsent`, utilities). One list read by everything: `vite.config.ts` declares each one as an entry, keeps its exports (`preserveEntrySignatures: 'exports-only'`) and splits the shared chunks `vendor-vue`, `vendor-inertia`, `vendor-head` and `vendor-calendar` (`build.rolldownOptions.output.codeSplitting.groups`); `<x-module-import-map>` (`App\View\Components\ModuleImportMap`) maps every specifier to its chunk through the Vite manifest; module builds (`src/vite.module.config.ts`, `defineModuleConfig`) keep them external and stop on any other runtime import of `@/`, so the page loads one instance of each and every module uses the installed core. `npm run verify:build` (`scripts/verify-build.mjs`, run in CI after the build and in `Dockerfile.prod`) fails if an entry is missing from the manifest, ships CSS, loses an export of `public-api.exports.json` (breaking) or gains one not in it, if the app, the packages and the entries do not share one chunk per vendor group, and if the core build contains a module's sources (the globs over `modules/*` exist only in development). `npm run check:public-api` (CI lint job) compares the declarations of the public entries, and of the core files they import, with `public-api.api.md`.

### Layouts

Location: `src/resources/js/layouts/`

- `DefaultLayout.vue` - Main layout for the public site (header, footer, navigation, module slots). Applied by the app (`createInertiaApp({ layout })`, `utils/pageLayout.ts`) to core pages and to module prefixes with `usesCoreLayout`, and kept across visits
- `AuthLayout.vue` - Persistent layout for authentication pages and the maintenance page (`defineOptions({ layout })` + `setLayoutProps()`)

### Pages

Location: `src/resources/js/pages/`

```
pages/
  Home.vue                  # Home page
  About.vue                 # About us
  Events/
    Index.vue               # Event listing
    Show.vue                # Event detail
  Articles/
    Index.vue               # Article listing
    Show.vue                # Article detail
  Gallery/
    Index.vue               # Gallery listing
    Show.vue                # Gallery detail
  Calendar/
    Index.vue               # Event calendar
  Legal/
    Show.vue                # Legal pages
  Search/
    Index.vue               # Global search
  Maintenance.vue           # 503 page in maintenance mode
  Profile/
    Show.vue                # User profile
  Auth/
    Login.vue               # Login
    Register.vue            # Registration
    ForgotPassword.vue      # Password recovery
    ResetPassword.vue       # Password reset
    VerifyEmail.vue         # Email verification
```

### Composables

Location: `src/resources/js/composables/`

Reusable functions that encapsulate reactive logic:

| Composable | Purpose |
|-----------|---------|
| `useEvents` | Event logic (formatting, filtering) |
| `useArticles` | Article logic |
| `useGallery` | Gallery logic |
| `useLightbox` | Full-screen image viewer |
| `useHeroSlider` | Hero carousel with autoplay |
| `useSeo` | Deprecated (modules built for core 3.0). Pages send a `seo` prop from the server; `useServerSeo()` in `app.ts` applies it on every navigation |
| `usePagination` | Reusable pagination |
| `useAuth` | Authentication state (`mustVerifyEmail` included) |
| `useVerificationGate` | Blocked state, reasons and 403 detection for forms an account that must verify its email cannot send |
| `useNotifications` | Toast notification system |
| `useTags` | Tag filtering |
| `useRoutes` | Named route helper |
| `useFlashMessages` | Session flash messages |
| `useFavicons` | Dynamic favicons (light/dark) |
| `useProfileTabs` | Profile tab navigation |
| `useMediaQuery` | Reactive responsive breakpoints |
| `useGridLayout` | Adaptive grid layout |
| `useEventDateBadge` | Formatted date badge for events |
| `useModuleSlots` | Module slot rendering |
| `useCalendarLocale` | Calendar localization |

### Components

Location: `src/resources/js/components/`

Organized by functional domain:

- **`ui/`** - Base components: `BaseCard`, `BaseButton`, `LoadingSpinner`, `ImagePlaceholder`, `TagBadge`, `TagFilter`, `TagList`, `ConfirmDialog`, `NotificationToast`, `EmptyState`, `SocialLinks`
- **`layout/`** - Layout: `TheHeader`, `TheNavigation`, `TheFooter`, `NavDropdown`, `UserDropdown`, `AuthLinks`, `ModuleSlot`
- **`events/`** - Events: `EventCard`, `EventList`, `EventsSection`
- **`articles/`** - Articles: `ArticleCard`, `ArticleList`
- **`gallery/`** - Gallery: `GalleryCard`, `GalleryGrid`, `PhotoLightbox`
- **`calendar/`** - Calendar: `CalendarWidget`, `EventCalendar`, `EventDetailPanel`, `EventTooltip`
- **`hero/`** - Carousel: `HeroSlider`
- **`contact/`** - Contact: `ContactForm`
- **`search/`** - Search: `SearchInput`
- **`map/`** - Map: `LocationMap`
- **`profile/`** - Profile: `ProfileHeader`, `ProfileSidebar`, `ProfileTabBar`, `ProfileTabIcon`, `ProfileAccountTab`
- **`form/`** - Forms: `FormToggle`, `FormNumberInput`, `FormCheckbox`, `FormCheckboxGroup`, `FormCheckboxWithTooltip`, `FormCombobox`, `FormRadioGroup`, `FormSelect`, `FormTagsInput`, `FormTooltip`

### Pinia stores

- `useAppStore` - Global application state

### Module slot system

Modules inject Vue components into predefined layout positions through the slot system:

1. The module registers slots in the ServiceProvider's `registerSlots()`
2. `ModuleSlotRegistry` stores the registrations
3. `HandleInertiaRequests` sends the slot payload to the frontend
4. The `ModuleSlot` component renders module components in the correct position
5. `useModuleSlots` provides the reactive API for accessing slots

---

## 10. External services

### Cloudinary (images)

Cloud image storage and transformation service.

- **Adapter**: `App\Infrastructure\Storage\Cloudinary\CloudinaryStorageAdapter`, registered as the `cloudinary` driver in `AppServiceProvider`
- **Disk**: Configured as `images` in `filesystems.php`
- **Optimization**: `ImageOptimizationService` resizes and compresses images before upload. Animated images (GIF, APNG, animated WebP: more than one frame, detected from the bytes by `App\Infrastructure\Images\AnimatedImageDetector`, whatever MIME type is claimed) are stored with their original bytes
- **Avatars**: stored by `App\Infrastructure\Images\AvatarPath` (profile: `users/<user id>/`, panel: `avatars/<Y>/<m>/`) as `<uuid>.<ext>`, or `<uuid>-animated.<ext>` when animated, with the extension of the stored bytes. The marker is part of the public id and has no dot, which a delivery URL would read as a format. `buildAvatarUrl()` adds `fl_animated` only to marked avatars: `f_auto` alone serves a still JPEG XL to browsers that accept it, and with the flag `f_auto` may turn a still image into a video. Before 3.10.1 the profile stored `users/<id>/<uuid>.jpg` and the panel `avatars/<Y>/<m>/<uuid>.<extension sent by the browser>`, whatever the bytes; only the animated ones are renamed, by `users:mark-animated-avatars` (static ones keep their name, which points at the same public id)
- **Cleanup**: `DeletesCloudinaryImages` trait automatically deletes images when models are deleted/updated
- **URLs**: Direct URL generation without Cloudinary Admin API calls

### Resend/SES (email)

Email sending system with dual support:

- **Dynamic configuration**: `MailConfigurationService` allows changing provider from the admin panel
- **Send quota**: `EmailQuotaService` controls daily send limits
- **Logging**: `LogSentEmail` listener records each sent email in `email_logs`
- **Statistics**: `MailStatisticsService` for delivery metrics
- **SES webhooks**: `SnsMessageValidator` processes bounce/complaint notifications from Amazon SES
- **Tests**: `MailTestService` for sending test emails

### Elasticsearch (logging)

Structured log destination for monitoring and analysis. `HttpLogContextProvider` enriches logs with HTTP request context (IP, User-Agent, URL, authenticated user).

### Cache, sessions and queues

- Cache, sessions and queues use the `database` driver by default (`src/.env.example`, `config/*.php`); Redis can be enabled through the environment (`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`)
- **Queued work**: `BuildModuleAssetsOnEnabled` (listener), `UpdateModuleJob`, `CheckModuleUpdatesJob`
- **Scheduler**: `CheckModuleUpdatesJob` daily at 04:00 (`routes/console.php`). In production supervisord runs `queue:work` and `schedule:work` next to nginx and PHP-FPM (`docker/prod/supervisord.conf`)

---

## 11. Key patterns

### Query services (reads) vs repositories (writes)

**CQRS-lite separation of concerns**:

- **Repositories** (`EventRepositoryInterface`): Write operations on domain entities. Work with domain objects (`Event`, `EventId`). Registered as `bind`.
- **Query Services** (`EventQueryServiceInterface`): Optimized read operations. Return response DTOs directly, without going through domain entities. Registered as `singleton`.

```
Write: Controller -> Service -> Repository -> Entity -> Model -> DB
Read:  Controller -> QueryService -> Model -> ResponseDTOFactory -> DTO -> Inertia
```

### DTO transformation with ResponseDTOFactory

`EloquentResponseDTOFactory` centralizes the conversion of Eloquent models to response DTOs:

- Converts each model type (`EventModel` -> `EventResponseDTO`)
- Sanitizes HTML with HTMLPurifier via `SanitizesHtml` trait
- Handles loaded relationships (tags, photos, author)
- Registered as singleton (`ResponseDTOFactoryInterface`)

### HasSlug with automatic redirects

The `HasSlug` trait manages URL slugs. Only `EventModel` uses it; articles and galleries take their slug from the admin form:

- **Creation**: Automatically generates slug from title + short UUID (`mi-evento-abc12345`)
- **Update**: When title changes, generates new slug and creates a redirect from old slug to new via `SlugRedirectService`
- **Mode without UUID**: Optionally generates clean slugs (`mi-evento`) with numeric suffix for collisions
- **Guaranteed uniqueness**: Increases UUID length if collision occurs

### DeletesCloudinaryImages

The `DeletesCloudinaryImages` trait automates image management:

- **On model deletion**: Deletes all associated images from Cloudinary
- **On image field update**: Deletes the previous image before saving the new one
- **Configuration**: The model defines `$cloudinaryImageFields` with the image fields

### Event-driven patterns (domain events)

Domain events decouple business logic from its side effects:

```
ModuleEnabled
    -> ClearCachesOnModuleChange (clears route, config, view caches)
    -> BuildModuleAssetsOnEnabled (builds module Vue components)
    -> ActivateMenuItemsOnModuleEnabled (reactivates the module's existing menu links)

MessageSending -> CheckQuotaBeforeSending (prevents sending if quota exceeded)
MessageSent -> LogSentEmail (records email in email_logs)
```

---

## 12. Security

### CSRF protection

- Laravel automatically generates and verifies CSRF tokens on all POST/PUT/DELETE requests
- Inertia's HTTP client sends the `XSRF-TOKEN` cookie as `X-XSRF-TOKEN`; requests sent by hand with `fetch` use `csrfHeaders()` from `utils/csrf.ts` (the `<meta name="csrf-token">` goes stale once the session is regenerated)
- CSRF middleware is never disabled

### XSS prevention

- **HTMLPurifier**: All rich HTML content (event descriptions, articles) is sanitized with the `richtext` configuration before sending to the frontend (`SanitizesHtml` trait)
- **Vue auto-escaping**: Vue automatically escapes `{{ }}` interpolations, preventing script injection
- **Content-Security-Policy**: CSP header that restricts script, style, image and connection sources

### Security headers (SecurityHeadersMiddleware)

```
X-Frame-Options: DENY                    -> Prevents clickjacking
X-Content-Type-Options: nosniff          -> Prevents MIME type sniffing
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(self)
Strict-Transport-Security: max-age=31536000; includeSubDomains  -> sent on HTTPS requests only
Content-Security-Policy:
    default-src 'self'
    script-src 'self' 'unsafe-inline' 'unsafe-eval'
    style-src 'self' 'unsafe-inline' https://fonts.bunny.net
    img-src 'self' https://res.cloudinary.com https://*.tile.openstreetmap.org data:
    font-src 'self' https://fonts.bunny.net data:
    connect-src 'self'
    frame-ancestors 'none'
    base-uri 'self'
    form-action 'self'
```

In the `local` environment the CSP also allows the Vite dev server (`http://localhost:5173`).

### Bot protection (BlockBotsMiddleware)

- Configurable list of blocked and allowed User-Agents
- Legitimate bots (Googlebot, Bingbot) are explicitly allowed
- Malicious bots (scrapers, aggressive crawlers) are blocked with 403 response
- Optional logging of blocks for analysis

### Rate limiting

- **Login**: 5 attempts per minute per IP + email combination
- **Contact**: 3 submissions per minute per IP

### Input validation

- All requests use `FormRequest` for server-side validation
- File type validation on image uploads
- Data sanitization before persistence
- Client-side validation is never trusted alone

### Secret management

- Environment variables in `.env` (never in version control)
- Different credentials per environment (dev/staging/prod)
- `APP_DEBUG=false` mandatory in production
- UUIDs as primary keys (not predictable auto-increment)
