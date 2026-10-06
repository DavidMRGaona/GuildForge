# Deployment guide

> Generated: 2026-03-21 | Last updated: 2026-10-05

Complete guide to deploy, operate, and monitor GuildForge in production.

---

## Production architecture

### Container stack

The production environment is based on a multi-stage Docker image that includes Nginx, PHP-FPM, a queue worker and the scheduler, all managed by Supervisord within a single container.

The table below is prod-local (`docker-compose.prod.yml`). Production runs on Coolify with a managed PostgreSQL and Redis shared by both tenants: see [Deployment with Coolify](#deployment-with-coolify).

| Container | Image | Port | Function |
|-----------|-------|------|----------|
| `guildforge_app_prod` | Dockerfile.prod (PHP 8.5 FPM Alpine) | 8000 | Application (Nginx + PHP-FPM + queue worker + scheduler) |
| `guildforge_db_prod` | postgres:17-alpine | - | PostgreSQL database |
| `guildforge_redis_prod` | redis:7.2-alpine | - | Cache and sessions |

In the development environment, the following are also included:

| Container | Image | Port | Function |
|-----------|-------|------|----------|
| `guildforge_elasticsearch` | elasticsearch:9.2.4 | 9200 | Search engine and logging |
| `guildforge_kibana` | kibana:9.2.4 | 5601 | Log visualization |
| `guildforge_mailpit` | axllent/mailpit:v1.31 | 8025 | Development mail server |

### Network diagram

```
                         Internet
                            |
                      [Reverse proxy / Coolify]
                            |
                    +-------+-------+
                    |               |
             Puerto 8000     Puerto 443 (SSL)
                    |               |
            +-------+-------+
            |  guildforge_app_prod |
            |  +--------------+   |
            |  |    Nginx     |   |
            |  |   :8000      |   |
            |  +------+-------+   |
            |         |           |
            |  +------+-------+   |
            |  |   PHP-FPM    |   |
            |  |   :9000      |   |
            |  +--------------+   |
            |                     |
            |  +--------------+   |
            |  | Queue worker |   |
            |  +--------------+   |
            +----------+----------+
                       |
            +----------+----------+
            |                     |
    +-------+-------+    +-------+-------+
    | guildforge_db  |    | guildforge    |
    |   PostgreSQL   |    |    Redis      |
    |    :5432       |    |    :6379      |
    +----------------+    +---------------+
```

All containers communicate through the `guildforge_prod` Docker network (bridge driver). Only the application container exposes its port to the host.

---

## Docker

### Production Dockerfile

The `Dockerfile.prod` file uses a multi-stage build to optimize image size and security:

**Stage 1: `assets` (Node 24 Alpine)**
- Installs npm dependencies (`npm ci`)
- Compiles frontend assets (`npx vite build`; types are checked by CI, which every deployment waits for)
- Compiles module assets that have `package.json` and `vite.config.ts` with `npm ci` and `npm run build`; a module that fails to install or build fails the image. Coolify builds carry no modules (they live outside the host repository), so this only does work in prod-local
- Consolidates module build artifacts for copying to the final stage

**Stage 2: `final` (PHP 8.5 FPM Alpine 3.24)**
- Installs system dependencies (Nginx, Supervisor, image libraries)
- Installs PHP extensions: `pdo_pgsql`, `pgsql`, `gd`, `zip`, `bcmath`, `intl`, `mbstring`, `exif`, `pcntl`, plus `redis` 6.3.0 and `imagick` 3.8.1 from PECL (pinned versions)
- OPcache is compiled into PHP 8.5, so it is not in that list. Do not add `zend_extension=opcache`: PHP would print "Failed loading Zend extension" on every start. The build fails if `php -m` or `php-fpm -m` does not list `Zend OPcache`
- Installs Composer and production PHP dependencies (`--no-dev --optimize-autoloader`)
- Copies source code and compiled assets from stage 1
- Copies compiled module assets to their corresponding directories
- Backs up modules to `/opt/modules-image` (Docker volumes mask the modules directory)
- Configures Nginx, Supervisord, and PHP with production files
- Exposes port 8000

### docker-compose.prod.yml

Defines three services for local production simulation:

```bash
make prod-up       # Start (http://localhost:8000)
make prod-down     # Stop
make prod-logs     # View logs
make prod-ps       # Status
```

**Services:**

| Service | Configuration |
|---------|---------------|
| `app-prod` | Image from `Dockerfile.prod`, env from `.env.prod.local`, volumes for storage and modules |
| `db-prod` | PostgreSQL 17 Alpine, database `guildforge_prod`, healthcheck with `pg_isready` |
| `redis-prod` | Redis 7.2 Alpine, healthcheck with `redis-cli ping` |

**Persistent volumes:**
- `guildforge_prod_db_data` - PostgreSQL data
- `guildforge_prod_storage` - Laravel storage (logs, cache, sessions, uploads)
- `guildforge_prod_redis_data` - Redis data
- `guildforge_prod_modules` - installed modules

### Production entrypoint

The `docker/prod/entrypoint.sh` script runs when the container starts:

1. Creates required directories (storage, bootstrap/cache, modules, public/build/modules)
2. Syncs modules from the image (`php artisan module:sync-from-image`)
3. Publishes module build assets (`php artisan module:publish-build-assets`)
4. Sets permissions (775 for storage, bootstrap/cache, modules, public/build)
5. Creates the storage symbolic link if it does not exist
6. Opens the deployment log entry (`php artisan core:record-deployment start`)
7. Runs pending migrations (`php artisan migrate --force`); on failure it records `fail` and stops
8. Discovers modules (`php artisan module:discover`) and syncs permissions (`php artisan permissions:sync`)
9. Caches configuration, clears routes and caches views; on failure it records `fail` and stops
10. Closes the deployment log entry (`php artisan core:record-deployment finish`)
11. Starts Supervisord

### Supervisord

Manages four processes inside the production container:

| Process | Command | Configuration |
|---------|---------|---------------|
| nginx | `nginx -g "daemon off;"` | Auto-start, auto-restart |
| php-fpm | `php-fpm` | Auto-start, auto-restart |
| queue-worker | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` | 1 process, www-data user, stop timeout 3600s, no `--force` (pauses under `artisan down`) |
| scheduler | `php artisan schedule:work` | www-data user (runs `CheckModuleUpdatesJob` daily at 04:00 and module tasks) |

`supervisorctl` talks to supervisord through `/run/supervisord.sock`:

```bash
docker exec <container> supervisorctl status
docker exec <container> supervisorctl restart php-fpm
docker exec <container> supervisorctl stop 'queue-worker:*' scheduler   # e.g. before a database dump
docker exec <container> supervisorctl start 'queue-worker:*' scheduler
```

At startup supervisord logs `CRIT Server 'unix_http_server' running without any HTTP authentication checking`. This is expected, not a failure: the socket is owned by root with mode `0700`, so only root inside the container can use it.

---

## CI/CD

### GitHub Actions

The project uses two main workflows and one reusable workflow:

#### 1. CI (`ci.yml`)

Runs on every push or pull request to `main`.

**Job `test`:**
- Services: PostgreSQL 17, Redis 7.2
- Steps: checkout, setup PHP 8.4, composer install, setup Node 24, npm ci, build assets, prepare `.env`, run migrations, run tests

> **Note on PostgreSQL versions:** CI, development and prod-local use PostgreSQL 17, the major version production runs. Migrations use Laravel's schema builder, so they also run on SQLite (tests).

**Job `lint`:**
- Runs in parallel with `test`
- Steps: checkout, setup Node 24, npm ci, TypeScript type checking, ESLint

#### 2. Deploy (`deploy.yml`)

Runs automatically when the CI workflow completes successfully on the `main` branch.

**Strategy:**
- Runs deployments in parallel to multiple servers (`WEBHOOK_URL_SERVER_1`, `WEBHOOK_URL_SERVER_2`)
- Each deployment sends a POST to the Coolify webhook with an authentication token

**Full pipeline:**

```
Push a main
    ↓
CI: test (PHP + PostgreSQL + Redis)    ←── en paralelo ──→    CI: lint (TypeScript + ESLint)
    ↓                                                              ↓
    +──────────────── Ambos exitosos ──────────────────────────────+
                              ↓
                    Deploy: webhook a Coolify
                    (servidor 1 + servidor 2 en paralelo)
```

#### 3. Module release (`reusable-module-release.yml`)

Reusable workflow for publishing modules as ZIP packages in GitHub Releases.

**Stages:**
1. **Validate**: verifies that `module.json` exists, the version is valid semver and matches the tag, and `requires.core` (and any `filament`, `php`, `laravel` constraint) is present and valid
2. **Test**: runs the module's PHPUnit suite inside the host application checked out at `host_ref` (default `main`); see `docs/module-ci-cd.md`
3. **Release**: compiles Vue assets (if the module has them), creates ZIP, generates changelog, publishes GitHub Release with ZIP, SHA-256 checksum and `module.json`

### Required secrets

| Secret | Description |
|--------|-------------|
| `WEBHOOK_URL_SERVER_1` | Coolify webhook URL (server 1) |
| `WEBHOOK_URL_SERVER_2` | Coolify webhook URL (server 2) |
| `DEPLOY_API_TOKEN` | Authentication token for webhooks |
| `GITHUB_TOKEN` | Automatic GitHub token (for releases) |

---

## Environment variables

### Required variables in production

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_NAME` | Application name | `GuildForge` |
| `APP_ENV` | Runtime environment | `production` |
| `APP_KEY` | Encryption key (generate with `php artisan key:generate`) | `base64:...` |
| `APP_DEBUG` | Debug mode (always `false` in production) | `false` |
| `APP_URL` | Public application URL | `https://guildforge.example.com` |
| `APP_LOCALE` | Default language | `es` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | PostgreSQL host | `db-prod` |
| `DB_PORT` | PostgreSQL port | `5432` |
| `DB_DATABASE` | Database name | `guildforge_prod` |
| `DB_USERNAME` | Database user | `guildforge_prod` |
| `DB_PASSWORD` | Database password | (secret) |
| `REDIS_HOST` | Redis host | `redis-prod` |
| `REDIS_PORT` | Redis port | `6379` |
| `REDIS_PASSWORD` | Redis password | (secret or `null`) |
| `REDIS_PREFIX` | Redis key prefix | `guildforge_` |
| `REDIS_CACHE_DB` | Redis database of the cache store; each tenant sharing a Redis needs a different value | `1` |
| `CACHE_STORE` | Cache driver | `database` |
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `SESSION_DRIVER` | Session driver | `database` |

> **Note on Redis vs database:** the default configuration uses `database` for cache, queues, and sessions, which simplifies deployment and does not require Redis to be available. For better performance in production, you can switch to `redis` for all three variables (`CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`), since Redis is part of the production stack.

| `SESSION_SECURE_COOKIE` | HTTPS-only cookies | `true` |
| `TRUSTED_PROXIES` | Trusted proxy IPs (do not use `*` in production) | `192.168.1.0/24` |
| `MAIL_MAILER` | Mail driver | `resend` or `ses` |
| `MAIL_FROM_ADDRESS` | Sender address | `noreply@guildforge.example.com` |
| `MAIL_FROM_NAME` | Sender name | `GuildForge` |
| `CLOUDINARY_URL` | Cloudinary connection URL | `cloudinary://KEY:SECRET@CLOUD` |
| `VITE_CLOUDINARY_CLOUD_NAME` | Cloudinary cloud name | `tu-cloud-name` |
| `CLOUDINARY_PREFIX` | Cloudinary file prefix | `guildforge` |
| `ELASTICSEARCH_ENABLED` | Enable Elasticsearch logging | `true` |
| `ELASTICSEARCH_HOST` | Elasticsearch host | `elasticsearch` |
| `ELASTICSEARCH_PORT` | Elasticsearch port | `9200` |
| `ELASTICSEARCH_INDEX` | Log index name | `guildforge-logs` |
| `ELASTICSEARCH_SSL` | Use SSL for Elasticsearch | `true` or `false` |
| `BOT_PROTECTION_ENABLED` | Bot protection | `true` |
| `BCRYPT_ROUNDS` | Hashing rounds (12 recommended for production) | `12` |
| `MODULES_PATH` | Module path relative to the application | `modules` |

### Secret management

- **Never** commit `.env` files to the repository
- Use system environment variables or hosting service variables (Coolify)
- For local production, use `.env.prod.local` (excluded from Git)
- Use different credentials for each environment (development, staging, production)
- `APP_DEBUG=false` always in production

---

## Database

### PostgreSQL 17 configuration

The prod-local container uses `postgres:17-alpine` with the following configuration:

- **Database**: `guildforge_prod`
- **User**: `guildforge_prod`
- **Healthcheck**: `pg_isready` every 5 seconds with 5 retries
- **Persistent volume**: `guildforge_prod_db_data`

### Backup and restore

**Create a backup:**

```bash
make db-backup
```

Generates a SQL file with a timestamp in `./backups/` (e.g., `backup_20260321_143022.sql`).

**Restore from a backup:**

```bash
make db-restore
```

Automatically restores from the most recent file in `./backups/`.

**Manual backup in production:**

```bash
docker exec guildforge_db_prod pg_dump -U guildforge_prod guildforge_prod > backup.sql
```

**Manual restore:**

```bash
cat backup.sql | docker exec -i guildforge_db_prod psql -U guildforge_prod -d guildforge_prod
```

### Backup automation

Currently, backups are run manually. For production, it is recommended to configure a cron job that runs the backup periodically:

```bash
# Example cron (daily at 3:00 AM)
0 3 * * * cd /path/to/project && make db-backup >> /var/log/guildforge-backup.log 2>&1
```

Also consider a retention policy to delete old backups and avoid excessive disk usage.

### Migration strategy

- Migrations run automatically when the production container starts (in `entrypoint.sh` with `--force`)
- All migrations must be compatible with PostgreSQL (production) and SQLite (tests)
- Use Laravel's schema builder, not raw SQL
- UUID primary keys: `$table->uuid('id')->primary()`
- Roll back with `make migrate-rollback` if a migration causes issues

---

## External services

### Cloudinary (images)

Cloudinary handles image storage, transformation, and delivery.

**Configuration:**

| Variable | Description |
|----------|-------------|
| `CLOUDINARY_URL` | Full connection URL (`cloudinary://API_KEY:API_SECRET@CLOUD_NAME`) |
| `VITE_CLOUDINARY_CLOUD_NAME` | Cloud name (accessible from the frontend) |
| `CLOUDINARY_PREFIX` | Prefix for organizing files (`guildforge`) |
| `VITE_CLOUDINARY_PREFIX` | Same prefix for the frontend |

**Image optimization:**

| Variable | Default value |
|----------|---------------|
| `IMAGE_OPTIMIZATION_ENABLED` | `true` |
| `IMAGE_OPTIMIZATION_MAX_WIDTH` | `2048` |
| `IMAGE_OPTIMIZATION_MAX_HEIGHT` | `2048` |
| `IMAGE_OPTIMIZATION_QUALITY` | `85` |

The `DeletesCloudinaryImages` trait automatically deletes old Cloudinary images when the fields configured in `$cloudinaryImageFields` are updated.

### Email (Resend / SES)

GuildForge supports two mail providers for production:

**Resend** (package `resend/resend-laravel`):

```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxx
```

**Amazon SES** (package `aws/aws-sdk-php`):

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=xxxxx
AWS_SECRET_ACCESS_KEY=xxxxx
AWS_DEFAULT_REGION=eu-west-1
```

In development, Mailpit captures all emails at http://localhost:8025.

### Elasticsearch (logging)

Elasticsearch stores application logs for search and analysis.

**Configuration:**

```env
ELASTICSEARCH_ENABLED=true
ELASTICSEARCH_HOST=elasticsearch
ELASTICSEARCH_PORT=9200
ELASTICSEARCH_INDEX=guildforge-logs
ELASTICSEARCH_SSL=false       # true si usas HTTPS
ELASTICSEARCH_USER=            # usuario si hay autenticación
ELASTICSEARCH_PASSWORD=        # contraseña si hay autenticación
```

**Verify connection:**

```bash
make es-test       # Connection test and test log submission
make es-health     # Cluster health
make es-indices    # Index list
```

### Redis

Redis is used as cache, session store, and queue broker.

**Configuration:**

```env
REDIS_CLIENT=phpredis
REDIS_HOST=redis-prod
REDIS_PASSWORD=null           # configurar en producción si es necesario
REDIS_PORT=6379
REDIS_PREFIX=guildforge_
REDIS_CACHE_DB=1              # one value per tenant when tenants share a Redis
```

In production both tenants use the same Redis 7.2 instance managed by Coolify. Each tenant needs its own `REDIS_PREFIX` and its own `REDIS_CACHE_DB` (for example `1` and `2`).

**Never run `php artisan cache:clear` or `php artisan optimize:clear` in production.** With the Redis store they run `FLUSHDB` on the cache database, which drops every cache entry and the `queue:restart` signal of every tenant using that database. Cache locks (unique jobs, `withoutOverlapping` tasks) are not there: `lock_connection` is `default`, so they live in database 0, which both tenants share, separated only by `REDIS_PREFIX`. To drop compiled files, run the individual commands (`config:clear`, `route:clear`, `event:clear`, `view:clear`) or `php artisan module:refresh-caches`, which clears and rebuilds them without touching the data cache (`App\Infrastructure\Support\FrameworkCacheCommands`).

---

## Monitoring

### Logging with Elasticsearch

When `ELASTICSEARCH_ENABLED=true`, Laravel logs are sent to Elasticsearch in addition to the file channel.

**Verify that Elasticsearch is running:**

```bash
# Cluster health
curl -s http://localhost:9200/_cluster/health?pretty

# List indices
curl -s http://localhost:9200/_cat/indices?v

# View recent container logs
docker logs guildforge_elasticsearch --tail 50
```

### Kibana

Kibana provides a web interface for visualizing and searching logs stored in Elasticsearch.

- **URL**: http://localhost:5601 (development)
- Connects automatically to Elasticsearch
- Create an index pattern `guildforge-logs*` to start visualizing logs

### Mail monitoring (Filament widget)

The admin panel includes widgets for monitoring the mail system:

- **MailHealthWidget**: shows the mail system status (visible to administrators only)
- **MailStatsOverviewWidget**: mail delivery statistics (visible to administrators only)

These widgets are configured from the dashboard settings page at `/admin/dashboard-settings`.

### Health endpoint

Nginx exposes a `/health` endpoint that returns `200 OK` with the text "healthy". This endpoint can be used for load balancer health checks or monitoring services:

```bash
curl http://localhost:8000/health
# healthy
```

`/health` is answered by Nginx alone and does not boot Laravel. The Coolify healthcheck uses Laravel's `/up` instead (see [Deployment with Coolify](#deployment-with-coolify)).

---

## Operational notes

### OPcache, module updates and maintenance mode

`docker/prod/php.ini` sets `opcache.validate_timestamps=1` and `opcache.revalidate_freq=2`: PHP-FPM checks file timestamps at most every two seconds, so a module installed from a ZIP or updated from the admin panel takes effect without restarting the container or reloading PHP-FPM. A Coolify deployment always starts a new container, so its OPcache starts empty.

Installing a module from a ZIP and applying module updates require maintenance mode. It is a site setting (*Configuración del sitio → Mantenimiento*, keys `maintenance_enabled` and `maintenance_message`), not `php artisan down`: the queue worker runs without `--force`, so `artisan down` would pause it and queued updates would never run. The setting only closes the public site: `/admin`, Livewire requests, `/up`, `/iniciar-sesion` and users who can access the panel keep working, and the queue worker and scheduler keep writing to the database.

A module update runs as a queued job (`UpdateModuleJob`, one per module at a time):

1. Backs up the installed module, downloads the release ZIP, verifies its `.sha256`, extracts it into `modules/.staging-<name>-*` and swaps it with the installed copy (kept as `modules/.previous-<name>-*`); publishes the module's `public/build`.
2. Runs `php artisan module:finish-update <name> <version>` in a new PHP process: the health check first, then migrations, new seeders and the recorded version in one database transaction. The commit is the point of no return.
3. Runs `php artisan module:refresh-caches`, which clears the framework's compiled files (config, routes, events, views, compiled classes, Blade icons, Filament) without touching the data cache, then rebuilds the config and view caches when the config cache was in use, and the route cache when routes were cached.
4. On a failure between the swap and the commit, restores the previous files. Finally runs `php artisan queue:restart` so the worker drops the old module classes.

Sending `USR2` to PID 1 does not reload PHP-FPM: PID 1 is supervisord. To restart PHP-FPM or the workers by hand, use `supervisorctl` (see [Supervisord](#supervisord)).

### Core version and module compatibility

`src/VERSION` is the version modules declare compatibility against (`requires.core`). Bump it whenever the contract modules see changes: a minor version for compatible additions, a major version for breaking changes (core classes modules import, `BaseResource`, `ModuleServiceProvider`, or a major version of Filament, Livewire, Laravel or PHP). Other deployments leave it alone.

Every boot (each request and each `artisan` command, entrypoint included) loads only the enabled modules whose `module.json` this core satisfies; the others stay enabled in the database, are not loaded, and the panel shows a banner. Only compatible modules register migrations, so an incompatible module's migrations wait until it is compatible again. After deploying a core version, check on each tenant:

- `php artisan core:version`
- `php artisan module:list`: the `Compatible` column
- `php artisan module:check-updates --force`: available updates and releases blocked by the new core

If a module's version changed while it was incompatible (`module:discover` records the new version but skips its migrations and seeders), the entrypoint's `migrate` runs its pending migrations once the module is compatible, but nothing reruns its seeders: run `php artisan module:seed <name>`, which only runs the seeders that have not run yet.

### Production PHP configuration

The `docker/prod/php.ini` file sets:

| Setting | Value | Description |
|---------|-------|-------------|
| `opcache.enable` | `1` | OPcache enabled |
| `opcache.validate_timestamps` | `1` | Revalidate file timestamps (module updates apply without a restart) |
| `opcache.revalidate_freq` | `2` | Check timestamps at most every 2 seconds |
| `opcache.memory_consumption` | `128M` | Memory for cached bytecode |
| `expose_php` | `Off` | Do not expose PHP version |
| `memory_limit` | `256M` | Memory limit per process |
| `post_max_size` | `20M` | Maximum POST size |
| `upload_max_filesize` | `20M` | Maximum upload size |
| `max_execution_time` | `60s` | Execution timeout |
| `display_errors` | `Off` | Do not display errors to users |
| `date.timezone` | `Europe/Madrid` | Timezone |

### Production Nginx

Relevant configuration from `docker/prod/nginx.conf`:

- Listens on port 8000
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`
- Gzip compression enabled
- Static assets with 1-year cache (`Cache-Control: public, immutable`)
- Module assets served from `/build/modules/`
- Maximum upload size: 20MB
- Hidden files denied (except `.well-known`)
- `/health` endpoint for health checks

### Modules as Git submodules

Modules in `src/modules/` are independent Git repositories (submodules). Key points:

- Tags and releases go to the module's own repository (e.g., `DavidMRGaona/guildforge-game-tables`)
- The `reusable-module-release.yml` workflow generates automatic releases when a tag is created
- Release ZIP files include precompiled Vue assets
- The module update system can install ZIPs from GitHub Releases

### Volumes and persistence

In prod-local, Docker volumes persist data between restarts (production on Coolify uses bind mounts, see [Deployment with Coolify](#deployment-with-coolify)):

| Volume | Content | Caution |
|--------|---------|---------|
| `guildforge_prod_db_data` | PostgreSQL data | Regular backup recommended |
| `guildforge_prod_storage` | Logs, cache, sessions, uploads | Can be cleaned with caution |
| `guildforge_prod_redis_data` | Redis data | Can be flushed without risk |
| `guildforge_prod_modules` | Installed modules | Entrypoint syncs from image |

**Update without data loss:**

```bash
make prod-update     # Rebuilds image + restarts containers (preserves volumes)
```

**Full reset (deletes all data):**

```bash
make prod-reset      # Deletes volumes and rebuilds everything
```

### Deployment with Coolify

Production runs as two Coolify applications (two tenants) on the same host. They share a PostgreSQL 17 instance and a Redis 7.2 instance managed by Coolify, and keep storage and modules in bind mounts (`/data/<app>/storage`, `/data/<app>/modules`). `docker-compose.prod.yml` does not describe production; it is only prod-local.

The automatic deployment flow is:

1. Push to the `main` branch (or merge a pull request)
2. GitHub Actions runs CI (tests + linting)
3. If CI passes, `deploy.yml` calls the Coolify webhook of each tenant (`WEBHOOK_URL_SERVER_1`, `WEBHOOK_URL_SERVER_2`), in parallel
4. Coolify builds `Dockerfile.prod` and starts a new container; the image carries no modules, which stay in the bind mount
5. The entrypoint records the deployment, runs migrations, discovers modules, syncs permissions and caches configuration (see [Production entrypoint](#production-entrypoint))

Coolify injects `SOURCE_COMMIT` (deployed commit) and `COOLIFY_BRANCH` (branch the application deploys from). `core:record-deployment` and the core updates page (`CoreUpdatesPage`) read them through `config/updates.php`: without `SOURCE_COMMIT` the deployment is not recorded and the page shows an error; the page compares the deployed commit with `COOLIFY_BRANCH` (`CORE_GITHUB_BRANCH` overrides it, `main` by default).

Required settings for each tenant in Coolify:

| Setting | Value | Why |
|---------|-------|-----|
| Healthcheck path | `/up` | With `/`, maintenance mode answers 503, the container turns unhealthy and the proxy takes the whole tenant down, admin panel included |
| `REDIS_CACHE_DB` | A different value per tenant (`1`, `2`) | Both tenants share Redis; separate cache databases keep each tenant's cache and `queue:restart` signal apart (locks live in database 0, separated by `REDIS_PREFIX`) |
| `REDIS_PREFIX` | A different value per tenant | Keeps session and queue keys apart in the shared database |

To roll back, redeploy the previous image from the application's deployment history in Coolify. Modules are not part of the image, so rolling back the core does not roll back modules.
