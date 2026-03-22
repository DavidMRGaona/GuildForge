# Deployment guide

> Generated: 2026-03-21 | Project: GuildForge v1.0.0

Complete guide to deploy, operate, and monitor GuildForge in production.

---

## Production architecture

### Container stack

The production environment is based on a multi-stage Docker image that includes Nginx, PHP-FPM, and a queue worker, all managed by Supervisord within a single container.

| Container | Image | Port | Function |
|-----------|-------|------|----------|
| `guildforge_app_prod` | Dockerfile.prod (PHP 8.4 FPM Alpine) | 8000 | Application (Nginx + PHP-FPM + queue worker) |
| `guildforge_db_prod` | postgres:16-alpine | - | PostgreSQL database |
| `guildforge_redis_prod` | redis:alpine | - | Cache and sessions |

In the development environment, the following are also included:

| Container | Image | Port | Function |
|-----------|-------|------|----------|
| `guildforge_elasticsearch` | elasticsearch:9.2.4 | 9200 | Search engine and logging |
| `guildforge_kibana` | kibana:9.2.4 | 5601 | Log visualization |
| `guildforge_mailpit` | axllent/mailpit | 8025 | Development mail server |

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

**Stage 1: `assets` (Node 22 Alpine)**
- Installs npm dependencies (`npm ci`)
- Compiles frontend assets (`npm run build`)
- Compiles module assets that have `package.json` and `vite.config.ts`
- Consolidates module build artifacts for copying to the final stage

**Stage 2: `final` (PHP 8.4 FPM Alpine)**
- Installs system dependencies (Nginx, Supervisor, image libraries)
- Installs PHP extensions: `pdo_pgsql`, `gd`, `zip`, `bcmath`, `opcache`, `intl`, `mbstring`, `exif`, `pcntl`, `redis`, `imagick`
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
| `db-prod` | PostgreSQL 16 Alpine, database `guildforge_prod`, healthcheck with `pg_isready` |
| `redis-prod` | Redis Alpine, healthcheck with `redis-cli ping` |

**Persistent volumes:**
- `guildforge_prod_db_data` - PostgreSQL data
- `guildforge_prod_storage` - Laravel storage (logs, cache, sessions, uploads)
- `guildforge_prod_redis_data` - Redis data
- `guildforge_prod_modules` - installed modules

### Production entrypoint

The `docker/prod/entrypoint.sh` script runs when the container starts:

1. Creates required directories (storage, bootstrap/cache, modules)
2. Syncs modules from the image (`php artisan module:sync-from-image`)
3. Publishes module build assets (`php artisan module:publish-build-assets`)
4. Sets permissions (775 for storage, bootstrap/cache, modules)
5. Creates storage symbolic link if it does not exist
6. Runs pending migrations (`php artisan migrate --force`)
7. Discovers modules (`php artisan module:discover`)
8. Caches configuration, clears routes, and caches views
9. Starts Supervisord (Nginx + PHP-FPM + queue worker)

### Supervisord

Manages three processes inside the production container:

| Process | Command | Configuration |
|---------|---------|---------------|
| nginx | `nginx -g "daemon off;"` | Auto-start, auto-restart |
| php-fpm | `php-fpm` | Auto-start, auto-restart |
| queue-worker | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` | 1 process, www-data user, stop timeout 3600s |

---

## CI/CD

### GitHub Actions

The project uses two main workflows and one reusable workflow:

#### 1. CI (`ci.yml`)

Runs on every push or pull request to `main`.

**Job `test`:**
- Services: PostgreSQL 17, Redis 7
- Steps: checkout, setup PHP 8.4, composer install, setup Node 22, npm ci, build assets, prepare `.env`, run migrations, run tests

> **Note on PostgreSQL versions:** CI uses PostgreSQL 17 to verify compatibility with the latest version, while production uses PostgreSQL 16 Alpine (`postgres:16-alpine`). Migrations are designed using Laravel's schema builder to ensure compatibility with both versions.

**Job `lint`:**
- Runs in parallel with `test`
- Steps: checkout, setup Node 22, npm ci, TypeScript type checking, ESLint

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
1. **Validate**: verifies that `module.json` exists, the version is valid semver, and matches the tag
2. **Test**: runs Pint and module tests (if they exist)
3. **Release**: compiles Vue assets (if the module has them), creates ZIP, generates changelog, publishes GitHub Release with ZIP and SHA-256 checksum

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

### PostgreSQL 16 configuration

The container uses `postgres:16-alpine` with the following configuration:

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
```

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

---

## Operational notes

### OPcache and module updates

In production, PHP uses OPcache with `validate_timestamps=0`, which means PHP files are compiled once and served from cache. When module files are updated (through the update system or manually), PHP-FPM continues serving old bytecode from the cache.

**Solution**: restart the container after updating modules:

```bash
docker restart guildforge_app_prod
```

Alternatively, send a USR2 signal to the PHP-FPM process for a graceful restart:

```bash
docker exec guildforge_app_prod kill -USR2 1
```

### Production PHP configuration

The `docker/prod/php.ini` file sets:

| Setting | Value | Description |
|---------|-------|-------------|
| `opcache.enable` | `1` | OPcache enabled |
| `opcache.validate_timestamps` | `0` | Do not revalidate files (maximum performance) |
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

In production, Docker volumes persist data between restarts:

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

The automatic deployment flow is:

1. Push to the `main` branch
2. GitHub Actions runs CI (tests + linting)
3. If CI passes, the deploy workflow sends a webhook to Coolify
4. Coolify rebuilds the Docker image and restarts the containers
5. The entrypoint runs migrations, syncs modules, and caches configuration

For deployments to multiple servers, independent webhooks are configured (`WEBHOOK_URL_SERVER_1`, `WEBHOOK_URL_SERVER_2`) that fire in parallel.
