# System architecture

> Generated: 2026-03-21 | Project: GuildForge v1.0.0

---

## 1. Executive summary

GuildForge is a web platform for wargames and role-playing games guilds. It combines a public frontend (events, articles, galleries, calendar) with an admin panel (Filament 3) for content management by guild members.

The architecture follows Clean Architecture principles with four concentric layers (Domain, Application, Infrastructure, Presentation), an extensible module system, and strict separation between reads (Query Services) and writes (Repositories). The main technology stack is Laravel 12+, Vue 3 with TypeScript, Inertia.js as the SSR bridge, PostgreSQL as the database, and Filament 3 for the admin panel.

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
| **S** - Single responsibility | Each class has one responsibility: `EventQueryService` only reads, `EventRepository` only persists |
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
        $this->validateDates(); // Invariante: endDate >= startDate
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

#### Value Objects

Immutable objects without identity that represent domain concepts:

| Value Object | Purpose |
|-------------|---------|
| `EntityId`, `EventId`, `ArticleId`, `GalleryId`, `PhotoId`, `UserId` | Typed identifiers (UUID) |
| `Slug` | Validated URL slug with pattern `[a-z0-9]+(-[a-z0-9]+)*` |
| `Price` | Price with validation |
| `HexColor`, `ColorPalette` | Visual theme colors |
| `DownloadLink` | Download link associated with events |
| `CoreTableRegistry` | Core table registry (module protection) |

#### Enums

- `PublicationStatus` - Content publication status
- `UserRole` - User roles (simple type with `label()`)
- `MenuLocation`, `MenuVisibility` - Menu location and visibility

#### Domain events

Events that notify significant state changes:

- **User**: `UserRegistered`, `UserLoggedIn`, `UserLoggedOut`, `UserPasswordChanged`, `UserProfileUpdated`
- **Modules**: `ModuleEnabled`, `ModuleDisabled`, `ModuleInstalled`, `ModuleUninstalled`, `ModuleUpdated`, `ModuleDiscovered`, `ModuleMigrated`

#### Domain exceptions

Specific exceptions that express business rule violations:

- `InvalidEventDatesException`, `CannotPublishPastEventException`
- `InvalidSlugException`, `InvalidPriceException`, `InvalidHexColorException`
- `ModuleNotFoundException`, `ModuleDependencyException`, `ModuleCircularDependencyException`
- `InvalidRoleNameException`, `RoleProtectedException`, `PermissionNotFoundException`
- `QuotaExceededException`, `InvalidMailConfigurationException`

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
- `UserServiceInterface` - User management
- `ContactServiceInterface` - Contact form
- `LegalPageServiceInterface` - Legal pages
- `SettingsServiceInterface` - Site configuration
- `ThemeSettingsServiceInterface` - Visual theme
- `DashboardWidgetConfigServiceInterface` - Dashboard widget configuration
- `ImageOptimizationServiceInterface` - Image optimization
- `SlugRedirectServiceInterface` - Slug redirects

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

**Authorization services** (`Application/Authorization/Services/`):
- `AuthorizationServiceInterface` - Permission verification
- `PermissionRegistryInterface` - In-memory permission registry
- `RoleServiceInterface` - Role management

#### Factory

- `ResponseDTOFactoryInterface` - Factory for converting Eloquent models to response DTOs

### 3.3. Infrastructure

**Location**: `src/app/Infrastructure/`

Concrete implementations that depend on frameworks, databases and external services.

#### Eloquent models

Located in `Infrastructure/Persistence/Eloquent/Models/`:

`UserModel`, `EventModel`, `ArticleModel`, `GalleryModel`, `PhotoModel`, `TagModel`, `HeroSlideModel`, `RoleModel`, `PermissionModel`, `SettingModel`, `SlugRedirectModel`, `ModuleModel`, `EmailLogModel`, `SesUsageRecordModel`, `MenuItemModel`

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

- **`HasSlug`** - Automatic slug generation with short UUID (`titulo-abc12345`). Supports mode with UUID (uniqueness guarantee) and without UUID (numeric suffix for collisions). Creates automatic redirects when slug changes.
- **`DeletesCloudinaryImages`** - Automatic Cloudinary image deletion when a model is deleted or updated. Configured with `$cloudinaryImageFields`.

#### External services

- **`CloudinaryStorageAdapter`** - Flysystem adapter for Cloudinary with image optimization before upload, direct URL generation without Admin API calls, and ignoring "not found" errors on deletion
- **`SnsMessageValidator`** - Validation of Amazon SES SNS notifications
- **`HttpLogContextProvider`** - Log context for HTTP requests

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

Authentication controllers in `Http/Controllers/Auth/`:
- Login, Register, Profile, ForgotPassword, ResetPassword, VerifyEmail

Controller pattern: Query Service injection, data query, Inertia response:

```php
final class EventController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly TagQueryServiceInterface $tagQuery,
    ) {}

    public function index(TagFilterRequest $request): Response
    {
        $events = $this->eventQuery->getPublishedEventsPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->eventQuery->getPublishedEventsTotal($tagSlugs);

        return Inertia::render('Events/Index', [
            'events' => $this->buildPaginatedResponse(/* ... */),
            'tags' => TagResource::collection($availableTags)->resolve(),
        ]);
    }
}
```

#### Middleware

- `HandleInertiaRequests` - Shared data with the frontend (auth, theme, navigation, modules, slots, translations)
- `SecurityHeadersMiddleware` - Security headers (CSP, HSTS, X-Frame-Options, etc.)
- `BlockBotsMiddleware` - Malicious bot blocking by User-Agent
- `EnsureLoginIsEnabled` - Verification that login is enabled
- `EnsureRegistrationIsEnabled` - Verification that registration is enabled

---

## 4. Module system

### General architecture

Modules are self-contained packages that extend core functionality without modifying it. Each module is an independent Git repository located in `src/modules/`.

```
src/modules/
  game-tables/               # Module example
    src/                     # PHP code (ServiceProvider, Controllers, Models, etc.)
    database/
      migrations/            # Module-specific migrations
    routes/
      web.php                # Web routes
      api.php                # API routes
    resources/
      views/                 # Blade views (Filament)
      js/                    # Vue components, pages, locales
    lang/                    # PHP translations
    config/
      module.php             # Module configuration
      settings.php           # Settings configuration
    tests/                   # Module tests
    module.json              # Module manifest
```

### ModuleLoader

`App\Modules\ModuleLoader` is responsible for booting enabled modules. Its flow:

1. Queries the repository for enabled modules (with optional cache)
2. For each module, registers an SPL autoloader for its namespace
3. Instantiates and registers the module's `ModuleServiceProvider`
4. Registers the module's hooks: slots, permissions, pages, routes, navigation

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
| `registerNavigation()` | `NavigationItemDTO[]` | Navigation items |
| `registerSlots()` | `SlotRegistrationDTO[]` | Vue components injectable into the layout |
| `registerPagePrefixes()` | `PagePrefixDTO[]` | Page prefixes for Inertia resolution |
| `registerRoutes()` | `ModuleRouteDTO[]` | Public routes for menus |
| `registerPolicies()` | `array<model, policy>` | Authorization policies |
| `registerNavigationGroups()` | `array<label, options>` | Filament navigation groups |
| `registerFilamentPages()` | `Page[]` | Filament pages |
| `getSettingsSchema()` | `Component[]` | Module settings form |
| `onEnable()` / `onDisable()` | `void` | Lifecycle callbacks |

### Module lifecycle

```
discover -> install -> enable -> boot -> (disable -> uninstall)
```

1. **Discover**: `php artisan module:discover` scans `src/modules/` and registers modules in the database
2. **Install**: The module is registered, its migrations and seeders are executed
3. **Enable**: `php artisan module:enable` activates the module. Fires `ModuleEnabled`, clears caches, builds Vue assets
4. **Boot**: `ModuleLoader::boot()` loads the ServiceProvider and registers all hooks
5. **Disable**: `php artisan module:disable` deactivates the module without deleting data. Fires `ModuleDisabled`
6. **Uninstall**: Removes the module completely. Fires `ModuleUninstalled`

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

**Management**: `module:list`, `module:discover`, `module:enable`, `module:disable`, `module:migrate`, `module:publish-assets`, `module:sync-from-image`

**Scaffolding**: `module:make` (complete module), `module:make-entity`, `module:make-controller`, `module:make-request`, `module:make-service`, `module:make-dto`, `module:make-migration`, `module:make-test`, `module:make-filament-resource`, `module:make-vue-page`, `module:make-vue-component`

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
- `ImageOptimizationServiceInterface`, `AuthServiceInterface`, `UserServiceInterface`
- `UserModelQueryServiceInterface`, `LegalPageServiceInterface`, `ContactServiceInterface`
- `SlugRedirectServiceInterface`, `ResponseDTOFactoryInterface`

**Mail system** (mix of `singleton` and `bind`):
- `MailConfigurationServiceInterface`, `MailTestServiceInterface`, `EmailQuotaServiceInterface`
- `MailStatisticsServiceInterface`, `SnsMessageValidatorInterface`

**Module system** (`singleton`):
- `ModuleManagerServiceInterface`, `ModuleContextServiceInterface`
- `ModulePermissionRegistryInterface`, `ModuleNavigationRegistryInterface`
- `ModuleSlotRegistryInterface`, `ModulePageRegistryInterface`, `ModuleRouteRegistryInterface`
- `ModuleScaffoldingServiceInterface`, `ModuleDiscoveryService`, `ModuleAssetBuilder`
- `ModuleDependencyResolver`, `ModuleMigrationRunner`, `ModuleSeederRunner`
- `CoreTableRegistry`, `ModuleSchemaGuard`, `ModuleLoader`

**Navigation** (`singleton`):
- `MenuServiceInterface`, `MenuItemHrefResolverInterface`, `RouteRegistryInterface`

**Update system** (`singleton`):
- `GitHubReleaseFetcherInterface`, `ModuleBackupServiceInterface`, `ModuleHealthCheckerInterface`
- `ModuleUpdateCheckerInterface`, `ModuleUpdaterInterface`
- `CoreVersionServiceInterface`, `CoreUpdateCheckerInterface`

### AuthorizationServiceProvider

- `RoleRepositoryInterface` -> `EloquentRoleRepository` (`bind`)
- `PermissionRepositoryInterface` -> `EloquentPermissionRepository` (`bind`)
- `AuthorizationServiceInterface` -> `AuthorizationService` (`singleton`)
- `RoleServiceInterface` -> `RoleService` (`singleton`)
- `PermissionRegistryInterface` -> `PermissionRegistry` (`singleton`)

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
            return null; // Fuerza re-autenticación
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
3. They are synchronized with the database via `php artisan permissions:sync`
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
| `settings` | `manage` | (admin only) |
| `mail` | `configure`, `view_stats` | (admin only) |
| `admin` | `access` | `editor` |

Modules extend this system with their own permissions using the format `module:resource.action`.

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
    SecurityHeadersMiddleware -> BlockBotsMiddleware -> HandleInertiaRequests
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
HandleInertiaRequests (shared data: auth, theme, navigation, slots)
    |
Inertia (JSON for SPA navigation / HTML for first load)
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
7. `Inertia::render('Events/Index', [...])` returns the response
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
| `ModuleSettingsPage` | Individual settings for each module |
| `ModuleUpdatesPage` | Checking and applying module updates |
| `CoreUpdatesPage` | Checking and applying core updates |

### Widget system

Dashboard widgets, configurable by the administrator via `DashboardWidgetConfigServiceInterface`:

**Core widgets**:
- `UpcomingEventsWidget` - Upcoming events counter (stats)
- `RecentArticlesWidget` - Recent articles (table with configurable limit)
- `GalleryStatsWidget` - Photo/gallery statistics (stats)
- `MailHealthWidget` - Mail system status (admin only)
- `MailStatsOverviewWidget` - Mail delivery statistics (admin only)

**Module widgets**: Registered via `registerFilamentWidgets()` in the module's ServiceProvider.

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

- **Vue 3** with Composition API and `<script setup>`
- **TypeScript** in strict mode (no `any`)
- **Inertia.js** as the bridge between Laravel and Vue (no separate REST API)
- **Pinia** for state management
- **Tailwind CSS** for styling
- **Vite 5** for build and HMR

### Layouts

Location: `src/resources/js/layouts/`

- `DefaultLayout.vue` - Main layout for the public site (header, footer, navigation, module slots)
- `AuthLayout.vue` - Layout for authentication pages

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
| `useSeo` | Dynamic meta tags for SEO |
| `usePagination` | Reusable pagination |
| `useAuth` | Authentication state |
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

- **Adapter**: `CloudinaryStorageAdapter` implements Laravel's Flysystem driver
- **Disk**: Configured as `images` in `filesystems.php`
- **Optimization**: `ImageOptimizationService` resizes and compresses images before upload
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

### Redis (cache/queues)

- **Cache**: Storage of enabled modules, theme configuration
- **Queues**: Asynchronous task processing like `BuildModuleAssetsOnEnabled`

---

## 11. Key patterns

### Query Services (reads) vs Repositories (writes)

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

The `HasSlug` trait manages URL slugs:

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
    -> ActivateMenuItemsOnModuleEnabled (reactivates module menu items)

MessageSending -> CheckQuotaBeforeSending (prevents sending if quota exceeded)
MessageSent -> LogSentEmail (records email in email_logs)
```

---

## 12. Security

### CSRF protection

- Laravel automatically generates and verifies CSRF tokens on all POST/PUT/DELETE requests
- Inertia.js includes the CSRF token in all its requests via `X-XSRF-TOKEN`
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
Strict-Transport-Security: max-age=31536000; includeSubDomains  -> HTTPS only
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
