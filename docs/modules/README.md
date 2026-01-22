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
