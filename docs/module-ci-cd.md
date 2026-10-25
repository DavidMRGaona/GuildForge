# CI/CD for module distribution

This guide explains how to set up and use the CI/CD system to distribute GuildForge modules via GitHub Releases.

## Architecture

The system uses a **centralized reusable workflow** in the host repository (`DavidMRGaona/GuildForge`) that is called from each module repository:

```
┌─────────────────────────────────────────────────────────┐
│  Host repository (DavidMRGaona/GuildForge)              │
│  .github/workflows/reusable-module-release.yml          │
│  - Shared validation, build, and release logic          │
└────────────────────────────┬────────────────────────────┘
                             │ calls
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ guildforge-     │ │ guildforge-     │ │ guildforge-     │
│ announcements   │ │ tournaments     │ │ memberships     │
│ release.yml     │ │ release.yml     │ │ release.yml     │
│ (15 lines)      │ │ (15 lines)      │ │ (15 lines)      │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## Creating a release

To create a new module release:

1. Raise `version` in `module.json`, following semver:

   ```json
   {
     "name": "announcements",
     "version": "1.0.0"
   }
   ```

2. Commit it, then create and push the tag:

   ```bash
   git add module.json
   git commit -m "chore: bump version to 1.0.0"
   git tag v1.0.0
   git push origin main --tags
   ```

The workflow automatically:
1. Validates that `module.json` exists and has the correct format: its `name` matches `module_name`, its `version` is semver, and its compatibility requirements are valid (`requires.core` is mandatory; `requires.modules` and `requires.extensions`, when present, must be lists of strings)
2. Verifies that the tag matches the version in `module.json`
3. Runs the module's PHPUnit suite inside the host application: it checks out `DavidMRGaona/GuildForge` at `host_ref`, installs its Composer dependencies, places the module in `src/modules/<module_name>` and runs `php artisan test --parallel --configuration=modules/<module_name>/phpunit.xml`, one process per CPU core with its own compiled-view directory (`.github/scripts/run-module-tests.sh`). Modules without `phpunit.xml` or tests are skipped with a notice; a failing test blocks the release
4. If the module has a `vite.config.ts`, builds its assets: it checks out the host's `src/resources/js`, `src/public` and `src/scripts` at `host_ref`, runs `npm ci` there, copies the module to `src/modules/<module_name>` and runs `MODULE_STANDALONE_OUTPUT=true sh scripts/build-module.sh <module_name>` with the core's toolchain (`defineModuleConfig`, after `npm ci` in the module when it has a `package.json` of runtime libraries). A module on the legacy module toolchain (its own `package.json` with Vite, deprecated) still runs `npm install` and `MODULE_STANDALONE_OUTPUT=true npm run build` in its directory. The step fails without `public/build/manifest.json`
5. Generates a ZIP with the correct structure, compiled assets included
6. Calculates a SHA256 checksum
7. Creates a GitHub Release with the ZIP, its checksum and `module.json` as assets

## Setting up a new module

Add the module's GitHub repository to its `module.json` so the updater can find its releases:

```json
"repository": "DavidMRGaona/guildforge-my-module"
```

### 1. Create the workflow in the module

`php artisan module:make` writes `.github/workflows/release.yml`. For a module created otherwise, copy [the template](templates/module-release-workflow.yml) there and set `module_name`:

```yaml
name: Module release

on:
  push:
    tags: ['v*.*.*']

jobs:
  release:
    uses: DavidMRGaona/GuildForge/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'MODULE_NAME'  # ← Change this
      # Host branch, tag or SHA to test and build against (main when the variable is unset)
      host_ref: ${{ vars.GUILDFORGE_HOST_REF || 'main' }}
    permissions:
      contents: write
```

To test and build a release against a host branch, tag or SHA other than `main` (for example while a core change the module needs is not merged yet), set the repository variable `GUILDFORGE_HOST_REF` in the module repository (*Settings → Secrets and variables → Actions → Variables*). Remove it once the change is on `main`.

### 2. Ensure module.json is valid

The `module.json` must have:

```json
{
  "name": "module-name",
  "displayName": "Nombre del módulo",
  "version": "1.0.0",
  "namespace": "Modules\\ModuleName",
  "provider": "ModuleNameServiceProvider",
  "repository": "owner/repo",
  "requires": {
    "core": "^3.0",
    "filament": "^5.0",
    "php": ">=8.5",
    "laravel": ">=13.0"
  },
  "dependencies": []
}
```

**Important:** The `name` in `module.json` must match the workflow's `module_name` exactly.

### Compatibility requirements

Sites decide from `module.json` alone, before loading any module code, whether a module can run on them. An incompatible module is never loaded at boot (its database state is kept and it loads again as soon as the site is compatible), its migrations are not registered, and it cannot be enabled, installed from a ZIP or applied as an update; the admin panel says why.

- `requires.core` (required): constraint on the GuildForge core version (`src/VERSION`). The release workflow rejects a tag without it. Modules published before this field existed are treated as `^2.0`, so core 3 rejects them.
- `requires.filament` (optional): constraint on the installed `filament/filament` version.
- `requires.php`, `requires.laravel` (optional): constraints on the running PHP and Laravel versions.
- `requires.extensions` (optional): PHP extensions that must be loaded (case-insensitive, `ext-` prefix allowed).
- `requires.modules` and `dependencies`: other modules; checked when the module is enabled.

Constraints use a subset of Composer's syntax: `^`, `~`, `>=`, `<=`, `>`, `<`, `=` or an exact version; terms joined with spaces or commas must all match, alternatives are joined with `||` (`^2.6 || ^3.0`). Wildcards (`2.x`, `*`), hyphen ranges, `!=`, a `v` prefix, stability flags (`@beta`), prereleases on partial versions (`^2.6-beta`) and version components longer than nine digits are not supported; a constraint the site cannot read, or a value that is not a string (`null` included), makes the module incompatible. In `^`, `~`, `>=` and `<`, a bound written without a prerelease stands for that version and all its prereleases: `2.6.0-beta` satisfies `^2.6`, `3.0.0-rc.1` does not (it is not below `3.0`). `~1.2` means `>=1.2 <2.0`, as in Composer.

Each release also publishes its `module.json` as an asset, so sites can read the requirements of a release before downloading it.

## Generated ZIP structure

```
announcements-1.0.0/
├── module.json
├── config/  database/  lang/  resources/  routes/  src/
├── package.json        # only with runtime libraries of its own
├── public/build/       # compiled Vue and CSS assets, manifest.json at its root
└── README.md           # if the repository has one
```

Everything in the repository is copied except `.git/`, `.github/`, `tests/`, `node_modules/`, `.env*`, `*.log`, `.phpunit*`, `phpunit.xml`, `.gitignore`, `.gitattributes`, `vite.config.ts`, `tsconfig.json` and `package-lock.json`. Installing or updating the module copies `public/build/` to the site's `public/build/modules/<name>/`, so the site never compiles anything.

## Building a ZIP locally

`make module-zip`, run on the host, lists the modules in `src/modules/` and asks which one to package. If it has a `vite.config.ts`, it builds the assets in the `guildforge_node` container with `MODULE_STANDALONE_OUTPUT=true` (`npm run build:module`, or the module's own npm build for a module on the legacy module toolchain) (and fails without `public/build/manifest.json`). It then writes `dist/<name>-<version>.zip`, with the same exclusions as the release workflow, and `dist/<name>-<version>.zip.sha256`. Tests are not run.

## Installing a module

1. Go to the module's Releases page on GitHub
2. Download the `{module_name}-{version}.zip` file
3. In the admin panel, turn maintenance mode on and go to *Administración → Módulos* (admins only)
4. Use *Instalar / actualizar* to upload the ZIP; a ZIP of an installed module updates it

### Verify integrity (optional)

```bash
# Also download the .sha256 file
sha256sum -c announcements-1.0.0.zip.sha256
```

## Updating installed modules

The admin page *Sistema → Actualizaciones de módulos* detects and applies new releases:

1. Each module declares its repository in `module.json` (`"repository": "owner/repo"`). Discovery (`module:discover`, run on every container start) copies it to `modules.source_owner`/`source_repo`. Modules installed before the field existed need it once: `php artisan module:set-source <name> <owner/repo>`.
2. Detection lists the repository's releases (not `/releases/latest`, which ignores prereleases), skips drafts and releases not newer than the installed version, and reads each candidate's `module.json` asset (releases without it are evaluated with `requires.core: "^2.0"`). It offers the highest release this site can run. When a newer release needs a different host, it records the highest such release with the reason (with no compatible release, the highest one newer than the installed version); the page shows it under *Versiones no compatibles*, without an update button. A module installed on a prerelease (`1.0.8-beta`) receives newer prereleases; stable installs only when `UPDATE_ALLOW_PRERELEASES=true`. It runs daily at 04:00 (`CheckModuleUpdatesJob` via `schedule:work`), from `php artisan module:check-updates [--force]` and from the page's *Comprobar* button, which bypasses the cache of GitHub answers (one hour, `UPDATE_CACHE_TTL`). A `module.json` asset that cannot be downloaded is reported as an error for that module, never as "up to date".
3. *Actualizar* (only with maintenance mode on) queues an `UpdateModuleJob` (one per module at a time) and the page follows its progress. The job makes a backup of the installed module, downloads the ZIP, verifies the `.sha256` (an update without checksum is rejected unless `UPDATE_VERIFY_CHECKSUM=false`), extracts it into `modules/.staging-<name>-*`, checks that it holds exactly this module and that this site satisfies its `module.json` (the last line of defence: an incompatible package is discarded before the swap), swaps it with the installed copy (kept as `modules/.previous-<name>-*`) and publishes `public/build`. Then `module:finish-update` runs in a new PHP process: it health-checks the module first and only then runs migrations, new seeders and the version bump in one database transaction. Finally `module:refresh-caches` rebuilds the framework caches without clearing the data cache. Any failure between the swap and the commit renames the previous version back (unless `UPDATE_AUTO_ROLLBACK=false`). Temporary files are always removed and the queue worker restarts afterwards; production OPcache revalidates file timestamps, so no container restart is needed.

Settings saved from the panel live in the database (`module_settings:<name>` in the `settings` table), not in the module directory: updates, rollbacks and container rebuilds keep them, and keys a new release adds to its `config/settings.php` show up with their defaults.

### Update configuration

| Variable | Default | Effect |
|----------|---------|--------|
| `GITHUB_TOKEN` | (none) | Token for the GitHub API, used to list releases and download their assets. Needed for private module repositories and to avoid the anonymous limit of 60 requests per hour; read-only access to the module repositories (contents: read) is enough |
| `GITHUB_API_URL` | `https://api.github.com` | GitHub API base URL |
| `GITHUB_TIMEOUT` | `30` | Request timeout, in seconds |
| `UPDATES_ENABLED` | `true` | `false` skips the scheduled check |
| `UPDATE_CACHE_TTL` | `3600` | Seconds GitHub answers are cached |
| `UPDATE_ALLOW_PRERELEASES` | `false` | Offer prereleases to stable installs |
| `UPDATE_VERIFY_CHECKSUM` | `true` | Reject an update whose `.sha256` is missing or does not match |
| `UPDATE_HEALTH_CHECK` | `true` | Health-check the module before its migrations run |
| `UPDATE_AUTO_ROLLBACK` | `true` | Restore the previous version when an update fails after the swap |

## Prereleases

Versions with a suffix (e.g., `1.0.0-beta.1`, `2.0.0-rc.1`) are automatically marked as prerelease on GitHub.

## Pull request checks

Each module's `tests.yml` calls `reusable-module-tests.yml` on every pull request and weekly (`schedule`, Mondays at 06:00 UTC) against the core's `main`, so a core change that breaks the module shows up even without new commits in it; the core never checks out modules, which anyone can write:

- **`test / Test module`**: the module's PHPUnit suite inside the host (see step 3 above).
- **`test / Quality`**, with `quality: true`: the core's quality tools with the core's configuration from `host_ref` (the module copies none of it), through `src/scripts/check-module-quality.sh`:
  - Rector (`src/`, dry run), then Pint (the whole module), then PHPStan level 8 (`src/` and `tests/`, `phpstan-modules.neon`, whose `phpstan-modules-bootstrap.php` autoloads `Modules\<Name>\Tests\` and `Modules\<Name>\Database\{Seeders,Factories}\` for PHPStan only)
  - ESLint and Prettier (`resources/js/`), and `vue-tsc` with the module's `tsconfig.json` (required when the module has TypeScript or Vue files; copy `src/stubs/modules/module/tsconfig.json.stub`), which checks the module against the core's own sources
  - for a module on the core's toolchain (`defineModuleConfig`): its `package.json`, if any, declares only runtime libraries (`src/scripts/check-module-package.mjs`); the module builds into a scratch directory, so a runtime import outside the [public frontend API](modules/README.md#public-frontend-api) or an undeclared package fails here; Vitest runs with the core's (`vitest.module.config.ts`) when the module has `vitest.config.ts` and a spec under `tests/js`
  - for a module on the legacy module toolchain (deprecated): Vitest, when the module has `vitest.config.ts` and `package-lock.json` (its own dependencies)

Every tool runs even when an earlier one fails, so one run lists everything. A module turns `quality` on in the pull request that leaves its code passing them, and the check then becomes required in its `main` protection, like `test / Test module`. Any quality tool added to the core goes into the script as well.

```yaml
jobs:
  test:
    uses: DavidMRGaona/GuildForge/.github/workflows/reusable-module-tests.yml@main
    with:
      module_name: 'my-module'
      host_ref: ${{ vars.GUILDFORGE_HOST_REF || 'main' }}
      quality: true
```

Locally, from the host root with the containers running: `make check MODULE=<module>`.

## Workflow options

The reusable workflow accepts these parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `module_name` | string | (required) | Module name in kebab-case |
| `php_version` | string | `8.5` | PHP version for tests |
| `node_version` | string | `24` | Node.js version for building Vue components |
| `host_ref` | string | `main` | Branch, tag or SHA of `DavidMRGaona/GuildForge` the module is tested and built against |
| `run_tests` | boolean | `true` | Run tests before the release |

It returns `version` (the released version) and `release_url` (the GitHub Release URL) as outputs.

Example with options:

```yaml
jobs:
  release:
    uses: DavidMRGaona/GuildForge/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'my-module'
      host_ref: ${{ vars.GUILDFORGE_HOST_REF || 'main' }}
      run_tests: false
    permissions:
      contents: write
```

## Configured modules

| Module | Status |
|--------|--------|
| guildforge-announcements | ✅ Configured |
| guildforge-channel-notifications | ✅ Configured |
| guildforge-cookie-consent | ✅ Configured |
| guildforge-event-registrations | ✅ Configured |
| guildforge-game-tables | ✅ Configured |
| guildforge-memberships | ✅ Configured |
| guildforge-tournaments | ✅ Configured |
| guildforge-venue-booking | ✅ Configured |

## Troubleshooting

### The workflow fails on validation

**Error:** `module.json not found`
- Make sure `module.json` exists at the root of the repository

**Error:** `Module name mismatch`
- The `name` in `module.json` must match the workflow's `module_name` exactly

**Error:** `Tag version doesn't match module.json version`
- The tag (without the `v` prefix) must match the version in `module.json`
- Tag `v1.0.0` → version `"1.0.0"`

**Error:** `requires.core is required in module.json`
- Add the core constraint the module was tested against, e.g. `"core": "^3.0"`

**Error:** `Invalid requires.<key> constraint` or `requires.<key> must be a constraint string`
- Use the supported syntax (see [Compatibility requirements](#compatibility-requirements)); `2.x`, `*` and `^2.6-beta` are not supported, and `null` or a number is not a constraint

### Tests fail

- Run the suite locally before tagging: `docker exec guildforge_app vendor/bin/phpunit -c modules/<module>/phpunit.xml`
- CI uses a fresh clone: files that are not committed (and empty test directories) do not exist there
- The module's `phpunit.xml` must keep the `APP_*_CACHE` environment block so tests never read the bootstrap caches
- To release while a fix is pending, pass `run_tests: false` in the module's `release.yml` and revert it afterwards

### The asset build fails

**Error:** `Build completed but manifest.json not found`
- The module's `vite.config.ts` must write to its own `public/build/` with `manifest: 'manifest.json'` when `MODULE_STANDALONE_OUTPUT=true`; use the configuration `module:make` writes (see [Building module assets](modules/README.md#building-module-assets))
- Reproduce it locally with `make module-zip`

### The release has no assets

- Check the logs of the "Create release" job for errors
- Verify that the workflow has `contents: write` permissions

### A module shows as incompatible

The panel shows an "Incompatible" badge, `php artisan module:list` shows `no: …` in the `Compatible` column and, if the module is enabled, a red banner lists it on every panel page. The reason says which requirement fails (`requiere core ^3.0, instalado 2.6.0`). Either install a release of the module that supports this core (*Actualizaciones de módulos*) or deploy a core that satisfies it; the module loads again on its own, without enabling it again, and its pending migrations run with the next `php artisan migrate` (every container start). Seeders are not rerun: if its version changed while it was incompatible (`module:discover` records the new version but skips its migrations and seeders), run `php artisan module:seed <name>` once it is compatible; it only runs the seeders that have not run yet.
