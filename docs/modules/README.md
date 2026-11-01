# Module development guide

This guide covers how to create and develop modules for GuildForge using the Module SDK. Releasing a module is covered in [CI/CD for module distribution](../module-ci-cd.md).

Commands run in the development containers: `php artisan` and PHPUnit in `guildforge_app`, npm in `guildforge_node`. Both work in `src/`, so paths such as `modules/my-module` are relative to it (`src/modules/my-module` on the host).

## Table of contents

1. [Getting started](#getting-started)
2. [Module structure](#module-structure)
3. [Scaffolding commands](#scaffolding-commands)
4. [Service provider](#service-provider)
5. [Permissions & navigation](#permissions--navigation)
6. [Module settings](#module-settings)
   - [Panel code for core 3 (Filament 5)](#panel-code-for-core-3-filament-5)
7. [Filament integration](#filament-integration)
8. [Public pages and frontend translations](#public-pages-and-frontend-translations)
9. [Slot registration](#slot-registration)
10. [SEO of public pages](#seo-of-public-pages)
11. [Building module assets](#building-module-assets)
12. [Tailwind CSS in modules](#tailwind-css-in-modules)
13. [Module installation](#module-installation)
14. [Testing modules](#testing-modules)
15. [Best practices](#best-practices)
16. [Legal notices in emails](#legal-notices-in-emails)
17. [Extending core functionality](#extending-core-functionality)
18. [Domain events and listeners](#domain-events-and-listeners)
19. [State machine pattern](#state-machine-pattern)
20. [Releasing a module](#releasing-a-module)
21. [Quick reference](#quick-reference)

---

## Getting started

### Creating a new module

Use `module:make` to create a module with the standard directory structure:

```bash
docker exec guildforge_app php artisan module:make my-module --display-name="Mi módulo" --description="Gestión de partidas" --author="GuildForge" --repository=owner/guildforge-my-module --core=^3.28
```

The name must be kebab-case, must not exist yet under `modules/` and must not be a reserved name (`core`, `app`, `admin`, `system`, `modules`, `api`, `web`, `auth`, `user`, `filament`, `laravel`, `vendor`, `public`, `storage`). `--display-name` is the name shown in the panel, in Spanish (default: the name in sentence case); `--repository` (`owner/repo`) is where the updater looks for releases; `--core` is the core version the module requires (`^major.minor` or `^major.minor.patch`; default: the minor of the checkout you generate from, see [Module manifest](#module-manifest-modulejson)).

When the module's repository already exists (created on GitHub with its README, issue templates and roadmap workflow) and is cloned into `modules/my-module`, add `--into-existing`: the command fills the folder, never overwrites a file that is there and lists the ones it kept. A folder that already holds a `module.json` is refused.

The generators (`module:make` and every `module:make-*`) only write files, so they run without a database, for instance outside the containers.

The command creates `modules/my-module/` with:
- `module.json`, the service provider and `config/module.php`
- `routes/web.php` and `routes/api.php`
- `lang/es/messages.php` and `resources/js/locales/{es,en}.ts`
- `vite.config.ts` and `vitest.config.ts` (three lines each, on the core's `defineModuleConfig` and `defineModuleTestConfig`), `tsconfig.json` and `resources/css/module.css`, the same build setup the shipped modules use; no `package.json`, since modules build and test with the core's toolchain (see [Build configuration](#build-configuration))
- `phpunit.xml`, `.gitignore`, `.github/workflows/release.yml` and `.github/workflows/tests.yml` (PHPUnit and the quality checks on every pull request, see [module CI/CD](../module-ci-cd.md#pull-request-checks))
- `README.md` with the requirements, installation, tests, quality checks and releases
- `tests/Unit/PermissionSuggestionsTest.php`, which fails if a permission is suggested to the `editor` role (see [Registering permissions](#registering-permissions))
- `tests/js/setup.ts`, `tests/js/support/page.ts` and `resources/js/locales/index.ts`, so a Vitest spec can mount the module's pages (see [Running module tests](#running-module-tests))
- the directory structure for all layers

It ends by printing the next steps. Every `module:make-*` generator does the same: it lists what is left to wire by hand (bindings, routes, policies, widgets…).

### Discovering and enabling

After creating a module, discover and enable it:

```bash
# Register the module in the database
docker exec guildforge_app php artisan module:discover

# Enable it (runs its migrations and seeders the first time it is enabled)
docker exec guildforge_app php artisan module:enable my-module

# Run new migrations of an enabled module
docker exec guildforge_app php artisan module:migrate my-module

# Run the seeders that have not run yet
docker exec guildforge_app php artisan module:seed my-module
```

Enabling checks the module's requirements first (see [Module manifest](#module-manifest-modulejson)): an incompatible module cannot be enabled.

Enabling a module with Vue components also queues the build of its assets. A module installed from a release ZIP brings them already built, so nothing is built. The application and queue containers have no Node: in development, a module cloned or generated without a build is not built there. The log gets a warning, `module:enable` prints the command, and you build it in the Node container:

```bash
docker exec guildforge_node npm run build:module -- my-module
```

### Listing modules

```bash
docker exec guildforge_app php artisan module:list
```

---

## Module structure

> **Note:** Modules live in `base_path('modules')`, which `MODULES_PATH` can override (`config/modules.php`). In the Docker setup that is `src/modules/` on the host. Each module is its own Git repository.

Entries marked `*` are created by `module:make`; the rest come from the generators, the build or by hand.

```
modules/my-module/
├── module.json              * Module manifest
├── README.md                * Requirements, installation, tests, releases
├── vite.config.ts           * Build configuration (the core's defineModuleConfig)
├── vitest.config.ts         * Vitest configuration (the core's defineModuleTestConfig) for tests/js
├── package.json               Only for runtime libraries the core does not bring (dependencies only)
├── package-lock.json          With package.json, from the Node container's npm; commit it
├── tsconfig.json            * TypeScript configuration
├── phpunit.xml              * Test configuration
├── .gitignore               * Ignores node_modules, public/build, PHPUnit caches
├── .github/workflows/
│   └── release.yml          * Release workflow
├── config/
│   ├── module.php           * Module configuration
│   └── settings.php           Default values of the panel settings (optional)
├── database/
│   ├── migrations/          * Migrations
│   └── seeders/               Seeders (by hand)
├── lang/
│   └── es/
│       ├── messages.php     * PHP translations
│       ├── filament.php       Written by the Filament generators
│       └── emails.php         Written by module:make-notification
├── public/build/              Compiled assets (release build only, ignored by Git)
├── resources/
│   ├── css/module.css       * Tailwind utilities for the module's components
│   ├── js/
│   │   ├── locales/         * es.ts, en.ts (frontend translations)
│   │   ├── pages/           * Inertia pages (pages/MyModule/*.vue)
│   │   ├── components/      * Vue components (slots)
│   │   └── types/           * TypeScript types
│   └── views/               * Blade views
├── routes/
│   ├── web.php              *
│   └── api.php              *
├── src/
│   ├── MyModuleServiceProvider.php   *
│   ├── Domain/              * Entities/, ValueObjects/, Repositories/ (+ Enums/, Events/)
│   ├── Application/         * DTOs/, Services/
│   ├── Infrastructure/      * Listeners/, Persistence/Eloquent/{Models,Repositories}/, Services/
│   ├── Notifications/       *
│   ├── Http/                * Controllers/ (+ Api/), Requests/
│   ├── Filament/            * Resources/ (+ RelationManagers/, Widgets/, Pages/)
│   └── Policies/            *
└── tests/
    ├── Unit/                * (with PermissionSuggestionsTest.php)
    ├── Integration/         *
    ├── Feature/             *
    └── js/                  * setup.ts, support/page.ts and the Vitest specs
```

### Module manifest (module.json)

```json
{
    "name": "my-module",
    "displayName": "Mi módulo",
    "version": "1.0.0",
    "repository": "owner/guildforge-my-module",
    "namespace": "Modules\\MyModule",
    "provider": "MyModuleServiceProvider",
    "description": "Gestión de partidas",
    "author": "GuildForge",
    "requires": {
        "core": "^3.6",
        "php": ">=8.5",
        "laravel": ">=13.0",
        "filament": "^5.0",
        "modules": ["another-module:^1.0"],
        "extensions": ["json"]
    },
    "dependencies": ["another-module"],
    "storesUtc": true
}
```

- `name`, `version`, `namespace` and `provider` are required. `name` is the directory name. `namespace` must be `Modules\<StudlyName>` and `provider` `<StudlyName>ServiceProvider`: the panel and the autoloader derive both from the directory name.
- `displayName` is the name shown in the panel.
- `version` is the released version. `module:make` writes `0.0.0`: nothing is published yet, and the first release raises it (see [Releasing a module](#releasing-a-module)).
- `repository` (`owner/repo`) is where updates come from; `module:discover` copies it to the module's record. A module installed without it needs `php artisan module:set-source <name> <owner/repo>` once.
- `requires.modules` and `requires.extensions`, when present, must be lists of strings.
- `storesUtc` (boolean, default `false`) declares that the module stores instants correctly when the core stores UTC (see [Dates and timezones](#dates-and-timezones)). Once the site stores UTC, a module without it is incompatible and the panel lists the reason. `module:make` writes it.
- Other keys (some shipped modules carry a `provides` block) are ignored by the core.

`requires.core` is required: it is the range of GuildForge core versions the module supports, and sites refuse to load, enable, install or update a module whose requirements they do not meet. See [Compatibility requirements](../module-ci-cd.md#compatibility-requirements) for the syntax. The core version (`src/VERSION`) changes whenever the contract modules depend on changes: a minor version for compatible additions, a major one for breaking changes (core classes modules import, `BaseResource`, `ModuleServiceProvider`, or a major version of Filament, Livewire, Laravel or PHP). `module:make` writes the `^major.minor` of the checkout it runs in (`^3.6` on core 3.6.x), or the value of `--core`. The next steps say which one it used: a checkout behind the latest release gives an older minor than the contracts the module uses, so raise it to the core release that brought them, or lower it to the oldest core minor you tested against.

The core may also declare a minimum version for a module (`App\Domain\Modules\CoreModuleMinimumVersions`) when older releases of it would break on that core; releases below it are rejected the same way, pre-releases of the minimum included (`2.2.0-rc.1` is older than `2.2.0`). Extend core models from the module (`EventModel::resolveRelationUsing()` in the service provider), never by asking the core to reference module classes: the core must not import anything from `Modules\`.

### Dates and timezones

The core stores instants in the *storage zone* (`app.timezone`: `Europe/Madrid` or `UTC`, from the `storage_timezone` setting; `UTC` on fresh installs) and shows them in the *display zone* (`APP_DISPLAY_TIMEZONE`). A module must work with both:

- Module models that have instant columns use `App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone`: it converts any `DateTimeInterface` to the storage zone on write. `date` and `immutable_date` casts are calendar days and keep their day.
- Query bindings must be storage-zone instants (`TimezoneServiceInterface::toStorage()`).
- Inject `App\Application\Services\TimezoneServiceInterface` for everything else: `toDisplay()`, `fromDisplayWallTime()`, `toDisplayWallTime()`, `format()`, `today()`, `displayDayRange()` and `displayMonthRange()` for calendar days, and `iso()` to serialize an instant for the browser (always in the display zone). Never use `config('app.timezone')` to show or interpret a date.
- Filament pickers, columns and entries already use the display zone; frontend code reads it through `@/utils/datetime` (`VENUE_TIMEZONE`).
- Module CI stores in UTC (the default in `.env.example`). Tests extending `ModuleTestCase` can call `$this->useUtcStorage()` to pin UTC storage for a case whatever the environment says.

---

## Scaffolding commands

Every generator takes the module name and a class name, writes PHP that follows the module's namespace and kebab-case translation namespace (`my-module::`), merges the translation keys it uses into `lang/es/messages.php`, `filament.php` or `emails.php` (existing keys are kept) and prints the steps left. Labels derived from class names may stay in English: translate them in `lang/es`.

### Domain layer

```bash
# Entity, <Name>Id value object, repository interface, Eloquent model and repository
# (--migration also writes the create_<table>_table migration)
docker exec guildforge_app php artisan module:make-entity my-module Game --migration
```

Files: `src/Domain/Entities/Game.php`, `src/Domain/ValueObjects/GameId.php`, `src/Domain/Repositories/GameRepositoryInterface.php`, `src/Infrastructure/Persistence/Eloquent/Models/GameModel.php` and `src/Infrastructure/Persistence/Eloquent/Repositories/EloquentGameRepository.php`. Bind the repository in the provider's `register()`.

### Application layer

```bash
# DTO (src/Application/DTOs/GameDTO.php)
docker exec guildforge_app php artisan module:make-dto my-module Game

# Response DTO
docker exec guildforge_app php artisan module:make-dto my-module Game --response

# Service (src/Infrastructure/Services/GameService.php); --interface adds
# src/Application/Services/GameServiceInterface.php and implements it
docker exec guildforge_app php artisan module:make-service my-module Game --interface
```

### HTTP layer

Controllers go to `src/Http/Controllers/` (`Api/` for `--api`) and render Inertia pages named `MyModule/<Page>` (see [Public pages](#public-pages-and-frontend-translations)). The command prints the route to add to `routes/web.php` or `routes/api.php`.

```bash
# Controller with an index() page
docker exec guildforge_app php artisan module:make-controller my-module Game

# Resource controller
docker exec guildforge_app php artisan module:make-controller my-module Game --resource

# API controller
docker exec guildforge_app php artisan module:make-controller my-module Game --api

# Invokable controller
docker exec guildforge_app php artisan module:make-controller my-module ShowDashboard --invokable

# Form request (src/Http/Requests/GameRequest.php)
docker exec guildforge_app php artisan module:make-request my-module Game
```

### Filament

```bash
# Resource with List, Create and Edit pages (src/Filament/Resources/), plus its policy
# (src/Policies/GamePolicy.php) and its keys in lang/es/filament.php
docker exec guildforge_app php artisan module:make-filament-resource my-module Game

# RelationManager for a core resource (src/Filament/RelationManagers/)
docker exec guildforge_app php artisan module:make-relation-manager my-module Registrations --resource=Event

# Dashboard widget (src/Filament/Widgets/RegistrationStatsWidget.php)
docker exec guildforge_app php artisan module:make-widget my-module RegistrationStats
```

A generated policy stays inactive until `registerPolicies()` lists it, and a widget until `registerFilamentWidgets()` does (see [Filament integration](#filament-integration)).

### Domain events, enums and notifications

```bash
# Enum (src/Domain/Enums/)
docker exec guildforge_app php artisan module:make-enum my-module RegistrationStatus

# Domain event (src/Domain/Events/)
docker exec guildforge_app php artisan module:make-event my-module RegistrationCreated

# Event listener, synchronous by default (src/Infrastructure/Listeners/)
docker exec guildforge_app php artisan module:make-listener my-module SendRegistrationConfirmation --event=RegistrationCreated

# Queued (async) event listener
docker exec guildforge_app php artisan module:make-listener my-module ProcessHeavyTask --event=RegistrationCreated --queued

# Queued notification (src/Notifications/, ShouldQueue) and its keys in lang/es/emails.php;
# one whose links carry a secret also implements ShouldBeEncrypted
docker exec guildforge_app php artisan module:make-notification my-module RegistrationConfirmation
```

### Frontend

```bash
# Page (resources/js/pages/MyModule/GameList.vue) and its types (resources/js/types/GameList.ts)
docker exec guildforge_app php artisan module:make-vue-page my-module GameList

# Component (resources/js/components/GameCard.vue)
docker exec guildforge_app php artisan module:make-vue-component my-module GameCard
```

### Database

```bash
# Migration that creates a table
docker exec guildforge_app php artisan module:make-migration my-module create_games_table --create=my_module_games

# Migration that modifies a table
docker exec guildforge_app php artisan module:make-migration my-module add_slug_to_games_table --table=my_module_games
```

Module tables must start with the module name followed by `_`, with the hyphens either removed or replaced by underscores (`mymodule_` or `my_module_` for `my-module`). Before running a module's migrations and seeders, the core reads them and refuses:

- `Schema::create/table/drop/dropIfExists/rename` on a core table or on a table without the module's prefix, or with a table name held in a variable;
- `DB::statement()` with DDL on a core table, or with a query held in a variable;
- in seeders: any `Schema::` call, and `delete()`/`truncate()` (or raw `DELETE`/`TRUNCATE`) on a core table.

The same rules apply while the migrations run. Never add foreign keys from module tables to core tables: reference core rows by their UUID.

### Seeders

Create seeders by hand in `database/seeders/`. The core runs the `*Seeder.php` files there, in the namespace `<module namespace>\Database\Seeders`:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Models\CategoryModel;

final class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Estrategia', 'slug' => 'estrategia'],
            ['name' => 'Rol', 'slug' => 'rol'],
        ];

        foreach ($categories as $category) {
            CategoryModel::query()->updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['id' => Str::uuid()->toString()]),
            );
        }
    }
}
```

The first `module:enable` runs the migrations and then the seeders. After that, `module:seed` (or `module:enable --seed`) only runs the seeders that have not run yet; an update runs the seeders it adds.

```bash
docker exec guildforge_app php artisan module:seed my-module
```

### Testing

```bash
# Entity unit test, the default (tests/Unit/Domain/Entities/GameTest.php)
docker exec guildforge_app php artisan module:make-test my-module Game --unit

# DTO unit test (tests/Unit/Application/DTOs/)
docker exec guildforge_app php artisan module:make-test my-module Game --dto

# Feature test for a controller made without --resource or --invokable (tests/Feature/Http/Controllers/)
docker exec guildforge_app php artisan module:make-test my-module Game --feature

# Filament resource test (tests/Feature/Filament/)
docker exec guildforge_app php artisan module:make-test my-module Game --filament
```

See [Testing modules](#testing-modules) to run them.

---

## Service provider

The service provider is the entry point of the module. It extends `App\Modules\ModuleServiceProvider`:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Modules\ModuleServiceProvider;
use Modules\MyModule\Domain\Repositories\GameRepositoryInterface;
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Models\GameModel;
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Repositories\EloquentGameRepository;
use Modules\MyModule\Policies\GamePolicy;

final class MyModuleServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'my-module';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(GameRepositoryInterface::class, EloquentGameRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Livewire components, Filament extensions, event listeners…
    }

    public function registerPolicies(): array
    {
        return [
            GameModel::class => GamePolicy::class,
        ];
    }

    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'games.view_any',
                label: __('my-module::messages.permissions.games.view_any'),
                group: __('my-module::messages.title'),
                module: 'my-module',
            ),
            // games.view, games.create, games.update, games.delete…
        ];
    }
}
```

`moduleName()` is the only abstract method: it returns the kebab-case name, which sets the module path, the translation and view namespaces (`my-module::`) and the settings key.

`parent::register()`:
- loads `config/module.php` into `config('modules.my-module')`, also when the configuration is cached. Do not call `mergeConfigFrom()`: it does nothing under `config:cache`, so the values would be missing in production;
- publishes the module settings into `config('modules.settings.my-module')` (see [Module settings](#module-settings)).

`parent::boot()`:
- loads `routes/web.php` with the `web` middleware and `routes/api.php` with the `api` middleware under the `/api` prefix;
- registers `resources/views` and `lang` under the `my-module` namespace;
- registers `database/migrations`;
- registers the policies returned by `registerPolicies()`.

Register dynamic relationships on core models in `register()`, not `boot()` (see [Extending core functionality](#extending-core-functionality)).

The other hooks are read by the core when the module loads: `registerPermissions()`, `registerSlots()`, `registerPagePrefixes()`, `registerRoutes()`, `registerCalendarSources()` and `registerNavigation()`. The panel reads `registerNavigationGroups()`, `registerFilamentPages()`, `registerFilamentWidgets()` and `getSettingsSchema()`. The full list is in the [quick reference](#serviceprovider-methods).

`onEnable()` and `onDisable()` are declared on the base class, but the core never calls them: set up data in migrations and seeders, which run when the module is first enabled.

---

## Permissions & navigation

### Registering permissions

`registerPermissions()` returns `PermissionDTO`s. Each one is stored with the key `<module>:<name>`, and `name` is `<resource>.<action>`:

```php
public function registerPermissions(): array
{
    return [
        new PermissionDTO(
            name: 'games.view_any',                                   // key: my-module:games.view_any
            label: __('my-module::messages.permissions.games.view_any'),
            group: __('my-module::messages.title'),
            description: null,
            module: 'my-module',
            roles: ['organizacion-de-partidas'],               // suggested, never granted
        ),
    ];
}
```

- Policies check the full key: `$this->authorize($user, 'my-module:games.view_any')` with the `AuthorizesWithPermissions` trait. The policies made by `module:make-filament-resource` check `<module>:<plural>.view_any|view|create|update|delete`; declare those five names.
- Admins pass every check. Other roles need the permission, granted from the role editor in the panel. `roles` lists the roles the module suggests it for: from core 3.16 the role editor marks it *Sugerido para este rol* on those roles, but nobody gets it until an admin ticks it. Never suggest one to `editor`: since core 3.26 it is the core's content role, and module permissions go to the roles each association creates. The `PermissionSuggestionsTest` that `module:make` writes checks it.
- `php artisan permissions:sync` stores the definitions in the database (the production container runs it on every start). From the core release with #184 the core also syncs them when the module is enabled or updated, so a new permission shows up in the role editor right away.

### Registered users and association members

From core 3.8, two roles cover the usual audiences (see [Base role and association member role](../architecture.md#base-role-and-association-member-role)):

- **`member`** is held by every account: the core gives it on registration, in the panel, in `make:admin-user` and in the seeders, and `users:assign-member-role` gives it to older accounts. Grant `member` the permissions any signed-in user needs. Never assign or remove `member` from a module. From core 3.8.1 the core keeps it anyway: `AuthorizationServiceInterface::removeRole()` ignores `member`, `syncRoles()` keeps it for a user who holds it, and the role cannot be deleted or renamed in the panel.
- **The association member role** (`socio` by default) is chosen in the site settings. Ask the core whether a user is an association member, and never check a role name, a constant or a module setting of your own:

```php
use App\Application\Authorization\Services\AssociationMembershipServiceInterface;

public function __construct(
    private readonly AssociationMembershipServiceInterface $associationMembership,
) {}

// false when the configured role is missing or invalid: nobody is an association member then
$isMember = $this->associationMembership->isAssociationMember($user);
```

`roleName()` returns the configured role name (for queries such as "users with the role") and `null` when the setting holds a role that cannot be used; handle `null` as "nobody".

**Granting permissions to roles from a migration.** Use `RolePermissionGrantsInterface` (core ≥ the release with #200) and never write `role_permission` yourself: the grant then leaves an entry in the role history (`role_updated` with the permissions added, plus who and from where) and invalidates the authorization cache, like a change made in the role editor. It only adds (nothing a role holds is taken away, and only the permissions it did not have are logged), creates a permission row that does not exist yet from its definition (the row may be missing when the migration runs: when the module is enabled for the first time, on a ZIP update, and on cores before the release with #184) and leaves an existing row as it is. When no role gets the grant, nothing is written.

```php
use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;

$join = new PermissionDefinitionDTO(
    key: 'mymodule:games.join',
    label: $label,
    resource: 'games',
    action: 'join',
    module: 'mymodule',
);

// Every signed-in user: does nothing on a site without the role
app(RolePermissionGrantsInterface::class)->grantToRole('member', $join);

// A permission split in finer ones: every role holding the old one gets the new ones
app(RolePermissionGrantsInterface::class)->grantToRolesWith('mymodule:games.update', $host, $join);
```

`$label` is the permission's translated label with a literal fallback: a module migrated before it boots has no translations registered. Migrations run in file name order, core and module ones together: a migration granting to `member` goes after the core's `2026_10_14_100002_create_member_role` (core 3.8.0 creates the role there, so it exists once the core is migrated, also on a new install before the seeders run). Leave `down()` empty: roles may have been edited since. Migrations written before #200 (venue-bookings `cancel_own`, tournaments `report_results`/`manage_participants`) wrote the pivot directly and stay as they are, since they have already run.

**Email verification.** Module web routes load in the `web` group, so routes with the `auth` middleware get the core's required email verification (`EnsureEmailIsVerifiedWhenRequired`, see [Required email verification](../architecture.md#required-email-verification)): while the site requires it, accounts that must verify are redirected to `/verificar-email`, or get a `403` on JSON requests. Do not add Laravel's `verified` middleware: it ignores the site setting and the cutoff that exempts accounts created before 3.8.0.

Routes without `auth` are not checked. When such a route also works for guests but acts as the signed-in user when there is one (signing up or checking in, for example), add the `email.verified-if-required` alias: it applies the same rule when someone is signed in and does nothing for guests.

```php
// routes/web.php: guests can join too, so the route has no auth;
// a signed-in user joins as themselves, so the verification rule applies to them
Route::post('/partidas/{gameId}/apuntarse', [GameSignUpController::class, 'store'])
    ->middleware('email.verified-if-required')
    ->name('mymodule.games.join');
```

Elsewhere (a service, a policy, a Livewire component), `App\Application\Services\EmailVerificationPolicyInterface::mustVerify($user)` answers the same question.

A module that uses any of this declares `"core": "^3.8"` in `requires` (see [Module manifest](#module-manifest-modulejson)).

### Panel navigation

`registerNavigation()` returns `NavigationItemDTO`s that describe the module's entries in the admin panel sidebar (routes such as `filament.admin.resources.games.index`):

```php
public function registerNavigation(): array
{
    return [
        new NavigationItemDTO(
            label: __('my-module::messages.navigation.games'),
            route: 'filament.admin.resources.games.index',
            icon: 'heroicon-o-puzzle-piece',
            group: __('my-module::messages.navigation_group'),
            sort: 1,
            permissions: ['my-module:games.view_any'],
            module: 'my-module',
        ),
    ];
}
```

The core keeps these items in `ModuleNavigationRegistryInterface` and never copies them into the public menu. The sidebar itself is built by Filament from the module's resources and pages (their navigation group, label, icon and sort) and the groups from `registerNavigationGroups()`.

### Public menu links

Modules offer their public pages through `registerRoutes()`. Each `ModuleRouteDTO` appears in the page select of the menu editor (*Administración → Navegación*), where admins, or roles with the `menu_items.*` permissions, decide which pages go in the header or footer menu:

```php
use App\Application\Modules\DTOs\ModuleRouteDTO;

public function registerRoutes(): array
{
    return [
        new ModuleRouteDTO(
            routeName: 'my_module.games.index',
            label: __('my-module::messages.routes.games'),
            module: $this->moduleName(),
        ),
    ];
}
```

- While the module is not loaded (disabled or incompatible), links to its routes are left out of the public menu; they come back when it loads again.
- Menu items whose `module` column holds the module name are deactivated when it is disabled, reactivated when it is enabled and deleted when it is uninstalled. Links created in the menu editor carry no module and are kept.
- A link whose route is no longer available is shown in the editor as *Página no disponible: …*, so it can be deactivated or pointed at another page.

---

## Module settings

Modules can define settings that admins edit in the panel.

### Default values

`config/settings.php` holds the release defaults:

```php
// modules/my-module/config/settings.php
return [
    'api_key' => '',
    'max_items' => 10,
    'features' => [
        'feature_a' => true,
        'feature_b' => false,
    ],
];
```

Use plain values. The file is read on every boot, outside the configuration cache, and once the configuration is cached Laravel no longer loads `.env`, so `env()` there would only see variables of the process environment.

### Where saved settings live

The values saved from the panel are stored in the core's `settings` table under the key `module_settings:<name>` (for example `module_settings:my-module`), as JSON. `config/settings.php` is never written:

- `config('modules.settings.my-module')` holds the defaults with the saved values on top. Nested arrays whose keys are all strings are merged key by key; anything else (lists, arrays with numeric keys, scalars, `null`) replaces the default whole. Keys a new release adds to `config/settings.php` appear with their default value.
- Updates, image syncs and rollbacks replace the module directory but not the saved values, so settings survive them.
- Uninstalling the module with its data deleted removes the row; a plain uninstall keeps it, so a reinstall gets the settings back.
- Core 3.6.0 moved, once, the values that older cores had written into `config/settings.php` from the panel into the table.

### Settings form schema

Override `getSettingsSchema()` in the provider. The core's module settings page renders it, reached from *Administración → Módulos* (admins only):

```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

public function getSettingsSchema(): array
{
    return [
        Section::make(__('my-module::settings.api'))
            ->schema([
                TextInput::make('api_key')
                    ->label(__('my-module::settings.api_key'))
                    ->password()
                    ->required(),
                TextInput::make('max_items')
                    ->label(__('my-module::settings.max_items'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100),
            ]),
        Section::make(__('my-module::settings.features'))
            ->schema([
                Toggle::make('features.feature_a')
                    ->label(__('my-module::settings.feature_a')),
                Toggle::make('features.feature_b')
                    ->label(__('my-module::settings.feature_b')),
            ]),
    ];
}
```

A module without a settings schema has no generic settings page; it can still ship its own settings page (see below).

### Panel code for core 3 (Filament 5)

Core 3.0 runs Filament 5 on Livewire 4. A module that adds panel code declares `"core": "^3.0"` and `"filament": "^5.0"` in `module.json`; releases for core 2 (`"filament": "^3.3"`) are rejected by sites on core 3, and the reverse.

- **Namespaces.** Forms and infolists are schemas: `form(Schema $schema): Schema` and `infolist(Schema $schema): Schema` with `Filament\Schemas\Schema`. Layout components (`Section`, `Grid`, `Fieldset`, `Tabs`, `Group`, `Wizard`) live in `Filament\Schemas\Components`, and `Get`/`Set` in `Filament\Schemas\Components\Utilities`. Every action (row, bulk, header, form) is a `Filament\Actions\*` class; tables use `->recordActions()` and `->toolbarActions()`. `Filament\Support\Enums\Width` replaces `MaxWidth`.
- **Resources** extend `App\Filament\Resources\BaseResource` and type their static properties as Filament 5 does: `protected static string|\BackedEnum|null $navigationIcon`, `protected static string|\UnitEnum|null $navigationGroup`. `php artisan module:make-filament-resource` (and `module:make-relation-manager` and `module:make-widget`) generate this shape.
- **Registering panel code.** The panel only looks at enabled, compatible modules. Resources are discovered from `src/Filament/Resources/*Resource.php`; pages come from `registerFilamentPages()` (declared on `ModuleServiceProvider`, returns page classes); widgets come from a `registerFilamentWidgets()` method on the module's provider (not declared on `ModuleServiceProvider`: the panel calls it only when it exists; `module:make` adds it). There is no `registerFilamentResources()`.
- **Settings.** `getSettingsSchema()` returns schema components (`Filament\Schemas\Components\*` for layout, `Filament\Forms\Components\*` for fields), rendered by the core's module settings page. Enum-backed fields are stored as their value (`$enum->value`); inside the form, `$get()` returns the enum instance (compare with `=== MyEnum::Case`).
- **Settings pages of your own** that render the core's `filament.pages.simple-settings` view set `protected string $view` (no longer static) and define `form(Schema $schema): Schema` (state path `data`), `getFormActions(): array` and `save(): void`. The view renders `<form wire:submit="save">` with the form and `<x-filament::actions :actions="$this->getFormActions()" />`. Read the values with `ModuleManagerServiceInterface::getSettings()` and save them with `updateSettings()`, so they land in the same `module_settings:<name>` row as the generic page's.
- **Defaults kept from core 2.** The core sets them globally with `configureUsing()`, so the panel behaves as it did on core 2 and module code inherits them too: table filters apply on change (`deferFilters(false)`), no implicit key sort (`defaultKeySort(false)`), page sizes `5, 10, 25, 50, all`; `Section`, `Grid` and `Fieldset` span the full width; `FileUpload`, `ImageColumn` and `ImageEntry` use public visibility. Override them per component when you need otherwise.
- **No panel CSS.** The panel theme (`resources/css/filament/admin/theme.css`, built with Vite) only scans the core's `app/Filament` and `resources/views/filament`: module views and classes are not compiled into it, and modules cannot add a stylesheet to the panel. Style module panel code with Filament components and their props (`badge()`, `color()`, `icon()`, `->extraAttributes()` with existing classes), never with Tailwind classes of your own.
- **Rich text.** `RichEditor` is TipTap. HTML stored with the Trix editor (core 2) loses paragraphs and image captions on its first save: convert it in a migration of the release that moves to core 3, with `app(App\Application\Content\Services\TrixHtmlConverterInterface::class)->convert($html)` (idempotent: converted HTML comes back unchanged). Write the rows with `DB::table()` so `updated_at` and model events stay untouched, and keep a copy of the originals if you want to be able to restore them: the core's conversion (`content:convert-trix`) only covers core tables.
- **Livewire 4.** Its endpoints live under `/livewire-{hash}/`, with a hash that differs per site: never match `livewire/*` by path; use `app(\Livewire\LivewireManager::class)->getUriPrefix()`.
- **Tests.** `ModuleTestCase` enables the module through the same compatibility check as a site, so the module's real `module.json` must accept the core it runs against (`"core": "^3.0"`), or every test fails in `setUp()` with `ModuleIncompatibleException`.

### Accessing settings

```php
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;

$moduleManager = app(ModuleManagerServiceInterface::class);

// Defaults with the saved values on top
$settings = $moduleManager->getSettings(new ModuleName('my-module'));

// Save the whole form state (stored in the settings table, never in config/settings.php)
$moduleManager->updateSettings(new ModuleName('my-module'), [
    'api_key' => 'new-key',
    'max_items' => 20,
]);

// Read one value
$apiKey = config('modules.settings.my-module.api_key');
```

`updateSettings()` replaces the stored values with the array it receives, so pass the whole form state, not a single key.

---

## Filament integration

### Navigation groups

Modules can register Filament navigation groups:

```php
public function registerNavigationGroups(): array
{
    return [
        __('my-module::messages.navigation_group') => [
            'icon' => 'heroicon-o-puzzle-piece',
            'sort' => 20,
        ],
    ];
}
```

Groups are only added if the core does not define them already.

### Policy registration

Map the Eloquent models the resources use to their policies:

```php
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Models\GameModel;
use Modules\MyModule\Policies\GamePolicy;

public function registerPolicies(): array
{
    return [
        GameModel::class => GamePolicy::class,
    ];
}
```

### Auto-discovery

The panel collects Filament code from enabled, compatible modules:
- **Resources**: discovered from `modules/{name}/src/Filament/Resources/*Resource.php`
- **Pages**: the classes returned by `registerFilamentPages()`
- **Widgets**: the classes returned by `registerFilamentWidgets()`, an optional method on the module's provider (it is not declared on `ModuleServiceProvider`)

### Dashboard widgets

```php
use Modules\MyModule\Filament\Widgets\RegistrationStatsWidget;

public function registerFilamentWidgets(): array
{
    return [
        RegistrationStatsWidget::class,
    ];
}
```

Admins toggle and order dashboard widgets on the dashboard settings page. A table widget reads its row limit with `app(App\Application\Services\DashboardWidgetConfigServiceInterface::class)->getLimit(static::class, 5)`.

---

## Public pages and frontend translations

### Routes and controllers

`module:make` writes `routes/web.php` with the prefix `/my-module` and the route name prefix `my_module.`; `routes/api.php` answers under `/api/my-module/` with names prefixed `my_module.api.`. Public URLs are in Spanish. Put write routes behind the `auth` middleware and authorize in the controller (`Gate::authorize()`): a form request's `authorize()` does not call the policy.

Controllers render Inertia pages by name:

```php
return Inertia::render('MyModule/GameList', [
    'games' => $games,
]);
```

### Page prefixes

The first segment of the page name is the module's page prefix, which `registerPagePrefixes()` maps to the module (`module:make` registers `<StudlyName>`):

```php
use App\Application\Modules\DTOs\PagePrefixDTO;

public function registerPagePrefixes(): array
{
    return [
        new PagePrefixDTO(prefix: 'MyModule', module: $this->moduleName(), usesCoreLayout: true),
    ];
}
```

`MyModule/GameList` resolves to `resources/js/pages/MyModule/GameList.vue` (or `resources/js/pages/GameList.vue`). Module prefixes are checked before the core pages, so never use a prefix that matches a core page directory (`Events`, `Articles`, `Profile`…).

**Layout.** From core 3.16, with `usesCoreLayout: true` the core renders its `DefaultLayout` around the prefix's pages and keeps it across visits, also between core and module pages: header, footer and the module slots keep their state. Such pages declare no layout and never import `DefaultLayout` (it would show twice); `module:make` and `module:make-vue-page` generate them that way. A module that sets the flag must require `"core": "^3.16"` in `module.json`: older cores do not know the argument. Without the flag (the default, for modules released before 3.16) the core renders no layout around the pages, and each page wraps its own `<DefaultLayout>`; that copy is bundled into the module build, so it shows the header of the core version the module was built against and remounts on every visit.

### Public frontend API

A module's frontend may use, at runtime, only what the core publishes for modules: the list in `src/resources/js/public-api.json` (core ≥ 3.21). The page serves every specifier of the list through an import map, so all modules and the site share one instance of each, always the installed core's:

- **Shared packages**: `vue`, `pinia`, `vue-i18n`, `@inertiajs/vue3`, `@unhead/vue` and FullCalendar (`@fullcalendar/vue3` and its `daygrid`, `timegrid`, `interaction` and `themes/classic` plugins). FullCalendar's stylesheets come with the site's CSS.
- **Public entries** of `resources/js`: the form components (`@/components/form`), `ModuleSlot`, the base UI components (`BaseButton`, `BaseCard`, `ConfirmDialog`, `EmptyState`, `LoadingSpinner`), the composables `useAuth`, `useCalendarLocale`, `useFlashMessages`, `useGridLayout`, `useNotifications`, `usePagination`, `useRoutes` and `useVerificationGate`, the contract `@/contracts/cookieConsent` and the utilities `calendarTheme`, `cloudinary`, `csrf` and `datetime`. Their exact exports and types are in `public-api.exports.json` and `public-api.api.md`, next to the list.

Everything else under `@/` is private to the core: the module build stops on a runtime import of it, with `"@/…" is not part of the core's public frontend API`. Type imports (`import type { User } from '@/types'`) are fine anywhere: the compiler erases them.

**What a module needs and the core does not publish:**

- A private part of the core's frontend: ask for it in a pull request to the core, which adds it to `public-api.json` in a minor release; the module then requires that core in `requires.core`. A small, stateless piece can also be the module's own code.
- Something several modules should share: it goes to the core as public API, never into another module.
- A third-party library the core does not bring: declare it in the module's `package.json`, in `dependencies` only, with its `package-lock.json` (generated with the Node container's npm). The build bundles it from the module's `node_modules`; the production image and the release run `npm ci` in the module first. A library moves to the core's import map when it keeps state or must be a single instance on the page (Vue plugins, singletons, libraries that register globally, like FullCalendar), or when several modules load it whole on the same pages.
- `@lucide/vue` is a library the core provides for module builds: undeclared, it is bundled from the core's `node_modules`, and each module carries only the icons it uses.

The module's quality checks reject a `package.json` with `devDependencies`, `peerDependencies` or `optionalDependencies`, with build or test tooling (`vite`, `@vitejs/plugin-vue`, `@tailwindcss/vite`, `tailwindcss`, `vitest`, `@vue/test-utils`, `happy-dom`, `typescript`, `vue-tsc`, `vue`), a shared package or a package of its scope (`@fullcalendar/core`), a package of a scope bundled into the core's shared chunks (`@vue/*`, `@intlify/*`), `@lucide/vue`, or `dependencies` without `package-lock.json`.

**Who answers for what.** The core answers for its contract: its CI fails when a public entry loses an export (`npm run verify:build` against `public-api.exports.json`) or when the types of the API change without the report being regenerated (`npm run check:public-api` against `public-api.api.md`, which also carries the hand-written declarations of `resources/js/types` that module tsconfigs include, such as the Inertia `PageProps`; regenerated with `npm run public-api:report`), so every change to the API shows up in the core's pull request. A module answers for working with the core: its tests run on every pull request and weekly against the core's `main` (`tests.yml`, `schedule`), so a core change that breaks it shows up within a week even without new commits. The core never checks out modules: anyone can write one.

**Versioning.** The public API follows the core's semver: a new entry or export is a minor release; removing or changing one is breaking, and is first deprecated for at least one minor release (the old one keeps working and the guide says what replaces it).

#### Shared packages

The packages above stay out of every module bundle and come from the import map. A module never declares them.

Link core pages through `useRoutes()` (`@/composables/useRoutes`), never with hand-written URLs; for example a consent checkbox links `useRoutes().legal.privacy` (core ≥ 3.16).

### Frontend translations

Texts for pages and components live in `resources/js/locales/es.ts` and `en.ts`, under one top-level key (`module:make` writes `myModule: { title }`):

```typescript
export default {
    myModule: {
        title: 'Mi módulo',
        games: {
            empty: 'No hay partidas',
        },
    },
};
```

Use them as `$t('myModule.games.empty')`. They are merged into the core's messages when the core is built with the module present; for a module installed afterwards, the server parses the files on each request and sends them to the page. That parser only understands a plain object literal: no imports, variables, spreads or computed keys. vue-i18n gives `@`, `{`, `}` and `|` a meaning: write them as literal interpolations (`'info{\'@\'}example.org'`).

---

## Slot registration

Slots let modules put Vue components into predefined positions of the public site.

### Slot positions

| Slot | Where |
|------|-------|
| `before-header`, `after-header` | Around the site header (`DefaultLayout`) |
| `before-content`, `after-content` | Around the page content (`DefaultLayout`) |
| `before-footer`, `after-footer` | Around the site footer (`DefaultLayout`) |
| `event-detail-actions` | Event detail page |
| `game-table-registration`, `campaign-detail-actions` | Game table and campaign pages of the game-tables module |
| `profile-sections` | Tabs of the user profile (uses `profileTab`) |

The core layouts and pages already render these slots: modules only register components.

### SlotRegistrationDTO fields

| Field | Type | Description |
|-------|------|-------------|
| `slot` | string | Slot position (see above) |
| `component` | string | Path of the component relative to the module's `resources/js`, e.g. `components/UpcomingGames.vue` |
| `module` | string | Module name (kebab-case) |
| `order` | int | Sort order (lower = rendered first) |
| `props` | array | Static props passed to the component |
| `dataKeys` | array | Inertia page props passed to the component as props of the same name |
| `profileTab` | array\|null | Tab metadata for the `profile-sections` slot (`tabId`, `icon`, `labelKey`, `badgeKey`, `parentId`, `visibleKey`). `visibleKey` names a boolean page prop (listed in `dataKeys`) that the module shares per request: when it is `false` the tab is hidden and a `?tab=` pointing at it opens the account tab; a missing key or any other value keeps the tab visible |

### Registering slot components

```php
use App\Application\Modules\DTOs\SlotRegistrationDTO;

public function registerSlots(): array
{
    return [
        new SlotRegistrationDTO(
            slot: 'after-header',
            component: 'components/UpcomingGames.vue',
            module: $this->moduleName(),
            order: 10,
            props: ['limit' => 5],
            dataKeys: ['upcomingGames'],
        ),
    ];
}
```

### Rendering a slot in a module page

A module page can offer a slot of its own with the core's `ModuleSlot` component, as the game-tables pages do:

```vue
<script setup lang="ts">
import ModuleSlot from '@/components/layout/ModuleSlot.vue';
</script>

<template>
    <ModuleSlot name="game-table-registration" />
</template>
```

For custom rendering, `useModuleSlots()` returns `moduleSlots`, `getSlotComponents(slot)` and `hasSlotComponents(slot)`. Each item is already resolved: `{ key, component, props, registration }`, with the `dataKeys` merged into `props`.

```vue
<script setup lang="ts">
import { useModuleSlots } from '@/composables/useModuleSlots';

const { getSlotComponents, hasSlotComponents } = useModuleSlots();
</script>

<template>
    <aside v-if="hasSlotComponents('after-content')">
        <component
            :is="item.component"
            v-for="item in getSlotComponents('after-content')"
            :key="item.key"
            v-bind="item.props"
        />
    </aside>
</template>
```

New slot positions are added to the core (`SlotPosition` in `resources/js/types/slots.ts`).

---

## SEO of public pages

Link previews (WhatsApp, Facebook, Telegram, Discord) and search engines read the HTML the server renders; SSR is off, so client-side `useHead` is invisible to them.

Every public module page sends a `seo` prop built by the core factory (core ^3.1):

```php
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;

return Inertia::render('GameTables/Show', [
    'table' => $resource,
    'seo' => $this->seo->forPage(new SeoPageDTO(
        title: $table->title,
        description: $table->synopsis,          // HTML is stripped, cut to 160 chars
        imagePublicId: $table->imagePublicId,   // falls back to the site's own logo
        schedule: new SeoScheduleDTO($start, $end, $table->location), // adds date/place and Event JSON-LD
    ))->toArray(),
]);
```

- Private pages (my bookings, create, edit, check-in…) pass `noindex: true`.
- Pages do not call `useSeo()`; the app root applies the `seo` prop. Pages without one get the site defaults.
- The factory never throws; on failure it returns the site defaults.

---

## Building module assets

Modules with Vue pages or components ship them compiled. Assets are built in CI (or in the node container), never on the site: the PHP containers of the development and production images have no Node.

### Build configuration

`module:make` writes a `vite.config.ts` of three lines; the configuration itself is the core's (`src/vite.module.config.ts`):

```typescript
import { defineModuleConfig } from '../../vite.module.config.ts';

export default defineModuleConfig({ name: 'my-module', root: import.meta.dirname });
```

The path is the same in development (`src/modules/<name>`), in the production image and in the release workflow (`modules/<name>` inside the core). `defineModuleConfig` builds with the core's Vite and plugins, and holds what the core and the release workflow rely on:

- **Entries**: every `.vue` file under `resources/js/components` and `resources/js/pages`, keyed by its path (`resources/js/components/GameCard.vue`), plus `resources/css/module.css` as the `module-styles` entry when it exists.
- **Manifest**: `manifest.json`, at the root of the output directory (not `.vite/manifest.json`): the site looks for `/build/modules/<name>/manifest.json`.
- **Output**: the core's `public/build/modules/<name>/` with `base: '/build/modules/<name>/'`; with `MODULE_STANDALONE_OUTPUT=true`, the module's own `public/build/` with `base: '/'`, as the release workflow builds (it fails without `public/build/manifest.json`).
- **Externals**: every specifier of the [public frontend API](#public-frontend-api), served by the import map.
- **Guard**: the build stops on a runtime import of a private part of the core (through `@/` or a relative path into the core's `resources/js`), on a subpath of a shared package the core does not share (`@fullcalendar/vue3/locales/es`) and on a package the module does not declare in `dependencies`.

`tsconfig.json` type-checks the module against the core: `@/*` points to the host's `resources/js`, the import map packages resolve to the host's `node_modules` (their types are the host's copies, so `$t` and the Inertia page props are typed) and the host's `resources/js/types` declarations are included. Run it with `docker exec guildforge_node sh -c 'cd modules/my-module && ../../node_modules/.bin/vue-tsc --noEmit -p tsconfig.json'`; the quality checks run it too.

Modules released before core 3.21 brought their own `package.json` with Vite and their own `vite.config.ts`. Core 3.25 no longer runs a module's own Vite: a `vite.config.ts` without `defineModuleConfig` fails `module:build`, the quality checks, the release workflow and the production image. Assets already inside an installed module's ZIP keep loading. Move a module to `defineModuleConfig` by replacing its `vite.config.ts` (and `vitest.config.ts`, see [Running module tests](#running-module-tests)) with the three-line versions, deleting `package.json` and `package-lock.json` (or keeping only `dependencies`) and requiring the core that ships `defineModuleConfig` in `module.json`.

### Building in development

```bash
docker exec guildforge_node npm run build:module -- my-module
```

The assets land in `public/build/modules/my-module/`. With the Vite dev server running (`make up` starts it), the site loads module pages and slot components from their sources with hot reload, so a build is only needed to try the built output. A module with runtime libraries of its own needs `npm ci` in its directory first.

To reproduce the release output (the module's own `public/build/`):

```bash
docker exec -e MODULE_STANDALONE_OUTPUT=true guildforge_node npm run build:module -- my-module
```

`make module-zip` builds that output and packages the module as the release workflow does (see [Releasing a module](#releasing-a-module)).

`php artisan module:build` and the listener that builds a module when it is enabled run the build (`scripts/build-module.sh`) inside the PHP process. Neither PHP image has Node, so in both they only log a failure.

### How assets reach a site

The release ZIP carries the compiled assets in `public/build/`. Installing the ZIP, updating the module and `php artisan module:publish-build-assets` (run on every start of the production container) copy them to `public/build/modules/<name>/`:

```
public/build/modules/my-module/
├── manifest.json        # Maps source paths to built files
└── assets/
    ├── GameCard-[hash].js
    ├── module-styles-[hash].css
    └── …
```

### Loading flow

1. A page or slot names a module component (`components/GameCard.vue`) or page (`MyModule/GameList`).
2. On a built site, the browser fetches `/build/modules/<module>/manifest.json`, finds `resources/js/<path>` in it and imports the built file; its imports of the public frontend API resolve through the import map to the site's own chunks. Under the dev server, slot components and pages come from the sources instead.
3. The module's CSS (the `module-styles` entry and any CSS attached to the loaded chunk) is added to the document once, inside the `module-utilities` cascade layer. Under the dev server it is `resources/css/module.css` compiled from the sources instead (see [Loading the stylesheet](#loading-the-stylesheet)).
4. The component renders with the props of its registration.

---

## Tailwind CSS in modules

This section is about the public site: panel code cannot bring its own CSS (see [Panel code for core 3](#panel-code-for-core-3-filament-5)).

The core stylesheet never sees module sources: `src/modules/*` is ignored by Git, so Tailwind skips it, and modules are absent when CI builds the core. A class a module uses only exists if the core's own sources or its safelist already produce it, or if the module compiles it. This holds under the dev server too: it attaches the module stylesheet compiled from the sources (see [Loading the stylesheet](#loading-the-stylesheet)), so a class missing there is missing on a built site as well.

### Module stylesheet

Every module with a frontend ships `resources/css/module.css` (written by `module:make`) as the `module-styles` build entry:

```css
@import 'tailwindcss/theme' theme(reference);
@import 'tailwindcss/utilities';

@custom-variant dark (&:where(.dark, .dark *));

@theme reference {
    /* Mirror of the core's colour tokens (page, surface, primary-*, accent-*,
       neutral-*, success, error…), each pointing at the variable the core defines */
    --color-surface: var(--color-bg-surface);
    /* … */
}

@source '../js/**/*.vue';
@source '../js/**/*.ts';
```

- `theme(reference)` and `@theme reference` reference the core's palette without re-emitting it, so the file only holds the utilities the module's own sources use, pointing at the core's variables.
- `@custom-variant dark` makes `dark:` follow the core's `.dark` class.
- Do not `@import 'tailwindcss'`: it would emit preflight and the default theme again.
- Keep the token list in sync with the core's `resources/css/app.css` (the generated file mirrors it).

### Loading the stylesheet

The site adds the stylesheet once, when it loads a page or slot component of that module, and always inside the `module-utilities` cascade layer: above the core's `base` (a module's `text-2xl` beats the `h1` reset) and below the core's `utilities` (the core wins a tie). A `<link>` cannot carry a layer, so `attachModuleStylesheet()` (`resources/js/utils/moduleStyles.ts`) adds a `<style>` with `@import url(…) layer(module-utilities)`.

| | Where the stylesheet comes from |
|---|---|
| Built site | The module's build: the `module-styles` entry of `/build/modules/<module>/manifest.json`, plus any CSS attached to the loaded chunk (see [Loading flow](#loading-flow)) |
| Dev server | `resources/css/module.css` compiled by the core's dev server from the module's sources (`http://localhost:5173/modules/<module>/resources/css/module.css`), for the pages and components it loads from those sources. A module without sources (installed from a ZIP) falls back to its build |

Under the dev server the stylesheet follows the sources: a change to a module's `.vue` or `.ts` makes Tailwind rescan them, and the core imports the sheet again without reloading the page; a change to `module.css` itself reloads the page. The test suite attaches no module stylesheet.

### Core safelist

The core also safelists common utilities and the semantic design tokens, at a cost of +15-25KB gzipped CSS shared by all modules. These classes exist without the module stylesheet, including in dynamic bindings.

**Available utility categories:**

| Category | Examples |
|----------|----------|
| Layout | `flex`, `grid`, `block`, `hidden`, `inline-flex`, `items-*`, `justify-*`, `flex-col`, `grid-cols-*` |
| Spacing | `p-*`, `m-*`, `px-*`, `py-*`, `gap-*`, `space-x-*`, `space-y-*` (scale 0-16) |
| Sizing | `w-*`, `h-*`, `min-w-*`, `max-w-*`, `size-*`, fractions (`w-1/2`, `w-1/3`, etc.) |
| Typography | `text-{xs,sm,base,lg,xl,2xl,3xl}`, `font-*`, `leading-*`, `truncate`, `line-clamp-*` |
| Borders | `rounded-*`, `border-*`, `divide-*` |
| Effects | `shadow-*`, `opacity-*`, `blur-*`, `backdrop-blur-*` |
| Transitions | `transition-*`, `duration-*`, `ease-*`, `animate-*` |
| Transform | `scale-*`, `rotate-*`, `translate-*`, `skew-*` |
| Position | `absolute`, `relative`, `fixed`, `sticky`, `z-*`, `inset-*`, `top/right/bottom/left-*` |
| Cursor | `cursor-pointer`, `cursor-default`, `cursor-not-allowed`, `cursor-grab` |
| Overflow | `overflow-*`, `overscroll-*` |

**Semantic design system classes:**

```css
/* Backgrounds */
bg-page, bg-surface, bg-elevated, bg-muted, bg-primary, bg-primary-light

/* Text */
text-base-primary, text-base-secondary, text-base-muted, text-base-inverted, text-primary

/* Borders */
border-default, border-strong, border-primary

/* Status colors */
bg-success, bg-error, bg-warning, bg-info
text-success, text-error, text-warning, text-info
border-success, border-error, border-warning, border-info
```

**Responsive prefixes** (`sm:`, `md:`, `lg:`, `xl:`) are available for layout, sizing, typography, and spacing utilities.

**State variants:**

| Variant | Available modifiers |
|---------|---------------------|
| `hover:` | opacity, scale, underline, shadow |
| `focus:` | outline, ring |
| `active:` | scale, opacity |
| `disabled:` | opacity, cursor, pointer-events |
| `group-hover:` | opacity, visibility, scale, translate |
| `dark:` | display, opacity, border |

**Example usage in Vue component:**

```vue
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    columns: number;
    gap: number;
    variant: 'primary' | 'secondary';
}>();

// Dynamic class binding using safelisted utilities
const gridClasses = computed(() => [
    `grid-cols-${props.columns}`,
    `gap-${props.gap}`,
    props.variant === 'primary' ? 'bg-primary-light' : 'bg-surface',
]);
</script>

<template>
    <div :class="['grid', ...gridClasses]">
        <slot />
    </div>
</template>
```

Anything outside the safelist (arbitrary values, uncommon utilities, variants not listed above) relies on the module stylesheet.

---

## Module installation

### Installing from the admin panel

In *Administración → Módulos* (admins only), with maintenance mode on, use *Instalar / actualizar* to upload the release ZIP. A ZIP of a module that is already installed updates it. Updates from GitHub releases are covered in [Updating installed modules](../module-ci-cd.md#updating-installed-modules).

### Programmatic installation

```php
use App\Application\Modules\Services\ModuleInstallerInterface;

$moduleInstaller = app(ModuleInstallerInterface::class);
$manifest = $moduleInstaller->installFromZip($uploadedFile);

// $manifest is the parsed module.json
echo "Installed: {$manifest->name} v{$manifest->version}";
```

### Uninstallation

```php
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;

$moduleManager = app(ModuleManagerServiceInterface::class);
$moduleManager->uninstall(new ModuleName('my-module'), deleteData: false);
```

Steps:
1. Fail if an enabled module depends on it.
2. Disable it.
3. Only with `deleteData: true`: roll back its migrations, forget which seeders ran and delete its saved settings.
4. Delete the module directory.
5. Delete its database record.
6. Dispatch `ModuleUninstalled`; the menu items that carry the module's name are deleted.

### Module events

| Event | Fields | Dispatched |
|-------|--------|------------|
| `ModuleInstalled` | moduleName, moduleVersion, modulePath | After a ZIP installation |
| `ModuleUpdated` | moduleName, previousVersion, newVersion, modulePath | After an update from a ZIP (updates from GitHub releases do not dispatch it) |
| `ModuleEnabled` | moduleId, moduleName | After enabling |
| `ModuleDisabled` | moduleId, moduleName | After disabling |
| `ModuleUninstalled` | moduleName, moduleVersion | After uninstalling |

All live in `App\Domain\Modules\Events`.

---

## Testing modules

### Running module tests

Module tests run inside the host application with the module's own `phpunit.xml`; `php artisan test` only runs the host's suites and never the modules'.

```bash
docker exec -e XDEBUG_MODE=off guildforge_app vendor/bin/phpunit -c modules/my-module/phpunit.xml
```

The core's test bootstrap stops the run when Xdebug is in `develop` mode (the container's default): there, Xdebug keeps the local variables of recent exceptions, and a Filament action test can pass without running the action, depending on the tests that ran before. CI does not load Xdebug.

`module:make` writes `phpunit.xml` as the shipped modules have it: it boots `tests/bootstrap.php`, runs the `tests/Unit`, `tests/Integration` and `tests/Feature` suites on SQLite in memory, and its `APP_CONFIG_CACHE`/`APP_ROUTES_CACHE`/`APP_EVENTS_CACHE` block keeps tests away from the bootstrap caches of the container. Keep that block. The release workflow runs the same file (see [Releasing a module](#releasing-a-module)), but on PostgreSQL, as the sites do: SQLite stores dates as text and takes any value in a UUID column, so some failures only show there. Before publishing changes that touch queries or migrations, run the suite against PostgreSQL too; real variables take precedence over the ones in `phpunit.xml`:

```bash
docker exec -e XDEBUG_MODE=off -e DB_CONNECTION=pgsql -e DB_HOST=guildforge_db -e DB_DATABASE=my_module_test \
    guildforge_app php artisan test --parallel --processes=4 --configuration=modules/my-module/phpunit.xml
```

Laravel creates one database per process (`my_module_test_test_1`…); drop them when done. Migrations must still work on both databases.

`tests/bootstrap.php` loads the core's test bootstrap (which clears a stale config cache and the compiled views before the suite) and then maps the module's test namespace to `tests/`:

```php
require __DIR__.'/../../../tests/bootstrap.php';

$loader = require __DIR__.'/../../../vendor/autoload.php';
$loader->addPsr4('Modules\\MyModule\\Tests\\', __DIR__);
```

The module autoloader only maps `src/`. PHPUnit loads the `*Test.php` files by path, but shared helpers (fakes, traits, builders) are only found through that mapping: put them under `tests/Support` with the `Modules\MyModule\Tests\Support` namespace. Modules created before this file existed boot `../../vendor/autoload.php` or their own `tests/bootstrap.php`; copy the one above (with the module's namespace) and point `bootstrap` in `phpunit.xml` at it the next time the module is touched.

Vue tests (`tests/js/**/*.spec.ts`) run with the core's Vitest, Vue Test Utils and happy-dom. The `vitest.config.ts` that `module:make` writes is three lines on the core's configuration (`src/vitest.module.config.ts`: the `@` alias to the core's `resources/js`, one Vue copy, `tests/js/setup.ts` when it exists):

```typescript
import { defineModuleTestConfig } from '../../vitest.module.config.ts';

export default defineModuleTestConfig({ root: import.meta.dirname });
```

```bash
docker exec guildforge_node sh -c 'cd modules/my-module && ../../node_modules/.bin/vitest run'
```

`module:make` also writes `tests/js/setup.ts`: it loads the core's texts with the module's on top (`resources/js/locales/index.ts`), mocks Inertia (`usePage()` returns the page in `tests/js/support/page.ts`, whose `signIn()` adds a user; the router and `Link` are spies and anchors) and renders only the slot of the site layout. `module:make-vue-page` writes a spec next to each page (`tests/js/<Page>.spec.ts`) that mounts it and checks its title.

The quality checks run it too once `tests/js` has a spec (`make check MODULE=my-module`), after building the module into a scratch directory, so a private import of the core fails in the module's pull request.

### Using ModuleTestCase

Feature tests extend `Tests\Support\Modules\ModuleTestCase`. With `$autoEnableModule`, every test discovers the modules and enables this one (through the same compatibility check as a site) and boots its provider:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Tests\Feature\Http\Controllers;

use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Modules\ModuleTestCase;

final class GameControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'my-module';

    protected bool $autoEnableModule = true;

    #[Test]
    public function it_lists_the_games(): void
    {
        $this->get(route('my_module.games.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('MyModule/GameList', false));
    }
}
```

- Since core 3.6.0, `enableModule()` refreshes the router's name lookups, so `route()` finds the module's routes. Against an older core, request the URL (`$this->get('/my-module/games')`).
- `enableModule()` also drops the translations loaded before the module booted. The first test of a PHPUnit process runs `migrate:fresh`, and a module migration that calls `__()` on the module's own keys used to leave that group cached empty, so the first test saw raw keys. On core 3.10.1 or older, call `app('translator')->setLoaded([])` after enabling the module.
- Pass `false` as the second argument of `component()`: module pages are not in the host's pages directory.
- The module's `module.json` must accept the core the tests run on, or `setUp()` fails with `ModuleIncompatibleException`.

### Testing panel code

The panel is built before a test enables the module, so its resources and pages are not registered. `Tests\Support\Modules\InteractsWithModulePanel` puts them on the panel:

```php
use Livewire\Livewire;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class GameResourceTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'my-module';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameResource::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_list_page_renders(): void
    {
        Livewire::test(ListGames::class)->assertOk();
    }
}
```

`module:make-test my-module Game --filament` generates a test of this shape.

---

## Best practices

### 1. Follow DDD architecture

- Keep domain logic in `Domain/`
- Use DTOs for data transfer
- Implement repository interfaces

### 2. Use translations

```php
// In PHP: the kebab-case module namespace
__('my-module::messages.created')
```

```vue
<!-- In Vue: the top-level key of resources/js/locales/es.ts -->
{{ $t('myModule.messages.created') }}
```

### 3. Prefix database tables

Name tables `my_module_*` (or `mymodule_*`): migrations that touch any other table are refused (see [Database](#database)).

```php
Schema::create('my_module_games', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    // …
});
```

### 4. Register Filament resources

Keep resources in `src/Filament/Resources/` with the `Resource` suffix so the panel discovers them, and register pages and widgets from the service provider (see [Auto-discovery](#auto-discovery)).

### 5. Handle module dependencies

Declare dependencies in `module.json`:

```json
{
    "requires": {
        "modules": ["base-module:^1.0"]
    },
    "dependencies": ["base-module"]
}
```

### 6. Read configuration and translations directly

```php
// config/module.php, loaded by parent::register()
config('modules.my-module.setting_key');

// Panel settings (defaults with the saved values on top)
config('modules.settings.my-module.max_items');

// Translations
__('my-module::messages.success');
```

The helpers in `app/Modules/helpers.php`:

| Helper | What it does |
|--------|--------------|
| `module_path('my-module', 'config')` | Absolute path inside the module |
| `module_enabled('my-module')` | Whether the module is enabled |
| `module_route('my-module', 'games.index')` | URL of the route `my_module.games.index` (snake_case module prefix, as `module:make` names routes) |
| `module_asset('my-module', 'img/logo.png')` | URL of `public/modules/my-module/img/logo.png`, which `module:publish-assets` copies from the module's `resources/assets` |
| `module_config()`, `module_trans()` | Read `config('modules.<name>.<key>')` and the `<name>::` translation namespace the base provider registers, falling back to the snake_case `<snake_name>` key and namespace for modules written against earlier versions. `config()` and `__()` as above do the same without the helper |
| `module()` | Without an argument, the module context service; `module('my-module')` makes that module the current one. `App\Modules\Facades\Module` is the same service (`Module::config()` behaves like `module_config()`) |

### 7. Use domain events for side effects

Don't put email sending or cascading updates in services. Use events and listeners instead:

```php
// Bad: side effects in the service
public function createRegistration(CreateRegistrationDTO $dto): Registration
{
    $registration = $this->repository->save($registration);

    // Couples registration logic to email
    Mail::to($user)->send(new RegistrationConfirmation($registration));

    return $registration;
}

// Good: dispatch an event, let listeners handle side effects
public function createRegistration(CreateRegistrationDTO $dto): Registration
{
    $registration = $this->repository->save($registration);

    RegistrationCreated::dispatch(
        $registration->getId()->toString(),
        $registration->getEventId()->toString(),
        $registration->getUserId()->toString()
    );

    return $registration;
}
```

### 8. Validate state transitions in entities

Don't allow invalid state changes. Use enums with `canTransitionTo()`:

```php
// Bad: no validation
public function cancel(): void
{
    $this->status = RegistrationStatus::Cancelled; // What if already attended?
}

// Good: validate the transition
public function cancel(): void
{
    if (! $this->status->canTransitionTo(RegistrationStatus::Cancelled)) {
        throw InvalidStateTransitionException::fromTo(
            $this->status->value,
            RegistrationStatus::Cancelled->value,
            'Cannot cancel registration in current state'
        );
    }

    $this->status = RegistrationStatus::Cancelled;
}
```

### 9. Register Livewire components for RelationManagers

Without this, RelationManagers don't work:

```php
private function registerLivewireComponents(): void
{
    if (! class_exists(Livewire::class)) {
        return;
    }

    Livewire::component(
        'modules.my-module.filament.relation-managers.registrations-relation-manager',
        RegistrationsRelationManager::class
    );
}
```

### 10. Use `register()` for model extensions

Dynamic relationships must be registered before Filament boots:

```php
// Bad: in boot(), too late, Filament is already initialized
public function boot(): void
{
    EventModel::resolveRelationUsing('registrations', function ($model) {
        return $model->hasMany(RegistrationModel::class);
    });
}

// Good: in register(), before Filament boots
public function register(): void
{
    parent::register();

    EventModel::resolveRelationUsing('registrations', function ($model) {
        return $model->hasMany(RegistrationModel::class, 'event_id', 'id');
    });
}
```

---

## Legal notices in emails

A module that puts a legal notice (data protection text, for example) in an email can let the administrators edit it from *Páginas legales → Avisos en correos* (core ^3.14). Register the notice while booting, only when the core offers the registry, so the module keeps working with an older core:

```php
use App\Application\Legal\DTOs\LegalMailNoticeDTO;
use App\Application\Legal\Services\LegalMailNoticeRegistryInterface;
use Illuminate\Notifications\AnonymousNotifiable;
use Modules\MyModule\Notifications\InvitationNotification;

if (interface_exists(LegalMailNoticeRegistryInterface::class)) {
    $this->app->make(LegalMailNoticeRegistryInterface::class)->register(new LegalMailNoticeDTO(
        key: 'my-module.invitation',            // unique; the text is saved as `legal_mail_notice:<key>`
        module: 'my-module',
        label: __('my-module::admin.notice.label'),
        help: __('my-module::admin.notice.help'),
        defaultContent: "First paragraph.\n\nSecond paragraph, with :site.",
        placeholders: [':site', ':inviter'],    // listed under the field
        // The whole email as HTML: the module's own notification with sample data and the form's text
        preview: fn (string $content): string => (string) (new InvitationNotification(
            inviterName: 'John Doe',
            eventTitle: 'Sample event',
            legalText: $content,
        ))->toMail(new AnonymousNotifiable)->render(),
    ));
}
```

The notification takes the text to show as an optional argument (`legalText`, `null` when it is really sent):

```php
use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

public function toMail(object $notifiable): MailMessage
{
    $message = (new MailMessage)->line(__('my-module::emails.invitation.intro', ['inviter' => $this->inviterName]));
    $replacements = [':site' => (string) config('app.name'), ':inviter' => $this->inviterName];

    // The saved text (or the default one) when sent; the form's text in the preview
    $paragraphs = $this->legalText === null
        ? app(LegalMailNoticeServiceInterface::class)->paragraphs('my-module.invitation', $replacements)
        : array_map(fn (string $paragraph): string => strtr(trim($paragraph), $replacements), preg_split('/\n\s*\n/', $this->legalText) ?: []);

    // Escaped, on one line and wrapped in <p>: the Markdown mail template never formats it
    foreach ($paragraphs as $paragraph) {
        $message->line(new HtmlString('<p>'.str_replace(["\r\n", "\r", "\n"], ' ', e($paragraph)).'</p>'));
    }

    return $message;
}
```

- The text is plain text: blank lines separate paragraphs. Empty means the default text: the field shows the default while nothing is saved, saving it unchanged stores an empty value (so the site follows later changes of the default) and *Restaurar texto por defecto* puts it back in the field.
- When building the email, call `LegalMailNoticeServiceInterface::paragraphs($key, [':site' => $site, …])` and escape each paragraph when rendering it.
- `LegalMailNoticeServiceInterface::entity()` returns the data controller (`name`, `email`, `dpoEmail`) from the *Datos de la asociación* tab; the name falls back to the site name.
- The preview shows the whole email with the text in the form, even if it is not saved yet. Use sample data, never real people.

---

## Cookies set by a module

A module that sets its own cookie declares it in the core's registry (core ^3.18), so a cookie consent module can list it in the banner and the cookie policy. Declaring needs nothing else installed: without a cookie consent module nobody reads the list. Never import another module's classes for this.

```php
use App\Application\Cookies\DTOs\DeclaredCookieDTO;
use App\Application\Cookies\Services\CookieDeclarationRegistryInterface;

// In boot(): runs when the registry is first resolved (or at once if it already was),
// so the module's translations are loaded by then
$this->callAfterResolving(CookieDeclarationRegistryInterface::class, function (CookieDeclarationRegistryInterface $registry): void {
    $registry->declare(new DeclaredCookieDTO(
        name: 'my_module_cookie',
        categorySlug: 'preferences',               // a consent category: necessary, preferences, analytics…
        provider: (string) config('app.name'),
        purpose: (string) __('my-module::messages.cookies.purpose'),
        duration: (string) __('my-module::messages.cookies.duration'),
        // type: CookieType::ThirdParty (App\Domain\Cookies\Enums) for a cookie a third party sets
    ));
});
```

- Declaring the same name again replaces the earlier entry.
- The cookie consent module creates each declared cookie once, in its category; after that the administrators manage it from the panel.

### Cookie consent in the browser

Before reading or writing a cookie that needs consent, a module's frontend asks the core's contract, a public entry of the [public frontend API](#public-frontend-api) (core ^3.19), never a global or an event of another module:

```typescript
import { computed } from 'vue';
import { hasCookieConsent } from '@/contracts/cookieConsent';

// Reactive: follows the banner registering after this component and the visitor changing their mind
const canRemember = computed(() => hasCookieConsent('preferences'));

if (hasCookieConsent('preferences')) {
    document.cookie = 'my_module_cookie=…; Max-Age=7776000; Path=/; SameSite=Lax';
}
```

- Without a cookie consent module on the page, or if it fails, the answer is no consent: the cookie is never written.
- `hasCookieConsent()` reads reactive state: a `computed`, a `watch` or a template that calls it runs again when consent changes, so no listener is needed (`watch(() => hasCookieConsent('preferences'), …)` for side effects).
- The cookie consent module is the provider: it calls `registerCookieConsentProvider({ hasConsent })` once its configuration is known and `notifyCookieConsentChanged()` after the visitor saves a choice. A later registration replaces the earlier one.
- In Vitest the contract resolves to the core's source through the `@` alias of `defineModuleTestConfig`: register a provider in the test instead of faking globals.

## Announcing general surveys

When a general survey (one not about a module's record) opens and whoever made it asked to announce it, the surveys module dispatches the core event `App\Domain\Surveys\Events\GeneralSurveyOpened` (core ^3.30) once the opening has committed. A module that posts to external channels listens to it; neither side imports the other, and with no listener nothing happens.

```php
use App\Domain\Surveys\Events\GeneralSurveyOpened;
use Illuminate\Support\Facades\Event;

// In boot()
Event::listen(GeneralSurveyOpened::class, AnnounceGeneralSurvey::class);

final readonly class AnnounceGeneralSurvey
{
    public function handle(GeneralSurveyOpened $event): void
    {
        // $event->surveyId, $event->title, $event->description (?string), $event->isPoll,
        // $event->closesAt (?DateTimeImmutable, an instant), $event->url (absolute http(s))
    }
}
```

- It is dispatched only the first time the survey opens: reopening after a review does not announce it again.
- `url` is the survey's public link when it has one, otherwise the page that asks to sign in, where only the audience may answer.
- It carries nothing personal (no author, no audience), so it can be posted publicly as it is. Show `closesAt` in the display timezone through `TimezoneServiceInterface`.

## Choosing the end survey in a subject's form

A module that offers survey subjects can let the panel form of each subject choose which survey goes out when it ends: its type's template (the default), another template, a survey of its own prepared from a template, or none. The module only adds the core's field (core ^3.31); the options and the stored choice belong to the surveys module, through `App\Application\Surveys\Contracts\SubjectEndSurveyChoiceInterface`. The core binds a default that offers nothing, so without the surveys module the field stays hidden.

```php
use App\Filament\Forms\Components\EndSurveyField;

// In the resource form, for a type the module offers as a survey subject
EndSurveyField::forSubject('example-thing'),

// When the type depends on the form
EndSurveyField::forSubject(fn (Get $get): string => filled($get('parent_id')) ? 'example-part' : 'example-thing'),
```

- The field saves itself once the record exists (on create, right after the record is created), so the pages need no hook. A form saved by hand, such as a relation manager with its own `save()`, calls `$this->form->model($record)->saveRelationships()` after saving the record.
- Saving the subject never fails because of it: someone who may not choose gets a warning and the type's survey is used, and a choice that can no longer change (the survey already went out) is shown disabled with the reason and a link to the survey.
- Its fields (`end_survey`, `end_survey_base`) are not dehydrated, so they never reach the model.

---

## Extending core functionality

Modules can extend core entities, Filament resources, and add new relationships without modifying core code.

### Dynamic model relationships

Add relationships to core models (Article, Event, Gallery, Photo, etc.) using `resolveRelationUsing()`:

```php
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Models\RegistrationModel;

public function register(): void
{
    parent::register();

    // IMPORTANT: Must be in register(), not boot()
    // Dynamic relationships must be registered before Filament boots
    EventModel::resolveRelationUsing('registrations', function (EventModel $model) {
        return $model->hasMany(RegistrationModel::class, 'event_id', 'id');
    });
}
```

**Important notes:**
- Must be called in `register()`, not `boot()` (Filament needs these before booting)
- The method is idempotent (safe to call multiple times)
- No foreign key constraints between module tables and core tables (use UUIDs)
- The relationship name becomes accessible as `$event->registrations`

**Real-world example:**

The event-registrations module adds a `registrations` relationship to `EventModel` to track user sign-ups for events.

### Extending Filament resources

Add RelationManagers to existing core Resources using the `HasExtendableRelations` trait:

```php
use App\Filament\Resources\EventResource;
use Livewire\Livewire;
use Modules\MyModule\Filament\RelationManagers\RegistrationsRelationManager;

public function boot(): void
{
    parent::boot();

    $this->registerLivewireComponents();
    $this->registerFilamentExtensions();
}

private function registerFilamentExtensions(): void
{
    // Check if the resource exists (core might not have it loaded)
    if (class_exists(EventResource::class)) {
        EventResource::extendRelations([
            RegistrationsRelationManager::class,
        ]);
    }
}

private function registerLivewireComponents(): void
{
    if (! class_exists(Livewire::class)) {
        return;
    }

    // Register the RelationManager as a Livewire component
    // Naming convention: modules.{module-name}.filament.relation-managers.{name}
    Livewire::component(
        'modules.my-module.filament.relation-managers.registrations-relation-manager',
        RegistrationsRelationManager::class
    );
}
```

**Key points:**
- Core resources that support extension use the `HasExtendableRelations` trait
- RelationManagers must be registered as Livewire components
- Use strict naming convention: `modules.{module-name}.filament.relation-managers.{kebab-case-name}`
- Check for class existence before extending (graceful degradation)

**Validation:**

The `HasExtendableRelations` trait automatically filters out RelationManagers whose relationships don't exist on the model. This prevents errors when a module is disabled but its RelationManager was cached.

### Extending Filament form sections

Add form sections to a core resource with the `HasExtendableFormSections` trait:

```php
use App\Filament\Resources\EventResource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

private function registerFilamentExtensions(): void
{
    if (class_exists(EventResource::class)) {
        EventResource::extendFormSections([
            Section::make(__('my-module::labels.registration_settings'))
                ->schema([
                    Toggle::make('registration_enabled')
                        ->label(__('my-module::labels.allow_registration')),
                    TextInput::make('max_registrations')
                        ->numeric()
                        ->label(__('my-module::labels.capacity')),
                ]),
        ]);
    }
}
```

`EventResource` is the only core resource that uses `HasExtendableRelations` and `HasExtendableFormSections`.

---

## Domain events and listeners

Use domain events to decouple side effects from core business logic. Events represent things that have happened in your domain.

### Creating domain events

Domain events use Laravel's `Dispatchable` and `SerializesModels` traits:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RegistrationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $registrationId,
        public readonly string $eventId,
        public readonly string $userId,
    ) {}
}
```

**Scaffolding command:**
```bash
docker exec guildforge_app php artisan module:make-event my-module RegistrationCreated
```

### Creating event listeners

Listeners live in `Infrastructure\Listeners` and handle side effects when events occur.

**Synchronous listener** (default):

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Infrastructure\Listeners;

use Modules\MyModule\Domain\Events\RegistrationCreated;
use Modules\MyModule\Notifications\RegistrationConfirmationNotification;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final readonly class SendRegistrationConfirmation
{
    public function handle(RegistrationCreated $event): void
    {
        $user = UserModel::find($event->userId);

        if ($user !== null) {
            $user->notify(new RegistrationConfirmationNotification(
                $event->registrationId,
                $event->eventId
            ));
        }
    }
}
```

**Queued (async) listener:**

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Infrastructure\Listeners;

use Modules\MyModule\Domain\Events\RegistrationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ProcessHeavyTask implements ShouldQueue
{
    public function handle(RegistrationCreated $event): void
    {
        // Heavy processing logic
    }

    public function failed(RegistrationCreated $event, \Throwable $exception): void
    {
        report($exception);
    }
}
```

The queue workers of the development and production images only consume the default queue: leave `$queue` unset. A listener sent to another queue (`public string $queue = 'listeners';`) is never run.

**Scaffolding commands:**
```bash
# Synchronous (default)
docker exec guildforge_app php artisan module:make-listener my-module SendRegistrationConfirmation --event=RegistrationCreated

# Queued (async)
docker exec guildforge_app php artisan module:make-listener my-module ProcessHeavyTask --event=RegistrationCreated --queued
```

### Creating notifications

Notifications use Laravel's notification system with i18n support:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RegistrationConfirmation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $registrationId,
        public readonly string $eventId,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__('my-module::emails.registration_confirmation.subject'))
            ->greeting(__('my-module::emails.registration_confirmation.greeting'))
            ->line(__('my-module::emails.registration_confirmation.intro'));
    }
}
```

**Scaffolding command:**
```bash
docker exec guildforge_app php artisan module:make-notification my-module RegistrationConfirmation
```

### Registering event listeners

Register listeners in your ServiceProvider's `boot()` method:

```php
use Illuminate\Support\Facades\Event;
use Modules\MyModule\Domain\Events\RegistrationCreated;
use Modules\MyModule\Domain\Events\RegistrationCancelled;
use Modules\MyModule\Infrastructure\Listeners\SendRegistrationConfirmation;
use Modules\MyModule\Infrastructure\Listeners\NotifyEventOrganizer;
use Modules\MyModule\Infrastructure\Listeners\UpdateEventCapacity;

public function boot(): void
{
    parent::boot();

    $this->registerEventListeners();
}

private function registerEventListeners(): void
{
    Event::listen(RegistrationCreated::class, SendRegistrationConfirmation::class);
    Event::listen(RegistrationCreated::class, NotifyEventOrganizer::class);
    Event::listen(RegistrationCreated::class, UpdateEventCapacity::class);

    Event::listen(RegistrationCancelled::class, UpdateEventCapacity::class);
}
```

**Why use events and listeners?**
- **Decoupling**: The core domain logic doesn't know about email sending
- **Single responsibility**: Each listener has one job
- **Easy to extend**: Add new listeners without modifying existing code
- **Testable**: Mock events in tests

---

## State machine pattern

State machines ensure entities can only transition through valid states.

### Creating state enums

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Domain\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';

    /**
     * Determine if transition to new state is allowed.
     */
    public function canTransitionTo(self $newState): bool
    {
        return match($this) {
            self::Pending => in_array($newState, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($newState, [self::Cancelled, self::Attended, self::NoShow], true),
            self::Cancelled => false, // Final state
            self::Attended => false,  // Final state
            self::NoShow => false,    // Final state
        };
    }

    /**
     * Get all valid next states from current state.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled, self::Attended, self::NoShow],
            self::Cancelled, self::Attended, self::NoShow => [],
        };
    }

    /**
     * Get translated label for the state.
     */
    public function label(): string
    {
        return match($this) {
            self::Pending => __('my-module::states.pending'),
            self::Confirmed => __('my-module::states.confirmed'),
            self::Cancelled => __('my-module::states.cancelled'),
            self::Attended => __('my-module::states.attended'),
            self::NoShow => __('my-module::states.no_show'),
        };
    }

    /**
     * Get CSS color class for badges.
     */
    public function color(): string
    {
        return match($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
            self::Attended => 'info',
            self::NoShow => 'gray',
        };
    }
}
```

**Scaffolding command:**
```bash
docker exec guildforge_app php artisan module:make-enum my-module RegistrationStatus
```

### Using state machines in entities

```php
use Modules\MyModule\Domain\Enums\RegistrationStatus;
use Modules\MyModule\Domain\Exceptions\InvalidStateTransitionException;

final class Registration
{
    public function __construct(
        private RegistrationId $id,
        private EventId $eventId,
        private UserId $userId,
        private RegistrationStatus $status,
    ) {}

    public function confirm(): void
    {
        $newStatus = RegistrationStatus::Confirmed;

        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidStateTransitionException::fromTo(
                $this->status->value,
                $newStatus->value,
                'Cannot confirm registration in current state'
            );
        }

        $this->status = $newStatus;
    }

    public function cancel(): void
    {
        $newStatus = RegistrationStatus::Cancelled;

        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidStateTransitionException::fromTo(
                $this->status->value,
                $newStatus->value,
                'Cannot cancel registration in current state'
            );
        }

        $this->status = $newStatus;
    }

    public function markAttended(): void
    {
        $newStatus = RegistrationStatus::Attended;

        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidStateTransitionException::fromTo(
                $this->status->value,
                $newStatus->value,
                'Cannot mark as attended in current state'
            );
        }

        $this->status = $newStatus;
    }

    public function getStatus(): RegistrationStatus
    {
        return $this->status;
    }
}
```

**Benefits:**
- **Compile-time safety**: Invalid transitions are caught immediately
- **Self-documenting**: The enum shows all possible states and transitions
- **Easy to test**: Just test the transition logic in one place
- **Prevents invalid data**: No way to bypass validation

---

## Releasing a module

A module starts at `version` `0.0.0`; its first release is the one that sets `1.0.0` (raise `version` in `module.json` and push the `v1.0.0` tag).

Releases are built by the reusable workflow in the host repository, which `.github/workflows/release.yml` (written by `module:make`) calls when a `v*.*.*` tag is pushed. It validates `module.json`, runs the module's tests inside the host, builds the assets with `MODULE_STANDALONE_OUTPUT=true` and publishes the ZIP, its checksum and `module.json`. See [CI/CD for module distribution](../module-ci-cd.md).

To build the same ZIP locally, run `make module-zip` on the host and pick the module: it writes `dist/<name>-<version>.zip` and its `.sha256`.

---

## Quick reference

### Management commands

| Command | Description |
|---------|-------------|
| `module:list` | List all modules |
| `module:discover` | Register the modules found on disk |
| `module:enable {module}` | Enable a module (runs migrations and seeders the first time) |
| `module:enable {module} --migrate --seed` | Enable and run pending migrations and seeders |
| `module:disable {module}` | Disable a module |
| `module:migrate {module}` | Run module migrations |
| `module:migrate {module} --rollback [--step=N]` | Roll back module migrations |
| `module:seed {module}` | Run the seeders that have not run yet |
| `module:publish-assets [module] [--all]` | Copy `resources/assets` to `public/modules/<name>` |
| `module:publish-build-assets` | Copy every module's `public/build` to `public/build/modules/<name>` |
| `module:build {module} [--all] [--force]` | Build Vue assets in the PHP process (needs Node there) |
| `module:check-updates [--force]` | Check GitHub for module updates (`--force` ignores the cache) |
| `module:update [name] [--all] [--dry-run] [--force]` | Update a module or all modules |
| `module:set-source {name} {owner/repo}` | Set the repository a module is updated from |
| `module:sync-from-image {path}` | Sync modules from the Docker image, keeping modules updated from a ZIP |

### Scaffolding commands

| Command | Description |
|---------|-------------|
| `module:make {name} [--display-name=] [--description=] [--author=] [--repository=]` | Create a module |
| `module:make-entity {module} {name} [--migration]` | Entity, ID value object, repository interface, model and repository |
| `module:make-controller {module} {name} [--resource\|--api\|--invokable]` | Controller |
| `module:make-request {module} {name}` | Form request |
| `module:make-service {module} {name} [--interface]` | Service |
| `module:make-dto {module} {name} [--response]` | DTO |
| `module:make-migration {module} {name} [--create=] [--table=]` | Migration |
| `module:make-test {module} {name} [--unit\|--dto\|--feature\|--filament]` | Test (`--unit` is the default) |
| `module:make-filament-resource {module} {name}` | Filament resource and policy |
| `module:make-relation-manager {module} {name} [--resource=]` | Filament RelationManager for a core resource |
| `module:make-widget {module} {name}` | Filament widget |
| `module:make-enum {module} {name}` | Enum |
| `module:make-event {module} {name}` | Domain event |
| `module:make-listener {module} {name} [--event=] [--queued]` | Event listener |
| `module:make-notification {module} {name}` | Notification |
| `module:make-vue-page {module} {name}` | Vue page and its types |
| `module:make-vue-component {module} {name}` | Vue component |

### ServiceProvider methods

| Method | Returns | Description |
|--------|---------|-------------|
| `moduleName()` | `string` | Kebab-case module name (abstract, required) |
| `registerPermissions()` | `PermissionDTO[]` | Module permissions |
| `registerNavigation()` | `NavigationItemDTO[]` | Panel navigation items (never copied into the public menu) |
| `registerRoutes()` | `ModuleRouteDTO[]` | Public pages offered in the menu editor |
| `registerPagePrefixes()` | `PagePrefixDTO[]` | Inertia page prefixes of the module |
| `registerSlots()` | `SlotRegistrationDTO[]` | Slot component registrations |
| `registerCalendarSources()` | `class-string<CalendarEntrySourceInterface>[]` | Sources of public calendar entries; fill `details` with `description`, `location`, `imagePublicId` and `meta` rows (`icon`, `label`, `value`, translated) so the detail panel is as complete as an event's |
| `registerPolicies()` | `array<class-string, class-string>` | Model to policy mappings |
| `registerNavigationGroups()` | `array` | Filament navigation groups |
| `registerFilamentPages()` | `class-string<Page>[]` | Filament pages |
| `registerFilamentWidgets()` | `class-string<Widget>[]` | Dashboard widgets (optional method, not declared on the base class) |
| `getSettingsSchema()` | `Filament\Schemas\Components\Component[]` | Form schema of the module settings page |
| `onEnable()`, `onDisable()` | `void` | Declared, but never called by the core |
