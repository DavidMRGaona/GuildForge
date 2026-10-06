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

```bash
# 1. Update the version in module.json
{
  "name": "announcements",
  "version": "1.0.0",  # ← Increment following semver
  ...
}

# 2. Commit the changes
git add module.json
git commit -m "chore: bump version to 1.0.0"

# 3. Create and push the tag
git tag v1.0.0
git push origin main --tags
```

The workflow automatically:
1. Validates that `module.json` exists and has the correct format, including its compatibility requirements (`requires.core` is mandatory)
2. Verifies that the tag matches the version in `module.json`
3. Runs the module's PHPUnit suite inside the host application: it checks out `DavidMRGaona/GuildForge` at `host_ref`, installs its Composer dependencies, places the module in `src/modules/<module_name>` and runs `vendor/bin/phpunit -c modules/<module_name>/phpunit.xml` (`.github/scripts/run-module-tests.sh`). Modules without `phpunit.xml` or tests are skipped with a notice; a failing test blocks the release
4. Generates a ZIP with the correct structure
5. Calculates a SHA256 checksum
6. Creates a GitHub Release with the ZIP, its checksum and `module.json` as assets

## Setting up a new module

Add the module's GitHub repository to its `module.json` so the updater can find its releases:

```json
"repository": "DavidMRGaona/guildforge-my-module"
```

### 1. Create the workflow in the module

Create `.github/workflows/release.yml` in the module repository:

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
    permissions:
      contents: write
```

### 2. Ensure module.json is valid

The `module.json` must have:

```json
{
  "name": "module-name",
  "version": "1.0.0",
  "namespace": "Modules\\ModuleName",
  "provider": "ModuleNameServiceProvider",
  "repository": "owner/repo",
  "requires": {
    "core": "^2.6",
    "filament": "^3.3",
    "php": ">=8.2",
    "laravel": ">=12.0"
  },
  "dependencies": []
}
```

**Important:** The `name` in `module.json` must match the workflow's `module_name` exactly.

### Compatibility requirements

Sites decide from `module.json` alone, before loading any module code, whether a module can run on them. An incompatible module is never loaded at boot (its database state is kept and it loads again as soon as the site is compatible), its migrations are not registered, and it cannot be enabled, installed from a ZIP or applied as an update; the admin panel says why.

- `requires.core` (required): constraint on the GuildForge core version (`src/VERSION`). The release workflow rejects a tag without it. Modules published before this field existed are treated as `^2.0`.
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
├── src/
├── database/
├── resources/
├── routes/
├── lang/
├── config/
└── README.md

❌ Automatically excluded:
- tests/
- .git/
- .github/
- node_modules/
- .env*
- *.log
- .phpunit*
- phpunit.xml
```

## Installing a module

1. Go to the module's Releases page on GitHub
2. Download the `{module_name}-{version}.zip` file
3. In the GuildForge admin panel → Modules → Install from ZIP
4. Upload the ZIP file

### Verify integrity (optional)

```bash
# Also download the .sha256 file
sha256sum -c announcements-1.0.0.zip.sha256
```

## Updating installed modules

The admin page *Sistema → Actualizaciones de módulos* detects and applies new releases:

1. Each module declares its repository in `module.json` (`"repository": "owner/repo"`). Discovery (`module:discover`, run on every container start) copies it to `modules.source_owner`/`source_repo`. Modules installed before the field existed need it once: `php artisan module:set-source <name> <owner/repo>`.
2. Detection lists the repository's releases (not `/releases/latest`, which ignores prereleases), skips drafts and releases not newer than the installed version, and reads each candidate's `module.json` asset (releases without it are evaluated with `requires.core: "^2.0"`). It offers the highest release this site can run. When a newer release needs a different host, it records the highest such release with the reason (with no compatible release, the highest one newer than the installed version); the page shows it under *Versiones no compatibles*, without an update button. A module installed on a prerelease (`1.0.8-beta`) receives newer prereleases; stable installs only when `UPDATE_ALLOW_PRERELEASES=true`. It runs daily at 04:00 (`CheckModuleUpdatesJob` via `schedule:work`), from `php artisan module:check-updates [--force]` and from the page's *Comprobar* button, which bypasses the one-hour cache. A `module.json` asset that cannot be downloaded is reported as an error for that module, never as "up to date".
3. *Actualizar* (only with maintenance mode on) queues an `UpdateModuleJob` (one per module at a time) and the page follows its progress. The job downloads the ZIP, verifies the `.sha256` (an update without checksum is rejected), extracts it into `modules/.staging-<name>-*`, checks that it holds exactly this module and that this site satisfies its `module.json` (the last line of defence: an incompatible package is discarded before the swap), swaps it with the installed copy (kept as `modules/.previous-<name>-*`) and publishes `public/build`. Then `module:finish-update` runs in a new PHP process: it health-checks the module first and only then runs migrations, new seeders and the version bump in one database transaction. Finally `module:refresh-caches` rebuilds the framework caches without clearing the data cache. Any failure between the swap and the commit renames the previous version back. Temporary files are always removed and the queue worker restarts afterwards; production OPcache revalidates file timestamps, so no container restart is needed.

## Prereleases

Versions with a suffix (e.g., `1.0.0-beta.1`, `2.0.0-rc.1`) are automatically marked as prerelease on GitHub.

## Workflow options

The reusable workflow accepts these parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `module_name` | string | (required) | Module name in kebab-case |
| `php_version` | string | `8.4` | PHP version for tests |
| `node_version` | string | `24` | Node.js version for building Vue components |
| `host_ref` | string | `main` | Branch, tag or SHA of `DavidMRGaona/GuildForge` the module is tested and built against |
| `run_tests` | boolean | `true` | Run tests before the release |

Example with options:

```yaml
jobs:
  release:
    uses: DavidMRGaona/GuildForge/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'my-module'
      host_ref: 'main'
      run_tests: false
    permissions:
      contents: write
```

## Configured modules

| Module | Status |
|--------|--------|
| guildforge-announcements | ✅ Configured |
| guildforge-cookie-consent | ✅ Configured |
| guildforge-event-registrations | ✅ Configured |
| guildforge-game-tables | ✅ Configured |
| guildforge-memberships | ✅ Configured |
| guildforge-tournaments | ✅ Configured |

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
- Add the core constraint the module was tested against, e.g. `"core": "^2.6"`

**Error:** `Invalid requires.<key> constraint` or `requires.<key> must be a constraint string`
- Use the supported syntax (see [Compatibility requirements](#compatibility-requirements)); `2.x`, `*` and `^2.6-beta` are not supported, and `null` or a number is not a constraint

### Tests fail

- Run the suite locally before tagging: `docker exec guildforge_app vendor/bin/phpunit -c modules/<module>/phpunit.xml`
- CI uses a fresh clone: files that are not committed (and empty test directories) do not exist there
- The module's `phpunit.xml` must keep the `APP_*_CACHE` environment block so tests never read the bootstrap caches
- To release while a fix is pending, pass `run_tests: false` in the module's `release.yml` and revert it afterwards

### The release has no assets

- Check the logs of the "Create release" job for errors
- Verify that the workflow has `contents: write` permissions

### A module shows as incompatible

The panel shows an "Incompatible" badge, `php artisan module:list` shows `no: …` in the `Compatible` column and, if the module is enabled, a red banner lists it on every panel page. The reason says which requirement fails (`requiere core ^3.0, instalado 2.6.0`). Either install a release of the module that supports this core (*Actualizaciones de módulos*) or deploy a core that satisfies it; the module loads again on its own, without enabling it again, and its pending migrations run with the next `php artisan migrate` (every container start). Seeders are not rerun: if its version changed while it was incompatible (`module:discover` records the new version but skips its migrations and seeders), run `php artisan module:seed <name>` once it is compatible; it only runs the seeders that have not run yet.
