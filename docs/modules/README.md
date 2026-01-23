# Module Development Guide

This guide covers how to create and develop modules for Runesword using the Module SDK.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Module Structure](#module-structure)
3. [Scaffolding Commands](#scaffolding-commands)
4. [Service Provider](#service-provider)
5. [Permissions & Navigation](#permissions--navigation)
6. [Testing Modules](#testing-modules)
7. [Best Practices](#best-practices)

---

## Getting Started

### Creating a New Module

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

### Discovering and Enabling

After creating a module, you must discover and enable it:

```bash
# Register the module in the database
php artisan module:discover

# Enable the module
php artisan module:enable my-module

# Run any module migrations
php artisan module:migrate my-module
```

### Listing Modules

View all discovered modules:

```bash
php artisan module:list
```

---

## Module Structure

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

### Module Manifest (module.json)

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

## Scaffolding Commands

The Module SDK provides commands to scaffold all components:

### Domain Layer

```bash
# Create entity, value object, repository interface, model, and repository
php artisan module:make-entity my-module Game --migration
```

### Application Layer

```bash
# Create DTO
php artisan module:make-dto my-module Game

# Create response DTO
php artisan module:make-dto my-module Game --response

# Create service with interface
php artisan module:make-service my-module Game --interface
```

### HTTP Layer

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

## Service Provider

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

## Permissions & Navigation

### Registering Permissions

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

### Registering Navigation

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

## Module Settings

Modules can define configurable settings that appear in the Filament admin panel.

### Defining Default Settings

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

### Settings Form Schema

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

### Accessing Settings

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

## Filament Integration

### Navigation Groups

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

### Policy Registration

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

### Auto-Discovery

Filament automatically discovers Resources, Pages, and Widgets from enabled modules:
- **Resources**: `modules/{name}/src/Filament/Resources/`
- **Pages**: `modules/{name}/src/Filament/Pages/`
- **Widgets**: `modules/{name}/src/Filament/Widgets/`

---

## Slot Registration

Slots allow modules to inject Vue components into predefined layout positions on the frontend.

### SlotRegistrationDTO Fields

| Field | Type | Description |
|-------|------|-------------|
| `slot` | string | Target slot identifier (e.g., `home.sidebar`) |
| `component` | string | Vue component name |
| `module` | string | Module name (kebab-case) |
| `order` | int | Sort order (lower = rendered first) |
| `props` | array | Props passed to the Vue component |
| `dataKeys` | array | Required Inertia shared data keys |

### Registering Slot Components

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

### Frontend Usage

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

## Module Installation

### Installing from Filament Admin

Navigate to **Settings > Modules** in the Filament admin panel. Use the "Install Module" button to upload a ZIP file.

### Programmatic Installation

```php
use App\Application\Modules\Services\ModuleInstallerInterface;

$moduleInstaller = app(ModuleInstallerInterface::class);
$manifest = $moduleInstaller->installFromZip($uploadedFile);

// $manifest contains the parsed module.json data
echo "Installed: {$manifest->name} v{$manifest->version}";
```

### Uninstallation Process

```php
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;

$moduleManager = app(ModuleManagerServiceInterface::class);
$moduleManager->uninstall(new ModuleName('my-module'));
```

**Uninstallation Steps:**
1. Verify no dependent modules are enabled (fails otherwise)
2. Revert all module migrations
3. Delete the module directory
4. Remove database record
5. Dispatch `ModuleUninstalled` event

### Installation Events

| Event | Fields | Description |
|-------|--------|-------------|
| `ModuleInstalled` | moduleName, moduleVersion, modulePath | Dispatched after successful installation |
| `ModuleUninstalled` | moduleName | Dispatched after successful uninstallation |

---

## Testing Modules

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

### Using InteractsWithModules Trait

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

## Best Practices

### 1. Follow DDD Architecture

- Keep domain logic in `Domain/`
- Use DTOs for data transfer
- Implement repository interfaces

### 2. Use Translations

```php
// In PHP
__('my_module::messages.created')

// In Vue
$t('my_module.messages.created')
```

### 3. Prefix Database Tables

Use module name as prefix:

```php
$table->uuid('id')->primary();  // Table: my_module_games
```

### 4. Register Filament Resources

Filament resources from modules need to be discovered. Add them to the Filament panel provider or use auto-discovery.

### 5. Handle Module Dependencies

Declare dependencies in `module.json`:

```json
{
    "requires": {
        "modules": ["base-module:^1.0"]
    },
    "dependencies": ["base-module"]
}
```

### 6. Use Helper Functions

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

### 7. Use the Module Facade

```php
use App\Modules\Facades\Module;

// Set current context
Module::setCurrent('my-module');

// Get config for current module
$value = Module::config('key');

// Get path for current module
$path = Module::path('src');
```

---

## Quick Reference

### Management Commands

| Command | Description |
|---------|-------------|
| `module:list` | List all modules |
| `module:discover` | Scan for new modules |
| `module:enable {module}` | Enable a module |
| `module:disable {module}` | Disable a module |
| `module:migrate {module}` | Run module migrations |
| `module:publish-assets` | Publish module assets |

### Scaffolding Commands

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
| `module:make-vue-page {module} {name}` | Create Vue page |
| `module:make-vue-component {module} {name}` | Create Vue component |

### ServiceProvider Methods

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
