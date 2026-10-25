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
   git clone https://github.com/DavidMRGaona/GuildForge.git guildforge
   cd guildforge
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

| Variable                       | Description                                                                                                                                  |
|--------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `APP_NAME`                     | Guild name                                                                                                                                   |
| `APP_URL`                      | Application URL                                                                                                                              |
| `CLOUDINARY_URL`               | Cloudinary connection string for images                                                                                                      |
| `MAIL_*`                       | Mail configuration (contact form, account emails)                                                                                            |
| `DB_*`                         | Database connection settings                                                                                                                 |
| `APP_DISPLAY_TIMEZONE`         | Zone of every date people see and type, and of the scheduler (default `Europe/Madrid`)                                                       |
| `APP_STORAGE_TIMEZONE_DEFAULT` | Zone the datetime columns are stored in (`Europe/Madrid` or `UTC`) while the site has no `storage_timezone` setting; default `Europe/Madrid` |

### Storage and display timezones

Dates are stored in the *storage zone* and shown in the *display zone*. The storage zone is the `storage_timezone` setting (`Europe/Madrid` or `UTC`); without it, `APP_STORAGE_TIMEZONE_DEFAULT` applies. Sites that already held data when the setting was introduced get `Europe/Madrid` written by a migration, so changing that variable never reinterprets their dates; a fresh install has no setting and follows the variable. The application refuses to boot when the setting cannot be read.

Moving an existing site to UTC is a maintenance task, PostgreSQL only. Maintenance mode only holds back visitors: the queue worker and the scheduler keep running with the zone they started with, and panel users get past it, so:

1. Turn maintenance mode on and make sure nobody edits anything in the panel until the end.
2. Stop the queue worker and the scheduler (the command cannot stop them).
3. `php artisan timezone:convert-storage --dry-run` counts what would change and lists anything that would stop the run.
4. `php artisan timezone:convert-storage` converts (`--reverse` converts back to `Europe/Madrid`).
5. Start the queue worker and the scheduler again.
6. Turn maintenance mode off.

The command converts every `timestamp without time zone` column of the core and the modules (never `date`, `time`, JSON or `timestamptz` columns, which it lists as already zone-aware) and writes the setting in the same transaction, so it can be repeated safely. It refuses to run outside maintenance mode, on a database other than PostgreSQL, while queued jobs exist, while a timestamp column has a database default it does not know, and, when converting to UTC (not with `--reverse`), while an installed module is not marked `"storesUtc": true`. Afterwards it clears the settings cache and signals the queue workers to restart, so start them again (step 5) once the command ends.

Things to know before converting:

- A wall time that does not exist (02:xx on the last Sunday of March, when clocks spring forward) is read as 03:xx, so it can collide with a real 03:xx value on a unique index and abort the run. The run is atomic: nothing changes, and the error names the index.
- Once the data is in UTC, log timestamps and the daily log file boundary follow UTC too.
- The health endpoint (`/up`) fails while the database is unreachable: the storage zone is read at boot and the application refuses to start without it.
- Turn maintenance mode on and off through the panel or the settings service. The command reads the setting fresh, so a change made straight in the database is honoured as well.

## Commands

| Command          | Description                                                         |
|------------------|---------------------------------------------------------------------|
| `make up`        | Start containers                                                    |
| `make down`      | Stop containers                                                     |
| `make setup`     | Install dependencies, create `.env` and `APP_KEY`, migrate and seed |
| `make shell`     | Enter PHP container                                                 |
| `make test`      | Run all tests                                                       |
| `make test-unit` | Run unit tests only                                                 |
| `make check`     | Run ALL linters (Rector, Pint, PHPStan, ESLint, Prettier, types)    |
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
├── Console/          # Artisan commands (module:*, core:*, content:convert-trix, users:assign-member-role)
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

Modules are not part of this repository: each one is its own GitHub repository (`DavidMRGaona/guildforge-<name>`; venue-bookings: `guildforge-venue-booking`), cloned into `src/modules/<name>`, which the core repository ignores. A fresh clone has no modules.

```bash
# Get an existing module
git clone https://github.com/DavidMRGaona/guildforge-game-tables.git src/modules/game-tables

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

| Workflow | Trigger           | Description                                                      |
|----------|-------------------|------------------------------------------------------------------|
| CI       | Push/PR to main   | Tests and linting                                                |
| Deploy   | CI passed on main | Deploy to production: canary first, the rest once it is healthy |

### CI jobs

- **test**: PHP 8.5, PostgreSQL 17, Redis 7.2. Checks Rector (`vendor/bin/rector process --dry-run`), PHP style (`vendor/bin/pint --test`) and static analysis (`vendor/bin/phpstan analyse`, PHPStan level 8), builds the assets, checks the module import map (`npm run verify:build`), then runs the migrations and the test suite (serially, on PostgreSQL).
- **lint**: TypeScript type-check, ESLint, Prettier (`npm run format:check`) and Vitest.

### Required secrets

Configure in GitHub → Settings → Secrets and variables → Actions:

| Secret                 | Description                                               |
|------------------------|-----------------------------------------------------------|
| `WEBHOOK_URL_SERVER_1` | Deploy URL (deployment platform API) of the first target  |
| `WEBHOOK_URL_SERVER_2` | Deploy URL (deployment platform API) of the second target |
| `DEPLOY_API_TOKEN`     | Deployment platform API token, sent as bearer token       |

Repository secrets used by the canary (same page):

| Secret                 | Description                                                       |
|------------------------|-------------------------------------------------------------------|
| `CANARY_RESOURCE_UUID` | `uuid=` value of the canary's deploy URL (must match exactly one) |
| `CANARY_HEALTH_URL`    | Canary health URL (`/up`)                                         |

If the canary fails, the other targets are not deployed: fix and merge, or rerun Deploy from the Actions tab. `skip_canary` (manual run) deploys every target at once, for emergencies only. See `docs/deployment-guide.md`.

## License

Proprietary – All rights reserved.
