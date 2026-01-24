# Module development guide

This guide covers how to create and develop modules for GuildForge using the Module SDK.

## Table of contents

1. [Getting started](#getting-started)
2. [Module structure](#module-structure)
3. [Scaffolding commands](#scaffolding-commands)
4. [Service provider](#service-provider)
5. [Permissions & navigation](#permissions--navigation)
6. [Module settings](#module-settings)
7. [Filament integration](#filament-integration)
8. [Slot registration](#slot-registration)
9. [Module installation](#module-installation)
10. [Extending core functionality](#extending-core-functionality)
11. [Domain events and listeners](#domain-events-and-listeners)
12. [State machine pattern](#state-machine-pattern)
13. [Testing modules](#testing-modules)
14. [Best practices](#best-practices)
15. [Quick reference](#quick-reference)

---

## Getting started

### Creating a new module

Use the `module:make` command to create a new module with the standard directory structure:

```bash
php artisan module:make my-module --description="My awesome module" --author="Developer"
```

This creates a complete module skeleton at `modules/my-module/` with:
- Service provider
- Configuration
- Routes (web and API)
- Language files
- Directory structure for all layers

### Discovering and enabling

After creating a module, you must discover and enable it:

```bash
# Register the module in the database
php artisan module:discover

# Enable the module
php artisan module:enable my-module

# Run any module migrations
php artisan module:migrate my-module
```

### Listing modules

View all discovered modules:

```bash
php artisan module:list
```

---

## Module structure

A typical module follows this structure:

> **Note:** Modules are stored in the `modules/` directory relative to the Laravel application root (`base_path('modules')`). In this project's Docker setup, that corresponds to `src/modules/` on the host.

```
modules/my-module/
├── module.json              # Module manifest
├── config/
│   └── module.php           # Module configuration
├── database/
│   └── migrations/          # Database migrations
├── lang/
│   └── es/
│       └── messages.php     # Translations
├── resources/
│   ├── js/
│   │   ├── pages/           # Vue pages
│   │   ├── components/      # Vue components
│   │   └── types/           # TypeScript types
│   └── views/               # Blade views
├── routes/
│   ├── web.php              # Web routes
│   └── api.php              # API routes
├── src/
│   ├── MyModuleServiceProvider.php
│   ├── Domain/
│   │   ├── Entities/
│   │   ├── ValueObjects/
│   │   └── Repositories/
│   ├── Application/
│   │   ├── DTOs/
│   │   └── Services/
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/
│   │   └── Services/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   └── Filament/
│       └── Resources/
└── tests/
    ├── Unit/
    └── Feature/
```

### Module manifest (module.json)

```json
{
    "name": "my-module",
    "version": "1.0.0",
    "namespace": "Modules\\MyModule",
    "provider": "MyModuleServiceProvider",
    "description": "My awesome module",
    "author": "Developer",
    "requires": {
        "php": ">=8.2",
        "laravel": ">=11.0",
        "modules": ["another-module:^1.0"],
        "extensions": ["json"]
    },
    "dependencies": ["another-module"]
}
```

---

## Scaffolding commands

The Module SDK provides commands to scaffold all components:

### Domain layer

```bash
# Create entity, value object, repository interface, model, and repository
php artisan module:make-entity my-module Game --migration
```

### Application layer

```bash
# Create DTO
php artisan module:make-dto my-module Game

# Create response DTO
php artisan module:make-dto my-module Game --response

# Create service with interface
php artisan module:make-service my-module Game --interface
```

### HTTP layer

```bash
# Create controller (default)
php artisan module:make-controller my-module Game

# Create resource controller
php artisan module:make-controller my-module Game --resource

# Create API controller
php artisan module:make-controller my-module Game --api

# Create invokable controller
php artisan module:make-controller my-module ShowDashboard --invokable

# Create form request
php artisan module:make-request my-module StoreGame
```

### Filament

```bash
# Create Filament resource with List, Create, Edit pages
php artisan module:make-filament-resource my-module Game

# Create Filament RelationManager
php artisan module:make-relation-manager my-module Registrations --resource=Event

# Create Filament Widget
php artisan module:make-widget my-module RegistrationStats
```

### Domain events and enums

```bash
# Create enum
php artisan module:make-enum my-module RegistrationStatus

# Create domain event
php artisan module:make-event my-module RegistrationCreated

# Create event listener
php artisan module:make-listener my-module SendRegistrationConfirmation
```

### Frontend

```bash
# Create Vue page with TypeScript types
php artisan module:make-vue-page my-module GameList

# Create Vue component
php artisan module:make-vue-component my-module GameCard
```

### Database

```bash
# Create migration
php artisan module:make-migration my-module create_games_table --create=my_module_games
```

### Testing

```bash
# Create unit test
php artisan module:make-test my-module Game --unit

# Create feature test
php artisan module:make-test my-module GameController --feature
```

---

## Service provider

The service provider is the entry point for your module. It extends `ModuleServiceProvider`:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule;

use App\Modules\ModuleServiceProvider;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;

final class MyModuleServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(
            $this->modulePath('config/module.php'),
            'my_module'
        );

        // Register service bindings
        $this->app->bind(GameRepositoryInterface::class, EloquentGameRepository::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Additional boot logic
    }

    public function onEnable(): void
    {
        // Called when module is enabled
    }

    public function onDisable(): void
    {
        // Called when module is disabled
    }

    public function registerPermissions(): array
    {
        return [
            new PermissionDTO(
                name: 'view',
                label: 'View Games',
                group: 'Game Library',
                module: 'my-module',
            ),
            new PermissionDTO(
                name: 'manage',
                label: 'Manage Games',
                group: 'Game Library',
                module: 'my-module',
            ),
        ];
    }

    public function registerNavigation(): array
    {
        return [
            new NavigationItemDTO(
                label: 'Games',
                route: 'my_module.games.index',
                icon: 'heroicon-o-puzzle-piece',
                group: 'Game Library',
                sort: 10,
                module: 'my-module',
                permissions: ['my-module.view'],
            ),
        ];
    }
}
```

---

## Permissions & navigation

### Registering permissions

Permissions are registered via the service provider's `registerPermissions()` method. They are automatically added to the permission registry when the module is booted.

```php
public function registerPermissions(): array
{
    return [
        new PermissionDTO(
            name: 'view',
            label: __('my_module::permissions.view'),
            group: 'My Module',
            description: 'Can view resources',
            module: 'my-module',
            roles: ['admin', 'editor'],
        ),
    ];
}
```

### Registering navigation

Navigation items can be registered with permission requirements:

```php
public function registerNavigation(): array
{
    return [
        new NavigationItemDTO(
            label: __('my_module::nav.games'),
            route: 'my_module.games.index',
            icon: 'heroicon-o-cube',
            group: 'Content',
            sort: 20,
            module: 'my-module',
            permissions: ['my-module.view'],
            children: [
                new NavigationItemDTO(
                    label: 'All Games',
                    route: 'my_module.games.index',
                    module: 'my-module',
                ),
            ],
        ),
    ];
}
```

---

## Module settings

Modules can define configurable settings that appear in the Filament admin panel.

### Defining default settings

Create `config/settings.php` in your module:

```php
// modules/my-module/config/settings.php
return [
    'api_key' => env('MY_MODULE_API_KEY', ''),
    'max_items' => 10,
    'features' => [
        'feature_a' => true,
        'feature_b' => false,
    ],
];
```

### Settings form schema

Override `getSettingsSchema()` in your ServiceProvider to define Filament form components:

```php
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

public function getSettingsSchema(): array
{
    return [
        Section::make(__('my_module::settings.api'))
            ->schema([
                TextInput::make('api_key')
                    ->label(__('my_module::settings.api_key'))
                    ->password()
                    ->required(),
                TextInput::make('max_items')
                    ->label(__('my_module::settings.max_items'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100),
            ]),
        Section::make(__('my_module::settings.features'))
            ->schema([
                Toggle::make('features.feature_a')
                    ->label(__('my_module::settings.feature_a')),
                Toggle::make('features.feature_b')
                    ->label(__('my_module::settings.feature_b')),
            ]),
    ];
}
```

### Accessing settings

```php
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;

$moduleManager = app(ModuleManagerServiceInterface::class);

// Get all settings for a module
$settings = $moduleManager->getSettings(new ModuleName('my-module'));

// Update settings
$moduleManager->updateSettings(new ModuleName('my-module'), [
    'api_key' => 'new-key',
    'max_items' => 20,
]);

// Access via config helper (after boot)
$apiKey = config('modules.settings.my-module.api_key');
```

---

## Filament integration

### Navigation groups

Modules can register custom Filament navigation groups:

```php
public function registerNavigationGroups(): array
{
    return [
        'Game Library' => [
            'icon' => 'heroicon-o-puzzle-piece',
            'sort' => 20,
        ],
        'Statistics' => [
            'icon' => 'heroicon-o-chart-bar',
            'sort' => 30,
        ],
    ];
}
```

Groups are only added if they don't already exist in core Filament configuration.

### Policy registration

Register model policies for Filament authorization:

```php
public function registerPolicies(): array
{
    return [
        \Modules\MyModule\Domain\Entities\Game::class => \Modules\MyModule\Policies\GamePolicy::class,
        \Modules\MyModule\Domain\Entities\Session::class => \Modules\MyModule\Policies\SessionPolicy::class,
    ];
}
```

### Auto-discovery

Filament automatically discovers Resources, Pages, and Widgets from enabled modules:
- **Resources**: `modules/{name}/src/Filament/Resources/`
- **Pages**: `modules/{name}/src/Filament/Pages/`
- **Widgets**: `modules/{name}/src/Filament/Widgets/`

---

## Slot registration

Slots allow modules to inject Vue components into predefined layout positions on the frontend.

### SlotRegistrationDTO fields

| Field | Type | Description |
|-------|------|-------------|
| `slot` | string | Target slot identifier (e.g., `home.sidebar`) |
| `component` | string | Vue component name |
| `module` | string | Module name (kebab-case) |
| `order` | int | Sort order (lower = rendered first) |
| `props` | array | Props passed to the Vue component |
| `dataKeys` | array | Required Inertia shared data keys |

### Registering slot components

Override `registerSlots()` in your ServiceProvider:

```php
use App\Application\Modules\DTOs\SlotRegistrationDTO;

public function registerSlots(): array
{
    return [
        new SlotRegistrationDTO(
            slot: 'home.sidebar',
            component: 'UpcomingGames',
            module: 'my-module',
            order: 10,
            props: ['limit' => 5],
            dataKeys: ['upcomingGames'],
        ),
        new SlotRegistrationDTO(
            slot: 'home.featured',
            component: 'FeaturedGame',
            module: 'my-module',
            order: 20,
            props: [],
            dataKeys: ['featuredGame'],
        ),
    ];
}
```

### Frontend usage

Use the `useModuleSlots` composable in Vue:

```typescript
import { useModuleSlots } from '@/composables/useModuleSlots';

const { getComponents, hasComponents } = useModuleSlots();

// Get components for a slot
const sidebarWidgets = getComponents('home.sidebar');

// Check if slot has components
if (hasComponents('home.sidebar')) {
  // Render widgets
}
```

```vue
<template>
  <aside v-if="hasComponents('home.sidebar')">
    <component
      v-for="widget in getComponents('home.sidebar')"
      :key="`${widget.module}-${widget.component}`"
      :is="resolveComponent(widget.component)"
      v-bind="widget.props"
    />
  </aside>
</template>
```

---

## Module installation

### Installing from Filament admin

Navigate to **Settings > Modules** in the Filament admin panel. Use the "Install module" button to upload a ZIP file.

### Programmatic installation

```php
use App\Application\Modules\Services\ModuleInstallerInterface;

$moduleInstaller = app(ModuleInstallerInterface::class);
$manifest = $moduleInstaller->installFromZip($uploadedFile);

// $manifest contains the parsed module.json data
echo "Installed: {$manifest->name} v{$manifest->version}";
```

### Uninstallation process

```php
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;

$moduleManager = app(ModuleManagerServiceInterface::class);
$moduleManager->uninstall(new ModuleName('my-module'));
```

**Uninstallation steps:**
1. Verify no dependent modules are enabled (fails otherwise)
2. Revert all module migrations
3. Delete the module directory
4. Remove database record
5. Dispatch `ModuleUninstalled` event

### Installation events

| Event | Fields | Description |
|-------|--------|-------------|
| `ModuleInstalled` | moduleName, moduleVersion, modulePath | Dispatched after successful installation |
| `ModuleUninstalled` | moduleName | Dispatched after successful uninstallation |

---

## Testing modules

### Using ModuleTestCase

Extend `ModuleTestCase` for module-specific tests:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Tests\Feature;

use Tests\Support\Modules\ModuleTestCase;

final class GameTest extends ModuleTestCase
{
    protected ?string $moduleName = 'my-module';
    protected bool $autoEnableModule = true;

    public function test_it_can_list_games(): void
    {
        $this->assertModuleEnabled('my-module');

        $response = $this->get(route('my_module.games.index'));

        $response->assertOk();
    }
}
```

### Using InteractsWithModules trait

For more control, use the trait directly:

```php
use Tests\Support\Modules\InteractsWithModules;

final class MyTest extends TestCase
{
    use InteractsWithModules;

    public function test_scaffolding(): void
    {
        $result = $this->createTestModule('test-module');

        $this->assertScaffoldSuccess($result);
        $this->assertModuleFileExists('test-module', 'module.json');
    }
}
```

---

## Best practices

### 1. Follow DDD architecture

- Keep domain logic in `Domain/`
- Use DTOs for data transfer
- Implement repository interfaces

### 2. Use translations

```php
// In PHP
__('my_module::messages.created')

// In Vue
$t('my_module.messages.created')
```

### 3. Prefix database tables

Use module name as prefix:

```php
$table->uuid('id')->primary();  // Table: my_module_games
```

### 4. Register Filament resources

Filament resources from modules need to be discovered. Add them to the Filament panel provider or use auto-discovery.

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

### 6. Use helper functions

```php
// Get module path
module_path('my-module', 'config/module.php');

// Get module config
module_config('my-module', 'setting_key');

// Check if module is enabled
module_enabled('my-module');

// Get module translation
module_trans('my-module', 'messages.success');
```

### 7. Use the Module facade

```php
use App\Modules\Facades\Module;

// Set current context
Module::setCurrent('my-module');

// Get config for current module
$value = Module::config('key');

// Get path for current module
$path = Module::path('src');
```

### 8. Use domain events for side effects

Don't put email sending or cascading updates in services. Use events and listeners instead:

```php
// Bad: Side effects in service
public function createRegistration(CreateRegistrationDTO $dto): Registration
{
    $registration = $this->repository->save($registration);

    // Don't do this - couples registration logic to email
    Mail::to($user)->send(new RegistrationConfirmation($registration));

    return $registration;
}

// Good: Dispatch event, let listeners handle side effects
public function createRegistration(CreateRegistrationDTO $dto): Registration
{
    $registration = $this->repository->save($registration);

    event(RegistrationCreated::create(
        $registration->getId()->toString(),
        $registration->getEventId()->toString(),
        $registration->getUserId()->toString()
    ));

    return $registration;
}
```

### 9. Validate state transitions in entities

Don't allow invalid state changes. Use enums with `canTransitionTo()`:

```php
// Bad: No validation
public function cancel(): void
{
    $this->status = RegistrationStatus::Cancelled; // What if already attended?
}

// Good: Validate transition
public function cancel(): void
{
    if (!$this->status->canTransitionTo(RegistrationStatus::Cancelled)) {
        throw InvalidStateTransitionException::fromTo(
            $this->status->value,
            RegistrationStatus::Cancelled->value,
            'Cannot cancel registration in current state'
        );
    }

    $this->status = RegistrationStatus::Cancelled;
}
```

### 10. Register Livewire components for RelationManagers

Without this, RelationManagers won't work:

```php
private function registerLivewireComponents(): void
{
    if (!class_exists(Livewire::class)) {
        return;
    }

    Livewire::component(
        'modules.my-module.filament.relation-managers.registrations-relation-manager',
        RegistrationsRelationManager::class
    );
}
```

### 11. Use `register()` for model extensions

Dynamic relationships must be registered before Filament boots:

```php
// Bad: In boot() - too late, Filament already initialized
public function boot(): void
{
    EventModel::resolveRelationUsing('registrations', function ($model) {
        return $model->hasMany(RegistrationModel::class);
    });
}

// Good: In register() - before Filament boots
public function register(): void
{
    parent::register();

    EventModel::resolveRelationUsing('registrations', function ($model) {
        return $model->hasMany(RegistrationModel::class, 'event_id', 'id');
    });
}
```

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
use Modules\MyModule\Filament\Resources\EventResource\RelationManagers\RegistrationsRelationManager;

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
    if (!class_exists(Livewire::class)) {
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

Add form sections to existing Resources using the `HasExtendableFormSections` trait:

```php
use App\Filament\Resources\EventResource\Pages\EditEvent;

private function registerFilamentExtensions(): void
{
    if (class_exists(EditEvent::class)) {
        EditEvent::extendFormSections([
            Section::make(__('my_module::labels.registration_settings'))
                ->schema([
                    Toggle::make('registration_enabled')
                        ->label(__('my_module::labels.allow_registration')),
                    TextInput::make('max_registrations')
                        ->numeric()
                        ->label(__('my_module::labels.capacity')),
                ])
        ]);
    }
}
```

---

## Domain events and listeners

Use domain events to decouple side effects from core business logic. Events represent things that have happened in your domain.

### Creating domain events

Domain events are immutable DTOs that capture what happened:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Domain\Events;

use DateTimeImmutable;

final readonly class RegistrationCreated
{
    public function __construct(
        public string $registrationId,
        public string $eventId,
        public string $userId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function create(
        string $registrationId,
        string $eventId,
        string $userId,
    ): self {
        return new self(
            $registrationId,
            $eventId,
            $userId,
            new DateTimeImmutable()
        );
    }
}
```

**Scaffolding command:**
```bash
php artisan module:make-event my-module RegistrationCreated
```

### Creating event listeners

Listeners handle the side effects when events occur:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Listeners;

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

**Scaffolding command:**
```bash
php artisan module:make-listener my-module SendRegistrationConfirmation
```

### Registering event listeners

Register listeners in your ServiceProvider's `boot()` method:

```php
use Illuminate\Support\Facades\Event;
use Modules\MyModule\Domain\Events\RegistrationCreated;
use Modules\MyModule\Domain\Events\RegistrationCancelled;
use Modules\MyModule\Listeners\SendRegistrationConfirmation;
use Modules\MyModule\Listeners\NotifyEventOrganizer;
use Modules\MyModule\Listeners\UpdateEventCapacity;

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
- **Single Responsibility**: Each listener has one job
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
            self::Pending => __('my_module::states.pending'),
            self::Confirmed => __('my_module::states.confirmed'),
            self::Cancelled => __('my_module::states.cancelled'),
            self::Attended => __('my_module::states.attended'),
            self::NoShow => __('my_module::states.no_show'),
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
php artisan module:make-enum my-module RegistrationStatus
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

        if (!$this->status->canTransitionTo($newStatus)) {
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

        if (!$this->status->canTransitionTo($newStatus)) {
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

        if (!$this->status->canTransitionTo($newStatus)) {
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

## Quick reference

### Management commands

| Command | Description |
|---------|-------------|
| `module:list` | List all modules |
| `module:discover` | Scan for new modules |
| `module:enable {module}` | Enable a module |
| `module:disable {module}` | Disable a module |
| `module:migrate {module}` | Run module migrations |
| `module:publish-assets` | Publish module assets |

### Scaffolding commands

| Command | Description |
|---------|-------------|
| `module:make {name}` | Create new module |
| `module:make-entity {module} {name}` | Create entity |
| `module:make-controller {module} {name}` | Create controller |
| `module:make-request {module} {name}` | Create form request |
| `module:make-service {module} {name}` | Create service |
| `module:make-dto {module} {name}` | Create DTO |
| `module:make-migration {module} {name}` | Create migration |
| `module:make-test {module} {name}` | Create test |
| `module:make-filament-resource {module} {name}` | Create Filament resource |
| `module:make-relation-manager {module} {name}` | Create Filament RelationManager |
| `module:make-widget {module} {name}` | Create Filament Widget |
| `module:make-enum {module} {name}` | Create enum |
| `module:make-event {module} {name}` | Create domain event |
| `module:make-listener {module} {name}` | Create event listener |
| `module:make-vue-page {module} {name}` | Create Vue page |
| `module:make-vue-component {module} {name}` | Create Vue component |

### ServiceProvider methods

| Method | Returns | Description |
|--------|---------|-------------|
| `registerPermissions()` | `PermissionDTO[]` | Module permissions |
| `registerNavigation()` | `NavigationItemDTO[]` | Navigation items |
| `registerNavigationGroups()` | `array` | Filament navigation groups |
| `registerPolicies()` | `array` | Model to policy mappings |
| `registerSlots()` | `SlotRegistrationDTO[]` | Slot component registrations |
| `getSettingsSchema()` | `Component[]` | Filament form schema for settings |
| `onEnable()` | `void` | Called when module is enabled |
| `onDisable()` | `void` | Called when module is disabled |
