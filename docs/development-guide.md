# Development guide

> Core 3.5.1

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
git clone https://github.com/DavidMRGaona/GuildForge.git guildforge
cd guildforge
```

### 2. Start the containers

Optional: to change the ports Docker publishes, copy the root `.env.example` to `.env` and edit `APP_PORT`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `VITE_PORT`, `MAIL_PORT` or `MAIL_SMTP_PORT` (read by `docker-compose.yml`). If you change `DB_DATABASE`, `DB_USERNAME` or `DB_PASSWORD`, set the same values in `src/.env` before the first `make up` (PostgreSQL only reads them when its volume is created). Leave `DB_PORT=5432` in `src/.env`: the root `DB_PORT` only changes the port published on the host.

```bash
make up
```

This brings up all the services defined in `docker-compose.yml`:
- **app** (PHP 8.5 FPM) - application server
- **nginx** - web server (port 8080)
- **db** - PostgreSQL 17 (port 5432)
- **node** - Node.js 24 with Vite HMR (port 5173)
- **queue** - Laravel queue worker
- **mailpit** - development mail server (web 8025, SMTP 1025)
- **redis** - Redis 7.2, unused until `src/.env` points cache, sessions or queues at it (port 6379)
- **elasticsearch** - log storage (port 9200)
- **kibana** - log visualization (port 5601)

`make up` does not rebuild existing images. After a change to `docker/php/Dockerfile` (for example a PHP upgrade), rebuild the two containers that use it: `docker compose build app queue && docker compose up -d app queue`. With an older image the application does not start: Composer's platform check stops it with "Composer detected issues in your platform".

Composer dependencies live in `src/vendor`, shared by the `app` and `queue` containers. After pulling a change to `src/composer.lock`, or switching to a branch with a different one, run `docker exec guildforge_app composer install` and `docker compose restart queue`. Until then `bootstrap/cache/packages.php` can still list service providers of packages that are gone, and the application does not boot.

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

The seeders create three accounts in the `local` and `testing` environments: `admin@example.local`, `editor@example.local` and `member@example.local`, all with the password `password`. Create other administrators with `docker exec -it guildforge_app php artisan make:admin-user`. Every account, these included, holds the base role `member`; on a database that has accounts from before core 3.8, `docker exec guildforge_app php artisan users:assign-member-role` gives it to them (`--dry-run` only counts them).

### 5. Get the modules (optional)

Modules are not part of the core repository: each one is its own GitHub repository, cloned into `src/modules/<name>` (ignored by the core repository). A fresh clone has none:

```bash
git clone https://github.com/DavidMRGaona/guildforge-game-tables.git src/modules/game-tables
docker exec guildforge_app php artisan module:discover
docker exec guildforge_app php artisan module:enable game-tables
```

Repositories are named `DavidMRGaona/guildforge-<name>`, except venue-bookings (`guildforge-venue-booking`); see the [documentation index](./index.md#module-documentation).

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
| `make recreate` | Recreate containers from the current images (no pull, no rebuild) |
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
| `make test-filter FILTER=EventTest` | Run a specific test |
| `make test-serial` | Run tests without parallelization |

### Code quality

| Command | Description |
|---------|-------------|
| `make check` | Run all linters (Rector + Pint + PHPStan + ESLint + Prettier + types) |
| `make check-fix` | Fix what can be fixed, in order: Rector, Pint, ESLint, Prettier |
| `make check-q` | Same checks with one line per tool (always exits 0: read the ✗ marks) |
| `make format` | Format all code (Pint + ESLint + Prettier) |
| `make rector` | Rector (check only) |
| `make rector-fix` | Apply Rector (run `make cs-fix` after it) |
| `make cs` | PHP code style with Pint (check only) |
| `make cs-fix` | PHP code style with Pint (fix) |
| `make phpstan` | PHP static analysis |
| `make lint-js` | ESLint for TypeScript/Vue |
| `make lint-js-fix` | Fix ESLint issues |
| `make format-check` | Prettier (check only) |
| `make format-fix` | Prettier (write) |
| `make types` | TypeScript type checking |

### Frontend

| Command | Description |
|---------|-------------|
| `make dev` | Vite server with HMR (only when the node container's own Vite is not running) |
| `make build-assets` | Production asset build (`vue-tsc` + `vite build`) |

CI also runs `npm run verify:build` after the build (`src/scripts/verify-build.mjs`: the entries of the public frontend API for modules, their exports against `resources/js/public-api.exports.json`, one chunk per vendor group, and no module sources in the core build) and, in the lint job, `npm run check:public-api` (the API's types against `resources/js/public-api.api.md`). Run them with `docker exec guildforge_node npm run verify:build` and `docker exec guildforge_node npm run check:public-api`; after an intended API change regenerate the files with `node scripts/verify-build.mjs public/build --write-snapshot` and `npm run public-api:report`, and review the diff in the pull request.

### Code generation

| Command | Description |
|---------|-------------|
| `make make-model NAME=Event` | Create model + migration + factory |
| `make make-migration NAME=create_events_table` | Create migration |
| `make make-controller NAME=EventController` | Create controller |
| `make make-resource NAME=EventResource` | Create API resource |
| `make make-request NAME=StoreEventRequest` | Create form request |
| `make make-test NAME=EventTest` | Create test |
| `make make-filament NAME=Event` | Create Filament resource with auto-generation |

These wrap Laravel's generators, which write to their default locations: move generated models to `app/Infrastructure/Persistence/Eloquent/Models/` and make Filament resources extend `App\Filament\Resources\BaseResource`.

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

`src/.env.example` ships with Elasticsearch logging off. To send logs to the `elasticsearch` container, set `ELASTICSEARCH_ENABLED=true`, `ELASTICSEARCH_HOST=elasticsearch` and add `elasticsearch` to `LOG_CHANNELS` (e.g. `LOG_CHANNELS=daily,elasticsearch`).

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

The admin panel's theme (`resources/css/filament/admin/theme.css`) is a Vite entry too, so `/admin` depends on Vite like the public site. It scans `app/Filament` and `resources/views/filament` (never `src/modules`), and Laravel reads it from the dev server while `public/hot` exists, or from `public/build/manifest.json` otherwise:

- With the Node container stopped and `public/hot` left behind, the panel loads without its styles.
- With no `public/hot` and a `public/build` compiled before the theme existed (its manifest has no theme entry), every `/admin` page answers 500 (`Unable to locate file in Vite manifest`). This happens after pulling the Filament 5 upgrade onto a checkout with an old build.

Keep the Node container running, or run `docker exec guildforge_node npx vite build` once (and again after adding Tailwind classes to panel code if you work without the dev server). `make build-assets` also runs `vue-tsc`, which is not needed for this.

If you need to restart the Vite server:

```bash
docker compose restart node
```

`make dev` starts a second Vite in the same container; it only works when the container's own Vite is not running (`vite.config.ts` sets `strictPort`, so otherwise it exits with "Port 5173 is already in use").

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

Modules live in `src/modules/`, which the core repository ignores: each module is its own Git repository, cloned there by hand (see [Get the modules](#5-get-the-modules-optional)).

```bash
# Inside the PHP container (make shell):
php artisan module:list             # List all modules
php artisan module:discover         # Discover new modules
php artisan module:enable <name>    # Enable a module
php artisan module:disable <name>   # Disable a module
php artisan module:build            # Compile module Vue assets
```

**Package a module for distribution:**

```bash
make module-zip     # Select a module and generate a ZIP with SHA-256 checksum
```

The package is generated in the `dist/` directory along with its `.sha256` verification file.

### Upgrading an existing checkout

If your volumes were created by PostgreSQL 16 or by Redis 8.x (checkouts older than the move to PostgreSQL 17 / Redis 7.2), the stack crash-loops: PostgreSQL 17 cannot open a data directory created by PostgreSQL 16, and Redis 7.2 cannot read an RDB file written by Redis 8.x. Recreate both volumes, keeping the database:

```bash
make db-backup     # while the old containers are still running
make down
docker volume ls | grep guildforge      # find the real names: <project>_guildforge_db_data, <project>_guildforge_redis_data
docker volume rm <project>_guildforge_db_data <project>_guildforge_redis_data   # use the names listed above
make up
make db-restore    # restores the latest backup into the new, empty database
make migrate       # only if the branch adds migrations newer than the backup
```

The Redis volume is not backed up: it holds the cache and, if your `.env` points sessions or queues at Redis, sessions and pending jobs, which are lost.

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

Locally, tests use **in-memory SQLite** for maximum speed. The PHPUnit configuration sets:

```
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

These `<env>` entries are not forced, so a variable already set in the environment wins: CI sets `DB_CONNECTION=pgsql` and runs the suite on PostgreSQL 17.

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

`make test` runs only the core suite (`src/tests`). Module tests live in each module and run with the module's own `phpunit.xml`:

```bash
docker exec -e XDEBUG_MODE=off guildforge_app vendor/bin/phpunit -c modules/game-tables/phpunit.xml
```

`tests/bootstrap.php` (which the module suites load too) stops the run when Xdebug is in `develop` mode, the container's default: in that mode a Filament action test can pass without running the action, depending on the tests that ran before. The `make test*` targets already pass `XDEBUG_MODE=off` (`test-coverage` passes `XDEBUG_MODE=coverage`); pass it yourself when calling PHPUnit directly, also on the host.

### PHPUnit configuration

The `src/phpunit.xml` file disables external services during tests:

- `PULSE_ENABLED=false`
- `TELESCOPE_ENABLED=false`
- `NIGHTWATCH_ENABLED=false`
- `LOG_CHANNEL=null`
- `BCRYPT_ROUNDS=4` (faster hashing)
- `memory_limit=512M`

PHPUnit notices and deprecations fail the suite (`failOnPhpunitNotice`, `failOnPhpunitDeprecation`). Use `createStub()` for a test double without expectations and `createMock()` only when the test calls `expects()`; use attributes (`#[DataProvider]`, `#[Group]`) instead of doc-comment annotations, which PHPUnit ignores since version 12. With `--compact` the summary only says "PHPUnit Notices: N": run `vendor/bin/phpunit --display-phpunit-notices <file>` to see them. Module `phpunit.xml` files set the same two options.

---

## Linting and formatting

PHP tools run in this order: **Rector → Pint → PHPStan**. Rector's output is not Pint-clean (spacing, imports, braces), so Pint always runs after it; once Pint has run, Rector finds nothing to change. CI runs `rector process --dry-run` before `pint --test`. After editing PHP in the core, run Rector and then Pint on the files you touched.

### Rector (PHP refactoring)

Uses `src/rector.php` over `app/`, `database/` and `tests/` (migrations and `tests/Fixtures` are skipped by pattern, so the same skips hold for a module's `database/` and `tests/`): PHP 8.5 syntax, dead code and code quality sets. A rule that does not fit the project is skipped there with `withSkip()` and a comment, rather than worked around in the code. CI fails if Rector would change anything:

```bash
make rector       # Check (dry-run)
make rector-fix   # Apply, then make cs-fix
```

### Pint (PHP code style)

Uses `laravel/pint` with the `laravel` preset plus `declare_strict_types` (`src/pint.json`); `modules/` is excluded from the core's run because each module is its own repository, and each module is checked with the same rules by its own CI (`make check MODULE=<module>` locally, see [module CI/CD](module-ci-cd.md#pull-request-checks)). CI fails on any style difference (`vendor/bin/pint --test`):

```bash
make cs       # Check (dry-run)
make cs-fix   # Auto-fix
```

### PHPStan (static analysis)

Uses `larastan/larastan` (PHPStan + Laravel rules) for static analysis at level 8 over `app/`, `database/` and `tests/`; CI fails on any error. Rector (`rector.php`) covers the same directories, except migrations, which already ran on live sites, and `tests/Fixtures`. `phpstan/phpstan-mockery` lets it see a Mockery mock as the class it mocks, and `phpstan/phpstan-phpunit` (its `extension.neon` only) narrows types after PHPUnit assertions, so an assertion that can never fail is an error. Modules run the same configuration (`phpstan-modules.neon` includes `phpstan.neon`). Tests fix the errors in the code, without a baseline:

- A test that reads a command's output or exit code calls `$this->artisanCommand()`, which always returns a `PendingCommand` (`artisan()` may return the exit code)
- `json_encode()` takes `JSON_THROW_ON_ERROR`, and files are read with `self::readFile()` (`Tests\Support\Files\ReadsFiles`), never through a `(string)` cast
- Spy on a facade through the instance `Log::spy()` returns (`$log->shouldHaveReceived(...)`); type PHPUnit doubles as an intersection (`Interface&Stub`, `Interface&MockObject`)
- A test that only checks nothing is thrown says so with `expectNotToPerformAssertions()`, never `assertTrue(true)`
- No assertion of what the types already guarantee (`assertInstanceOf()` on a typed value, an enum's case count, `assertSame()` of two literals, `assertArrayHasKey()` on a fixed shape): check the value, or the wiring (the listener is queued, the container resolves the binding), or drop it. When a match must succeed before its groups are read, `if (preg_match(...) !== 1) { $this->fail(...); }` lets PHPStan read them without a `?? ''`
- An inline `@phpstan-ignore <identifier> (reason)` only where a test breaks a type on purpose

```bash
make phpstan
```

### ESLint (TypeScript/Vue)

Checks `.ts` and `.vue` files in `resources/js/`; a warning fails like an error (`--max-warnings=0`), in CI and in modules' `test / Quality` alike:

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

Formats `.ts` and `.vue` files in `resources/js/`; CI fails on any difference (`npm run format:check`):

```bash
make format-check   # Check
make format-fix     # Write
make format         # Pint + ESLint fix + Prettier
```

### Check everything at once

```bash
make check     # Pint + PHPStan + ESLint + TypeScript
make check-q   # One line per tool; always exits 0, so read the ✗ marks
```

### Pre-commit hook

Installs a Git hook that runs checks before each commit:

```bash
make hooks     # Installs the hook in .git/hooks/pre-commit
```

The hook runs: Pint + PHPStan + TypeScript type checking.

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

> **Note**: each module is its own Git repository (not a submodule). Tags and pushes go to its own remote.

---

## Development without Docker

The JS `make` targets call the npm scripts in `src/package.json`. Without Docker:

```bash
composer dev    # Starts server (http://localhost:8000), worker, Pail, and Vite simultaneously
npm run dev     # Vite server with HMR only
npm run build   # Type checking + production build
```

> **Note**: requires PHP 8.5, Node 24 (22.13+ also works, see `engines` in `src/package.json`), and PostgreSQL 17 (Redis only if `.env` uses it). See `src/composer.json` and `src/package.json` for the full list of scripts.
