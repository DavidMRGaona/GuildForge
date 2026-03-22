# Development guide

> Generated: 2026-03-21 | Project: GuildForge v1.0.0

Complete guide to set up, develop, and maintain the GuildForge project in a local environment.

---

## Prerequisites

Before getting started, make sure you have the following tools installed:

| Tool | Minimum version | Description |
|------|----------------|-------------|
| Docker | 24+ | Container engine |
| Docker Compose | 2.0+ | Container orchestration |
| Make | 3.8+ | Task automation |
| Git | 2.30+ | Version control |

There is no need to install PHP, Node.js, Composer, or npm on your local machine. Everything runs inside Docker containers.

---

## Installation

Follow these steps to get the project up and running from scratch:

### 1. Clone the repository

```bash
git clone <url-del-repositorio> runesword
cd runesword
```

### 2. Start the containers

```bash
make up
```

This brings up all the services defined in `docker-compose.yml`:
- **app** (PHP 8.4 FPM) - application server
- **nginx** - web server (port 8080)
- **db** - PostgreSQL 16 (port 5432)
- **node** - Node.js 22 with Vite HMR (port 5173)
- **queue** - Laravel queue worker
- **mailpit** - development mail server (port 8025)
- **redis** - cache and sessions (port 6379)
- **elasticsearch** - search engine and logging (port 9200)
- **kibana** - log visualization (port 5601)

### 3. Full initial setup

```bash
make setup
```

This command automatically runs:
1. PHP dependency installation (`composer install`)
2. Node dependency installation (`npm install`)
3. Copy `.env.example` to `.env`
4. Application key generation (`APP_KEY`)
5. Run migrations with seeders
6. Create the storage symbolic link

### 4. Verify the installation

Access the following URLs:

| Service | URL |
|---------|-----|
| Application | http://localhost:8080 |
| Vite (HMR) | http://localhost:5173 |
| Mailpit | http://localhost:8025 |
| Elasticsearch | http://localhost:9200 |
| Kibana | http://localhost:5601 |

---

## Essential commands

All commands are executed through `make` and run inside Docker containers.

### Docker

| Command | Description |
|---------|-------------|
| `make up` | Start all containers |
| `make down` | Stop all containers |
| `make build` | Rebuild containers (no cache) |
| `make restart` | Restart all containers |
| `make recreate` | Recreate containers (pull new images) |
| `make logs` | View logs from all containers |
| `make ps` | Show container status |

### Container access

| Command | Description |
|---------|-------------|
| `make shell` | Enter the PHP container |
| `make shell-node` | Enter the Node container |
| `make shell-db` | Enter the PostgreSQL console |

### Database

| Command | Description |
|---------|-------------|
| `make migrate` | Run pending migrations |
| `make migrate-rollback` | Roll back the last migration |
| `make seed` | Run seeders |
| `make fresh` | Fresh migration with seeders (deletes all data) |
| `make db-reset` | Drop the database and recreate it with seeders |
| `make db-backup` | Create a backup in `./backups/` |
| `make db-restore` | Restore from the latest backup |

### Testing

| Command | Description |
|---------|-------------|
| `make test` | Run all tests (parallel, 8 processes) |
| `make test-q` | Run tests with compact output |
| `make test-unit` | Unit tests only |
| `make test-feature` | Feature tests only |
| `make test-coverage` | Tests with HTML coverage report |
| `make test-filter FILTER=NombreTest` | Run a specific test |
| `make test-serial` | Run tests without parallelization |

### Code quality

| Command | Description |
|---------|-------------|
| `make check` | Run all linters (CS Fixer + PHPStan + ESLint + types) |
| `make check-q` | Checks with minimal output |
| `make format` | Format all code (CS Fixer + ESLint + Prettier) |
| `make cs` | PHP CS Fixer (check only) |
| `make cs-fix` | PHP CS Fixer (fix) |
| `make phpstan` | PHP static analysis |
| `make lint-js` | ESLint for TypeScript/Vue |
| `make lint-js-fix` | Fix ESLint issues |
| `make types` | TypeScript type checking |

### Frontend

| Command | Description |
|---------|-------------|
| `make dev` | Vite server with HMR |
| `make build-assets` | Production asset build |

### Code generation

| Command | Description |
|---------|-------------|
| `make make-model NAME=Evento` | Create model + migration + factory |
| `make make-migration NAME=create_eventos_table` | Create migration |
| `make make-controller NAME=EventoController` | Create controller |
| `make make-resource NAME=EventoResource` | Create API resource |
| `make make-request NAME=StoreEventoRequest` | Create form request |
| `make make-test NAME=EventoTest` | Create test |
| `make make-filament NAME=Evento` | Create Filament resource with auto-generation |

### Laravel

| Command | Description |
|---------|-------------|
| `make cache` | Create config, route, and view caches |
| `make cache-clear` | Clear all caches |
| `make routes` | List all routes |
| `make tinker` | Open Tinker console |
| `make ide-helper` | Generate IDE helpers |

### Elasticsearch

| Command | Description |
|---------|-------------|
| `make es-test` | Test Elasticsearch connection |
| `make es-logs` | View recent container logs |
| `make es-health` | Check cluster health |
| `make es-indices` | List all indices |

### Git hooks

| Command | Description |
|---------|-------------|
| `make hooks` | Install pre-commit hook |
| `make pre-commit` | Run pre-commit checks (CS + PHPStan + types) |

### Local production

| Command | Description |
|---------|-------------|
| `make prod-build` | Build production image |
| `make prod-build-clean` | Build image without cache |
| `make prod-up` | Start local production environment (port 8000) |
| `make prod-down` | Stop local production environment |
| `make prod-logs` | View local production logs |
| `make prod-shell` | Enter the production container |
| `make prod-fresh` | Fresh migration in local production |
| `make prod-ps` | Production container status |
| `make prod-update` | Update local production (preserves data) |
| `make prod-reset` | Full reset (deletes all data and volumes) |

### Modules

| Command | Description |
|---------|-------------|
| `make module-zip` | Package a module as a production ZIP |

---

## Local development

### Start the containers

```bash
make up
```

The Node container automatically runs `npm install && npm run dev -- --host 0.0.0.0`, which starts the Vite server with hot module replacement (HMR) on port 5173.

### Access the application

The application is available at **http://localhost:8080** through Nginx, which proxies to PHP-FPM.

### Hot reload (Vite)

Vite runs automatically inside the Node container when starting the containers with `make up`. Changes to `.vue`, `.ts`, and `.css` files are reflected instantly in the browser without needing to reload the page.

If you need to restart the Vite server manually:

```bash
make dev
```

### Queue worker

The `queue` container automatically runs the Laravel queue worker:

```
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

The worker processes queued jobs (email sending, asynchronous tasks, etc.) and restarts automatically if it fails.

### Database management

**Migrations:**

```bash
make migrate            # Run pending migrations
make migrate-rollback   # Roll back the last migration
make fresh              # Fresh migration (drops and recreates everything)
```

**Backups:**

```bash
make db-backup     # Creates a SQL file in ./backups/ with date and time
make db-restore    # Restores from the most recent backup
```

**Direct PostgreSQL access:**

```bash
make shell-db      # Opens an interactive psql session
```

### Module management

Modules are extensible features located in `src/modules/`. Each module is an independent Git repository (submodule).

```bash
# Inside the PHP container (make shell):
php artisan module:list         # List all modules
php artisan module:discover     # Discover new modules
php artisan module:enable       # Enable a module
php artisan module:disable      # Disable a module
php artisan module:build        # Compile module Vue assets
```

**Package a module for distribution:**

```bash
make module-zip     # Select a module and generate a ZIP with SHA-256 checksum
```

The package is generated in the `dist/` directory along with its `.sha256` verification file.

---

## Testing

### Test suites

The project organizes tests into three suites, defined in `src/phpunit.xml`:

| Suite | Directory | Content |
|-------|-----------|---------|
| Unit | `tests/Unit/` | Domain entities, value objects, DTOs |
| Integration | `tests/Integration/` | Eloquent persistence, infrastructure services |
| Feature | `tests/Feature/` | HTTP controllers, end-to-end flows |

### Test database

Tests use **in-memory SQLite** for maximum speed. The PHPUnit configuration automatically sets:

```
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Other test settings: array cache, synchronous queue, array sessions, array mailer.

### Running tests

```bash
make test             # All tests in parallel (8 processes, 512M memory)
make test-q           # Compact output
make test-unit        # Unit tests only
make test-feature     # Feature tests only
make test-serial      # Without parallelization (useful for debugging)

# Specific test:
make test-filter FILTER=EventTest

# With coverage:
make test-coverage    # Generates HTML report in src/coverage/
```

### PHPUnit configuration

The `src/phpunit.xml` file disables external services during tests:

- `PULSE_ENABLED=false`
- `TELESCOPE_ENABLED=false`
- `NIGHTWATCH_ENABLED=false`
- `LOG_CHANNEL=null`
- `BCRYPT_ROUNDS=4` (faster hashing)
- `memory_limit=512M`

---

## Linting and formatting

### PHP CS Fixer (PHP code style)

Uses `friendsofphp/php-cs-fixer` to check and fix PHP code style:

```bash
make cs       # Check (dry-run)
make cs-fix   # Auto-fix
```

### PHPStan (static analysis)

Uses `larastan/larastan` (PHPStan + Laravel rules) for static analysis:

```bash
make phpstan
```

### ESLint (TypeScript/Vue)

Checks `.ts` and `.vue` files in `resources/js/`:

```bash
make lint-js       # Check
make lint-js-fix   # Auto-fix
```

### TypeScript (type checking)

Runs `vue-tsc --noEmit` to check types without generating files:

```bash
make types
```

### Prettier (frontend formatting)

Formats `.ts` and `.vue` files:

```bash
# Included in make format:
make format   # Runs CS Fixer + ESLint fix + Prettier
```

### Check everything at once

```bash
make check     # CS Fixer + PHPStan + ESLint + TypeScript
make check-q   # Same checks with minimal output (for CI)
```

### Pre-commit hook

Installs a Git hook that runs checks before each commit:

```bash
make hooks     # Installs the hook in .git/hooks/pre-commit
```

The hook runs: CS Fixer + PHPStan + TypeScript type checking.

---

## Modules

Modules are extensible features in `src/modules/`. For the complete development guide, see [docs/modules/README.md](./modules/README.md).

### Essential commands

| Command | Description |
|---------|-------------|
| `php artisan module:list` | List all modules and their status |
| `php artisan module:discover` | Discover new modules |
| `php artisan module:enable <name>` | Enable a module |
| `php artisan module:disable <name>` | Disable a module |
| `make module-zip` | Package a module as a production ZIP |

> **Note**: modules are independent Git repositories (submodules). Tags and pushes go to their own remote.

---

## Development without Docker

The `make` commands are wrappers for the scripts defined in `src/composer.json` and `src/package.json`. For development without Docker:

```bash
composer dev    # Starts server, worker, Pail, and Vite simultaneously
npm run dev     # Vite server with HMR only
npm run build   # Type checking + production build
```

> **Note**: requires PHP 8.4, Node 22, PostgreSQL 16, and Redis installed locally. See `src/composer.json` and `src/package.json` for the full list of scripts.
