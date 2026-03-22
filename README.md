# GuildForge

Platform for wargames and role-playing games guilds with:

- **Public frontend**: Events, articles, photo galleries for visitors
- **Admin panel**: Content management for members

## Tech stack

| Layer               | Technology                                    |
|---------------------|-----------------------------------------------|
| Backend framework   | Laravel 12+                                   |
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

3. Configure your instance in `.env` (see [Environment variables](#environment-variables) below):
   ```env
   APP_NAME="GuildForge"
   APP_DESCRIPTION="Platform for wargames and role-playing games guilds"
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

## Environment variables

Key environment variables to configure:

| Variable        | Description                              |
|-----------------|------------------------------------------|
| `APP_NAME`      | Guild name                               |
| `APP_URL`       | Application URL                          |
| `CLOUDINARY_URL`| Cloudinary connection string for images  |
| `MAIL_*`        | Email configuration for contact form     |
| `DB_*`          | Database connection settings             |

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

This project follows **Clean Architecture** principles:

```
Domain → Application → Infrastructure → Presentation
```

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

- **Domain**: Business entities, value objects, and repository interfaces
- **Application**: DTOs (Create/Response), factories, query service interfaces
- **Infrastructure**: Eloquent implementations, query services, external services
- **Presentation**: Controllers, HTTP resources, Filament resources, Vue components

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

## Routes

### Public

| Route               | Description                                           |
|---------------------|-------------------------------------------------------|
| `/`                 | Home with hero slider, events, articles, gallery      |
| `/nosotros`         | About page with guild info and contact section        |
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

### API

| Route                | Description                |
|----------------------|----------------------------|
| `/api/calendar`      | Calendar events JSON       |
| `/api/settings`      | Site settings JSON         |

## Development rules

1. **TDD**: Write tests BEFORE implementation
2. **Strict typing**: `declare(strict_types=1)` in PHP, `strict: true` in TypeScript
3. **No `any` type** in TypeScript
4. **All code in English**: variables, functions, classes, comments, commits
5. **SOLID principles**: Apply consistently

## CI/CD

### Continuous integration pipeline

The project uses GitHub Actions for CI/CD:

| Workflow | Trigger            | Description          |
|----------|--------------------|----------------------|
| CI       | Push/PR to main    | Tests and linting    |
| Deploy   | CI passed on main  | Deploy to production |

### CI jobs

- **test**: PHP 8.4, PostgreSQL 17, Redis 7. Runs migrations and tests in parallel.
- **lint**: TypeScript type-check and ESLint.

### Required secrets

Configure in GitHub → Settings → Secrets and variables → Actions:

| Secret                | Description                          |
|-----------------------|--------------------------------------|
| `COOLIFY_WEBHOOK_URL` | Coolify webhook URL for deployments  |

## License

Proprietary – All rights reserved.
