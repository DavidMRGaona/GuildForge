# GuildForge

Platform for wargames and role-playing games guilds with:

- **Public frontend**: Events, articles, photo galleries for visitors
- **Admin panel**: Content management for members

## Tech stack

| Layer               | Technology                                    |
|---------------------|-----------------------------------------------|
| Backend framework   | Laravel 13+                                   |
| Admin panel         | Filament 5                                    |
| Frontend bridge     | Inertia.js                                    |
| Frontend framework  | Vue 3 (Composition API with `<script setup>`) |
| Frontend language   | TypeScript (strict mode)                      |
| Build tool          | Vite 8                                        |
| Frontend runtime    | Node.js 24 LTS                                |
| CSS framework       | Tailwind CSS                                  |
| State management    | Pinia                                         |
| Database (dev/prod) | PostgreSQL 17                                 |
| Database (testing)  | SQLite in-memory (local), PostgreSQL 17 (CI)  |
| Containerization    | Docker + Docker Compose                       |

## Requirements

- Docker
- Docker Compose
- Make

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/DavidMRGaona/GuildForge.git runesword
   cd runesword
   ```

2. Copy the environment file and configure:
   ```bash
   cp src/.env.example src/.env
   ```
   Optional: `cp .env.example .env` at the repository root to change the ports Docker publishes (`APP_PORT`, `DB_PORT`, `VITE_PORT`, `MAIL_PORT`, `MAIL_SMTP_PORT`).

3. Configure your instance in `src/.env` (see [Environment variables](#environment-variables) below):
   ```env
   APP_NAME="GuildForge"
   APP_DESCRIPTION="Platform for wargames and role-playing games guilds"
   ```

4. Start the containers and run the full setup:
   ```bash
   make up
   make setup   # composer install, npm install (node container), .env, APP_KEY, migrate --seed, storage:link
   ```

5. Open http://localhost:8080. The node container already runs the Vite dev server (http://localhost:5173); `make build-assets` makes a production build.
   The seeders create three local accounts, `admin@example.local`, `editor@example.local` and `member@example.local`, all with the password `password` (only in the `local` and `testing` environments). Create other administrators with `docker exec -it guildforge_app php artisan make:admin-user`.

## Environment variables

Key environment variables to configure:

| Variable         | Description                                       |
|------------------|---------------------------------------------------|
| `APP_NAME`       | Guild name                                        |
| `APP_URL`        | Application URL                                   |
| `CLOUDINARY_URL` | Cloudinary connection string for images           |
| `MAIL_*`         | Mail configuration (contact form, account emails) |
| `DB_*`           | Database connection settings                      |

## Commands

| Command          | Description                                                         |
|------------------|---------------------------------------------------------------------|
| `make up`        | Start containers                                                    |
| `make down`      | Stop containers                                                     |
| `make setup`     | Install dependencies, create `.env` and `APP_KEY`, migrate and seed |
| `make shell`     | Enter PHP container                                                 |
| `make test`      | Run all tests                                                       |
| `make test-unit` | Run unit tests only                                                 |
| `make check`     | Run ALL linters (PHP + JS + types)                                  |
| `make fresh`     | Fresh migration with seeders                                        |

## Project structure

This project follows **Clean Architecture** principles:

```
Domain → Application → Infrastructure → Presentation
```

```
src/app/
├── Domain/           # Entities, value objects, enums, exceptions, repository interfaces
├── Application/      # DTOs (create/response), factories, query service interfaces
├── Infrastructure/   # Eloquent models, repositories, query services, external services
├── Modules/          # Module loader and ModuleServiceProvider base
├── Console/          # Artisan commands (module:*, core:*, content:convert-trix)
├── Http/             # Controllers, resources, requests, middleware
├── Filament/         # Admin resources, pages, widgets
├── Mail/             # Mailables
├── Notifications/    # Email notifications
├── Policies/         # Authorization policies
├── View/             # Blade components (SEO head, favicons, module import map)
└── Providers/        # Service providers

resources/js/
├── pages/            # Inertia pages (Home, Events, Articles, Gallery, About)
├── components/       # Vue components (ui, layout, events, articles, gallery)
├── composables/      # Vue composables
├── stores/           # Pinia stores
├── types/            # TypeScript types
├── locales/          # i18n translations
├── layouts/          # Page layouts
├── utils/            # Utility functions
└── vendor-exports/   # Vue, Pinia, vue-i18n, Inertia, Unhead exported to modules (import map)

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

Modules are not part of this repository: each one is its own GitHub repository (`DavidMRGaona/guildforge-<name>`), cloned into `src/modules/<name>`, which the core repository ignores. A fresh clone has no modules.

```bash
# Get an existing module
git clone git@github.com:DavidMRGaona/guildforge-game-tables.git src/modules/game-tables

# Inside the PHP container (make shell):
# create a new module
php artisan module:make my-module --description="My module"

# discover and enable
php artisan module:discover
php artisan module:enable my-module

# list all modules
php artisan module:list
```

For detailed module development documentation, see [docs/modules/README.md](docs/modules/README.md).

## Routes

### Public

| Route                          | Description                                         |
|--------------------------------|-----------------------------------------------------|
| `/`                            | Home with hero slider, events, articles, gallery    |
| `/nosotros`                    | About page with guild info and contact section      |
| `/contacto`                    | Contact form (POST)                                 |
| `/calendario`                  | Interactive event calendar                          |
| `/eventos`                     | Events listing                                      |
| `/eventos/{slug}`              | Event detail                                        |
| `/articulos`                   | Articles listing                                    |
| `/articulos/{slug}`            | Article detail                                      |
| `/galeria`                     | Gallery listing                                     |
| `/galeria/{slug}`              | Gallery detail with lightbox                        |
| `/buscar`                      | Global search                                       |
| `/sitemap.xml`                 | XML sitemap                                         |
| `/iniciar-sesion`, `/registro` | Login and registration                              |
| `/perfil`                      | User profile                                        |
| `/{slug}`                      | Legal pages (privacy, terms, cookies, legal notice) |
| `/admin`                       | Admin panel (Filament)                              |
| `/up`                          | Health check (also answers during maintenance mode) |

Enabled modules add their own routes.

### JSON and webhooks

| Route                      | Description                      |
|----------------------------|----------------------------------|
| `/eventos/calendario`      | Calendar events JSON             |
| `/api/webhooks/ses` (POST) | Amazon SES notifications webhook |

## Development rules

1. **TDD**: Write tests BEFORE implementation
2. **Strict typing**: `declare(strict_types=1)` in PHP, `strict: true` in TypeScript
3. **No `any` type** in TypeScript
4. **All code in English**: variables, functions, classes, comments, commits
5. **SOLID principles**: Apply consistently

## CI/CD

### Continuous integration pipeline

The project uses GitHub Actions for CI/CD:

| Workflow | Trigger           | Description          |
|----------|-------------------|----------------------|
| CI       | Push/PR to main   | Tests and linting    |
| Deploy   | CI passed on main | Deploy to production |

### CI jobs

- **test**: PHP 8.5, PostgreSQL 17, Redis 7.2. Checks PHP style (`vendor/bin/pint --test`), builds the assets, checks the module import map (`npm run verify:build`), then runs the migrations and the test suite (serially, on PostgreSQL).
- **lint**: TypeScript type-check and ESLint.

### Required secrets

Configure in GitHub → Settings → Secrets and variables → Actions:

| Secret                 | Description                                               |
|------------------------|-----------------------------------------------------------|
| `WEBHOOK_URL_SERVER_1` | Deploy URL (deployment platform API) of the first target  |
| `WEBHOOK_URL_SERVER_2` | Deploy URL (deployment platform API) of the second target |
| `DEPLOY_API_TOKEN`     | Deployment platform API token, sent as bearer token       |

## License

Proprietary – All rights reserved.
