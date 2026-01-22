# Association website

Website template for associations with:

- **Public frontend**: Events, articles, photo galleries for visitors
- **Admin panel**: Content management for members

## Tech stack

| Layer               | Technology                                    |
|---------------------|-----------------------------------------------|
| Backend framework   | Laravel 11+                                   |
| Admin panel         | Filament 3                                    |
| Frontend bridge     | Inertia.js                                    |
| Frontend framework  | Vue 3 (Composition API with `<script setup>`) |
| Frontend language   | TypeScript (strict mode)                      |
| Build tool          | Vite 5                                        |
| CSS framework       | Tailwind CSS                                  |
| State management    | Pinia                                         |
| Database (dev/prod) | PostgreSQL 16                                 |
| Database (testing)  | SQLite in-memory                              |
| Containerization    | Docker + Docker Compose                       |

## Requirements

- Docker
- Docker Compose
- Make

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd <project-folder>
   ```

2. Copy the environment file and configure:
   ```bash
   cp src/.env.example src/.env
   ```

3. Configure your association in `.env`:
   ```env
   APP_NAME="Your association"
   APP_DESCRIPTION="Brief description of your association"
   ```

4. Start the containers:
   ```bash
   make up
   ```

5. Install dependencies (inside container):
   ```bash
   make shell
   composer install
   npm install
   ```

6. Generate an application key and run migrations:
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

7. Build frontend assets:
   ```bash
   npm run build
   ```

## Commands

| Command          | Description                        |
|------------------|------------------------------------|
| `make up`        | Start containers                   |
| `make down`      | Stop containers                    |
| `make shell`     | Enter PHP container                |
| `make test`      | Run all tests                      |
| `make test-unit` | Run unit tests only                |
| `make check`     | Run ALL linters (PHP + JS + types) |
| `make fresh`     | Fresh migration with seeders       |

## Project structure

```
src/app/
├── Domain/           # Entities, Value Objects, Enums, Exceptions, Repository Interfaces
├── Application/      # DTOs (Create/Response), Factories, Query Service Interfaces
├── Infrastructure/   # Eloquent Models, Repositories, Query Services, External Services
├── Http/             # Controllers, Resources, Requests, Middleware
├── Filament/         # Admin Resources, Widgets
├── Policies/         # Authorization Policies
└── Providers/        # Service Providers

resources/js/
├── pages/            # Inertia pages (Home, Events, Articles, Gallery, About)
├── components/       # Vue components (ui, layout, events, articles, gallery)
├── composables/      # Vue composables
├── stores/           # Pinia stores
├── types/            # TypeScript types
├── locales/          # i18n translations
├── layouts/          # Page layouts
└── utils/            # Utility functions

tests/
├── Unit/             # Unit tests (Domain, Application)
├── Integration/      # Integration tests (Infrastructure)
└── Feature/          # Feature tests (Controllers)
```

## Modules

The project includes an extensible module system for adding functionality without modifying core code.

```bash
# Create a new module
php artisan module:make my-module --description="My module"

# Discover and enable
php artisan module:discover
php artisan module:enable my-module

# List all modules
php artisan module:list
```

For detailed module development documentation, see [docs/modules/README.md](docs/modules/README.md).

## Architecture

This project follows **Clean Architecture** principles:

```
Domain → Application → Infrastructure → Presentation
```

- **Domain**: Business entities, value objects, and repository interfaces
- **Application**: DTOs (Create/Response), factories, query service interfaces
- **Infrastructure**: Eloquent implementations, query services, external services
- **Presentation**: Controllers, HTTP resources, Filament resources, Vue components

## Public routes

| Route               | Description                                           |
|---------------------|-------------------------------------------------------|
| `/`                 | Home with hero slider, events, articles, gallery      |
| `/nosotros`         | About page with association info and contact section  |
| `/contacto`         | Contact form (POST)                                   |
| `/calendario`       | Interactive event calendar                            |
| `/eventos`          | Events listing                                        |
| `/eventos/{slug}`   | Event detail                                          |
| `/articulos`        | Articles listing                                      |
| `/articulos/{slug}` | Article detail                                        |
| `/galeria`          | Gallery listing                                       |
| `/galeria/{slug}`   | Gallery detail with lightbox                          |
| `/buscar`           | Global search                                         |
| `/sitemap.xml`      | XML sitemap                                           |

## API routes

| Route                | Description                |
|----------------------|----------------------------|
| `/api/calendar`      | Calendar events JSON       |
| `/api/settings`      | Site settings JSON         |

## Environment variables

Key environment variables to configure:

| Variable        | Description                              |
|-----------------|------------------------------------------|
| `APP_NAME`      | Association name                         |
| `APP_URL`       | Application URL                          |
| `CLOUDINARY_URL`| Cloudinary connection string for images  |
| `MAIL_*`        | Email configuration for contact form     |
| `DB_*`          | Database connection settings             |

## Contributing

1. **TDD**: Write tests BEFORE implementation
2. **Strict typing**: `declare(strict_types=1)` in PHP, `strict: true` in TypeScript
3. **No `any` type** in TypeScript
4. **All code in English**: variables, functions, classes, comments, commits
5. **SOLID principles**: Apply consistently

## CI/CD

### Pipeline de integración continua

El proyecto usa GitHub Actions para CI/CD:

| Workflow | Trigger            | Descripción         |
|----------|--------------------|---------------------|
| CI       | Push/PR a main     | Tests y linting     |
| Deploy   | CI exitoso en main | Deploy a producción |

### Jobs de CI

- **test**: PHP 8.4, PostgreSQL 17, Redis 7. Ejecuta migraciones y tests en paralelo.
- **lint**: TypeScript type-check y ESLint.

### Secrets necesarios

Configurar en GitHub → Settings → Secrets and variables → Actions:

| Secret                | Descripción                            |
|-----------------------|----------------------------------------|
| `COOLIFY_WEBHOOK_URL` | URL del webhook de Coolify para deploy |

## License

Proprietary – All rights reserved.
