# CI/CD for module distribution

This guide explains how to set up and use the CI/CD system to distribute GuildForge modules via GitHub Releases.

## Architecture

The system uses a **centralized reusable workflow** in the main repository (`runesword`) that is called from each module repository:

```
┌─────────────────────────────────────────────────────────┐
│  Main repository (runesword)                             │
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
1. Validates that `module.json` exists and has the correct format
2. Verifies that the tag matches the version in `module.json`
3. Runs the module's PHPUnit suite inside the host application: it checks out `DavidMRGaona/GuildForge` at `host_ref`, installs its Composer dependencies, places the module in `src/modules/<module_name>` and runs `vendor/bin/phpunit -c modules/<module_name>/phpunit.xml` (`.github/scripts/run-module-tests.sh`). Modules without `phpunit.xml` or tests are skipped with a notice; a failing test blocks the release
4. Generates a ZIP with the correct structure
5. Calculates a SHA256 checksum
6. Creates a GitHub Release with the assets

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
  "description": "Module description",
  "dependencies": []
}
```

**Important:** The `name` in `module.json` must match the workflow's `module_name` exactly.

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
2. Detection lists the repository's releases (not `/releases/latest`, which ignores prereleases), skips drafts and picks the highest version. A module installed on a prerelease (`1.0.8-beta`) receives newer prereleases; stable installs only when `UPDATE_ALLOW_PRERELEASES=true`. It runs daily at 04:00 (`CheckModuleUpdatesJob` via `schedule:work`), from `php artisan module:check-updates [--force]` and from the page's *Comprobar* button, which bypasses the one-hour cache.
3. *Actualizar* queues an `UpdateModuleJob` (one per module at a time) and the page follows its progress. The job downloads the ZIP, verifies the `.sha256` (an update without checksum is rejected), extracts it into `modules/.staging-<name>-*`, checks that it holds exactly this module, swaps it with the installed copy (kept as `modules/.previous-<name>-*`), publishes `public/build`, runs migrations and new seeders, and health-checks the module. Any failure after the swap renames the previous version back. Temporary files are always removed and the queue worker restarts afterwards; production OPcache revalidates file timestamps, so no container restart is needed.

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

### Tests fail

- Run the suite locally before tagging: `docker exec guildforge_app vendor/bin/phpunit -c modules/<module>/phpunit.xml`
- CI uses a fresh clone: files that are not committed (and empty test directories) do not exist there
- The module's `phpunit.xml` must keep the `APP_*_CACHE` environment block so tests never read the bootstrap caches
- To release while a fix is pending, pass `run_tests: false` in the module's `release.yml` and revert it afterwards

### The release has no assets

- Check the logs of the "Create release" job for errors
- Verify that the workflow has `contents: write` permissions
