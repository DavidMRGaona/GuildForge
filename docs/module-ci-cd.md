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
3. Runs linting (Pint) and tests if they exist
4. Generates a ZIP with the correct structure
5. Calculates a SHA256 checksum
6. Creates a GitHub Release with the assets

## Setting up a new module

### 1. Create the workflow in the module

Create `.github/workflows/release.yml` in the module repository:

```yaml
name: Module release

on:
  push:
    tags: ['v*.*.*']

jobs:
  release:
    uses: DavidMRGaona/runesword/.github/workflows/reusable-module-release.yml@main
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

## Prereleases

Versions with a suffix (e.g., `1.0.0-beta.1`, `2.0.0-rc.1`) are automatically marked as prerelease on GitHub.

## Workflow options

The reusable workflow accepts these parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `module_name` | string | (required) | Module name in kebab-case |
| `php_version` | string | `8.4` | PHP version for tests |
| `run_tests` | boolean | `true` | Run tests before the release |

Example with options:

```yaml
jobs:
  release:
    uses: DavidMRGaona/runesword/.github/workflows/reusable-module-release.yml@main
    with:
      module_name: 'my-module'
      php_version: '8.3'
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

- Verify that `composer.json` has the correct dependencies
- Make sure the tests pass locally before creating the tag

### The release has no assets

- Check the logs of the "Create release" job for errors
- Verify that the workflow has `contents: write` permissions
