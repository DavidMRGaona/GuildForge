# Module updater repair implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the admin "module updates" section detect new GitHub releases of each module, apply them safely (download, verify, swap, migrate, roll back on failure), clean up what is no longer needed, and run without hitting web timeouts or stale OPcache.

**Architecture:** Detection reads the repository from each module's `module.json` (`"repository": "owner/repo"`), lists GitHub releases (prereleases included when the installed version is itself a prerelease) and persists the result per module. Application stages the release ZIP inside `modules/` (same mount point, so `rename()` works), swaps directories atomically and reverts the swap on failure. Updates run as queued jobs; the page polls the update history. OPcache revalidates timestamps in production.

**Tech stack:** Laravel 12, Filament 3 (Livewire 3), PHPUnit 11, Mockery, `Http::fake()`, ZipArchive, Docker (supervisord, PHP-FPM, Redis queue).

**Spec:** Investigation findings in this conversation (2026-10-04). Summary of root causes:
1. `modules.source_owner`/`source_repo` are NULL everywhere: nothing populates them and `module.json` has no repository field.
2. `GitHubReleaseFetcher` calls `/releases/latest`, which returns 404 when a repo only has prereleases (all ours are `-beta`).
3. `updates.behavior.allow_prereleases` defaults to `false`.
4. `ModuleUpdater::applyUpdate()` moves the first dashed sibling directory onto the module path (can move another module).
5. `ModuleBackupService::restoreBackup()` extracts root-less backups into `modules/` instead of `modules/<name>/`.
6. Updates run inside a web request limited to 60 s; "update all" stops after the first module.
7. Production has `opcache.validate_timestamps=Off`, so updated PHP files are not picked up by PHP-FPM.
8. No scheduler runs the existing `CheckModuleUpdatesJob`.

## Global constraints

- `declare(strict_types=1)` in every PHP file; no `any`/untyped params; PHPStan level 8 must stay clean for touched files.
- Architecture boundaries: Domain → Application → Infrastructure → Presentation; inner layers never import outer ones.
- All user-facing text through `__()` with keys in `lang/es/filament.php` and `lang/en/filament.php`; Spanish-style capitalization ("Comprobar actualizaciones", not Title Case).
- Tests run with `php vendor/bin/phpunit` (host) — SQLite in memory. Docker-only commands are not required for tests.
- Commits: conventional messages in English, no `Co-Authored-By`.
- Staging and swap directories MUST live inside `config('modules.path')`, never in `storage/`: production bind-mounts `modules/` and `storage/` separately and `rename()` across mounts fails with EXDEV.
- Temporary directories inside `modules/` MUST start with a dot so discovery ignores them.

## Review focus

1. A release ZIP whose root folder is not the module (wrong module, multiple roots, `../` entries) must be rejected before the installed module is touched — pinned in Task 6.
2. A failure after the swap (migration error, health check) must leave the previous version in place and its assets republished — pinned in Task 8.
3. Updating one module must never touch sibling module directories (regression for root cause 4) — pinned in Task 8.
4. A GitHub API error (403 rate limit, 404 private repo, network) must surface as an error in the page/command, not as "all up to date" — pinned in Tasks 3 and 4.
5. A module without `repository` must be listed as "sin repositorio configurado", not silently skipped — pinned in Task 5.

---

## Phase 1 — detection

### Task 1: `repository` field in the manifest, synced on discovery

**Files:**
- Modify: `src/app/Application/Modules/DTOs/ModuleManifestDTO.php`
- Modify: `src/app/Infrastructure/Modules/Services/ModuleManagerService.php` (`discover()`, both branches)
- Modify: `src/app/Infrastructure/Modules/Services/ModuleDiscoveryService.php` (skip dot directories)
- Test: `src/tests/Unit/Application/Modules/DTOs/ModuleManifestDTOTest.php`
- Test: `src/tests/Integration/Infrastructure/Modules/Services/ModuleManagerServiceTest.php`
- Test: `src/tests/Unit/Infrastructure/Modules/Services/ModuleDiscoveryServiceTest.php`

**Interfaces:**
- Produces: `ModuleManifestDTO::$repository: ?string` (format `owner/repo`), `ModuleManifestDTO::repositoryOwner(): ?string`, `ModuleManifestDTO::repositoryName(): ?string`. Discovery calls `Module::updateSourceInfo(string $owner, string $repo)` when the manifest declares a repository; when it does not, the stored source is left untouched (so a source set manually in Task 5b survives).

- [ ] **Step 1: Failing DTO tests**

```php
public function test_from_array_parses_repository(): void
{
    $dto = ModuleManifestDTO::fromArray([
        'name' => 'game-tables', 'version' => '1.0.0',
        'namespace' => 'Modules\\GameTables', 'provider' => 'Modules\\GameTables\\GameTablesServiceProvider',
        'repository' => 'DavidMRGaona/guildforge-game-tables',
    ]);

    $this->assertSame('DavidMRGaona/guildforge-game-tables', $dto->repository);
    $this->assertSame('DavidMRGaona', $dto->repositoryOwner());
    $this->assertSame('guildforge-game-tables', $dto->repositoryName());
}

public function test_from_array_rejects_malformed_repository(): void
{
    $this->expectException(InvalidArgumentException::class);

    ModuleManifestDTO::fromArray([
        'name' => 'x', 'version' => '1.0.0', 'namespace' => 'Modules\\X', 'provider' => 'Modules\\X\\P',
        'repository' => 'https://github.com/owner/repo',
    ]);
}

public function test_repository_is_optional(): void
{
    $dto = ModuleManifestDTO::fromArray([
        'name' => 'x', 'version' => '1.0.0', 'namespace' => 'Modules\\X', 'provider' => 'Modules\\X\\P',
    ]);

    $this->assertNull($dto->repository);
    $this->assertNull($dto->repositoryOwner());
}
```

- [ ] **Step 2: Run** `php vendor/bin/phpunit --filter ModuleManifestDTOTest` — expect failures (unknown property).

- [ ] **Step 3: Implement** — add `public ?string $repository = null` as the last constructor parameter; in `fromArray()`:

```php
$repository = $data['repository'] ?? null;
if ($repository !== null && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', (string) $repository) !== 1) {
    throw new InvalidArgumentException("Invalid repository, expected 'owner/repo': {$repository}");
}
```

pass `repository: $repository`, add it to `toArray()` when not null, and:

```php
public function repositoryOwner(): ?string
{
    return $this->repository === null ? null : explode('/', $this->repository, 2)[0];
}

public function repositoryName(): ?string
{
    return $this->repository === null ? null : explode('/', $this->repository, 2)[1];
}
```

- [ ] **Step 4: Failing discovery tests** (in `ModuleManagerServiceTest`, reuse its `createTestModule()` helper; extend the helper to merge extra manifest keys if it does not already):

```php
public function test_discover_sets_source_from_manifest_repository(): void
{
    $this->createTestModule('repo-module', ['repository' => 'acme/guildforge-repo-module']);

    $this->service->discover();

    $module = $this->service->find(new ModuleName('repo-module'));
    $this->assertSame('acme', $module?->sourceOwner());
    $this->assertSame('guildforge-repo-module', $module?->sourceRepo());
}

public function test_discover_syncs_source_for_existing_modules(): void
{
    $this->createTestModule('repo-module');
    $this->service->discover();

    $this->writeManifestKey('repo-module', 'repository', 'acme/new-repo');
    $this->service->discover();

    $this->assertSame('new-repo', $this->service->find(new ModuleName('repo-module'))?->sourceRepo());
}

public function test_discover_keeps_manual_source_when_manifest_has_no_repository(): void
{
    $this->createTestModule('repo-module');
    $this->service->discover();
    $repository = app(ModuleRepositoryInterface::class);
    $module = $repository->findByName(new ModuleName('repo-module'));
    $module->updateSourceInfo('manual', 'manual-repo');
    $repository->save($module);

    $this->service->discover();

    $this->assertSame('manual', $repository->findByName(new ModuleName('repo-module'))?->sourceOwner());
}
```

Add a private helper `writeManifestKey(string $module, string $key, string $value)` that decodes, sets and re-encodes `module.json`.

And in `ModuleDiscoveryServiceTest`:

```php
public function test_it_ignores_dot_directories(): void
{
    // create modules/.staging-x/module.json with a valid manifest
    // assert discover() does not return it
}
```

(write it with the same fixture helpers the file already uses).

- [ ] **Step 5: Run** — expect the new tests to fail.

- [ ] **Step 6: Implement**
  - `ModuleDiscoveryService`: `if ($dir === '.' || $dir === '..' || str_starts_with($dir, '.')) { continue; }`
  - `ModuleManagerService::discover()` existing branch, after the description sync:

```php
if ($manifest->repository !== null
    && ($existing->sourceOwner() !== $manifest->repositoryOwner() || $existing->sourceRepo() !== $manifest->repositoryName())) {
    $existing->updateSourceInfo((string) $manifest->repositoryOwner(), (string) $manifest->repositoryName());
    $needsSave = true;
}
```

  - New-module branch: pass `sourceOwner: $manifest->repositoryOwner(), sourceRepo: $manifest->repositoryName()` to `new Module(...)`.

- [ ] **Step 7: Run** the three test classes — all pass. Run PHPStan on the three source files.

- [ ] **Step 8: Commit** `feat: read each module's GitHub repository from module.json`

### Task 2: declare `repository` in every module

**Files (each in its own module repo):** `src/modules/<name>/module.json` for announcements, channel-notifications, cookie-consent, event-registrations, game-tables, memberships, tournaments, venue-bookings. Value = the repo's `origin` (`git -C src/modules/<name> remote get-url origin`), e.g. `DavidMRGaona/guildforge-venue-booking` (singular — the remote name is the source of truth, not the module name). Skip `security-test` (no repo of its own).

- [ ] **Step 1:** Add `"repository": "<owner>/<repo>"` after `"version"` in each `module.json`.
- [ ] **Step 2:** `docker exec guildforge_app php artisan module:discover` and verify `source_owner/source_repo` are filled for the 8 modules.
- [ ] **Step 3:** Commit per module: `chore: declare the module's GitHub repository for the updater`. Do NOT tag yet (releases happen at the end, Task 14).

### Task 3: fetch the newest release from the releases list

**Files:**
- Modify: `src/app/Application/Updates/Services/GitHubReleaseFetcherInterface.php`
- Modify: `src/app/Infrastructure/Updates/Services/GitHubReleaseFetcher.php`
- Modify: `src/app/Domain/Updates/Exceptions/UpdateException.php` (new `githubRequestFailed`)
- Modify: `src/app/Infrastructure/Updates/Services/CoreUpdateChecker.php` (call site keeps `includePrereleases: false`)
- Test: `src/tests/Integration/Infrastructure/Updates/Services/GitHubReleaseFetcherTest.php`

**Interfaces:**
- Produces: `getLatestRelease(string $owner, string $repo, bool $includePrereleases = false): ?GitHubReleaseInfo` — newest non-draft release by semver (not by date), skipping tags that are not valid versions; `null` only when the repo has no matching release. Throws `UpdateException::githubRequestFailed(string $repository, string $reason)` on any non-2xx or transport error (including 404, which for a configured repo means missing or private).
- Produces: `batchFetchLatestReleases(array $repos, bool $includePrereleases = false): array<string, GitHubReleaseInfo|UpdateException|null>` — per repo the release, `null`, or the exception (so one failing repo does not hide the rest).
- Cache: the raw release list is cached per repo under `updates.github_releases.{owner}.{repo}` for `updates.cache.ttl`; errors are never cached. `clearCache()` without arguments forgets only the keys of repos fetched in this process — never `Cache::flush()`.

- [ ] **Step 1: Failing tests** (replace the `releases/latest` fakes in the existing tests with `releases*` fakes; add):

```php
public function test_returns_highest_prerelease_when_prereleases_included(): void
{
    Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([
        $this->release('v1.0.9-beta', prerelease: true),
        $this->release('v1.0.10-beta', prerelease: true),
        $this->release('v1.1.0', draft: true),
    ])]);

    $release = $this->fetcher->getLatestRelease('o', 'r', includePrereleases: true);

    $this->assertSame('1.0.10-beta', $release?->version->value());
}

public function test_skips_prereleases_by_default(): void
{
    Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([
        $this->release('v2.0.0-beta', prerelease: true),
        $this->release('v1.5.0'),
    ])]);

    $this->assertSame('1.5.0', $this->fetcher->getLatestRelease('o', 'r')?->version->value());
}

public function test_ignores_tags_that_are_not_versions(): void
{
    Http::fake(['api.github.com/repos/o/r/releases*' => Http::response([
        $this->release('nightly'),
        $this->release('v1.0.0'),
    ])]);

    $this->assertSame('1.0.0', $this->fetcher->getLatestRelease('o', 'r')?->version->value());
}

public function test_throws_on_api_error_and_does_not_cache_it(): void
{
    Http::fake(['api.github.com/repos/o/r/releases*' => Http::sequence()
        ->push(['message' => 'API rate limit exceeded'], 403)
        ->push([$this->release('v1.0.0')], 200)]);

    try {
        $this->fetcher->getLatestRelease('o', 'r');
        $this->fail('Expected UpdateException');
    } catch (UpdateException $e) {
        $this->assertStringContainsString('403', $e->getMessage());
    }

    $this->assertSame('1.0.0', $this->fetcher->getLatestRelease('o', 'r')?->version->value());
}

public function test_batch_returns_exception_for_failing_repo_and_release_for_others(): void
{
    Http::fake([
        'api.github.com/repos/o/ok/releases*' => Http::response([$this->release('v1.0.0')]),
        'api.github.com/repos/o/private/releases*' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $results = $this->fetcher->batchFetchLatestReleases([
        ['owner' => 'o', 'repo' => 'ok'], ['owner' => 'o', 'repo' => 'private'],
    ]);

    $this->assertSame('1.0.0', $results['o/ok']?->version->value());
    $this->assertInstanceOf(UpdateException::class, $results['o/private']);
}

public function test_clear_cache_does_not_flush_other_cache_entries(): void
{
    Cache::put('unrelated', 'keep');

    $this->fetcher->clearCache();

    $this->assertSame('keep', Cache::get('unrelated'));
}

/** @return array<string, mixed> */
private function release(string $tag, bool $prerelease = false, bool $draft = false): array
{
    $version = ltrim($tag, 'v');

    return [
        'tag_name' => $tag, 'prerelease' => $prerelease, 'draft' => $draft, 'body' => '',
        'published_at' => '2026-10-04T10:00:00Z',
        'assets' => [
            ['name' => "m-{$version}.zip", 'browser_download_url' => "https://example.test/m-{$version}.zip"],
            ['name' => "m-{$version}.zip.sha256", 'browser_download_url' => "https://example.test/m-{$version}.zip.sha256"],
        ],
    ];
}
```

- [ ] **Step 2: Run** `php vendor/bin/phpunit --filter GitHubReleaseFetcherTest` — expect failures.

- [ ] **Step 3: Implement**
  - `UpdateException::githubRequestFailed(string $repository, string $reason): self` → message `"GitHub request for '{$repository}' failed: {$reason}"`.
  - `fetchReleases(owner, repo): array` → `GET repos/{o}/{r}/releases?per_page=30`; non-2xx → throw `githubRequestFailed("{$o}/{$r}", "HTTP {$status}")`; transport `\Throwable` → rethrow wrapped. Cache only successful lists (`Cache::put` after success; `Cache::get` first).
  - `getLatestRelease()` → from the list: drop `draft === true`, drop `prerelease === true` unless included, map through `GitHubReleaseInfo::fromGitHubResponse()` inside `try/catch (InvalidModuleVersionException)` to skip bad tags, return the max by `ModuleVersion::compare`/`isGreaterThan`.
  - `batchFetchLatestReleases()` → per repo `try { getLatestRelease } catch (UpdateException $e) { $results[$key] = $e; }`.
  - Track fetched cache keys in a private array; `clearCache()` without args forgets those keys.
  - `CoreUpdateChecker`: no change needed if it calls `getLatestRelease($owner, $repo)` (default `false`); wrap its call in `try/catch (UpdateException)` returning its existing "no update" value and logging, so core behaviour is unchanged.

- [ ] **Step 4: Run** the fetcher tests plus `CoreUpdateCheckerTest` — pass.
- [ ] **Step 5: Commit** `fix: detect module releases from the releases list instead of /releases/latest`

### Task 4: checker with release channel policy and reported errors

**Files:**
- Create: `src/app/Application/Updates/DTOs/UpdateCheckResultDTO.php`
- Create: `src/app/Application/Updates/Services/ReleaseChannelPolicy.php`
- Modify: `src/app/Application/Updates/Services/ModuleUpdateCheckerInterface.php` (add `checkAll()`)
- Modify: `src/app/Infrastructure/Updates/Services/ModuleUpdateChecker.php`
- Modify: `src/app/Domain/Modules/Entities/Module.php` (`clearLatestAvailableVersion()`)
- Test: `src/tests/Unit/Application/Updates/Services/ReleaseChannelPolicyTest.php`
- Test: `src/tests/Integration/Infrastructure/Updates/Services/ModuleUpdateCheckerTest.php`

**Interfaces:**
- Produces: `final readonly class ReleaseChannelPolicy { public function __construct(private bool $allowPrereleases) {} public function includesPrereleasesFor(ModuleVersion $installed): bool }` — true when config allows or `$installed->preRelease !== null`. Bound in `AppServiceProvider` (or the updates provider) as `new ReleaseChannelPolicy((bool) config('updates.behavior.allow_prereleases', false))`.
- Produces: `final readonly class UpdateCheckResultDTO { /** @param Collection<int, AvailableUpdateDTO> $updates @param array<string, string> $errors moduleName => message @param list<string> $modulesWithoutSource */ }`.
- Produces: `ModuleUpdateCheckerInterface::checkAll(): UpdateCheckResultDTO`; `checkAllForUpdates()` stays and returns `$this->checkAll()->updates`.
- Persistence per checked module: `last_update_check_at = now` always; `latest_available_version = newer version` or cleared when none.

- [ ] **Step 1: Failing policy tests**

```php
public function test_includes_prereleases_when_installed_version_is_prerelease(): void
{
    $this->assertTrue((new ReleaseChannelPolicy(false))->includesPrereleasesFor(ModuleVersion::fromString('1.0.8-beta')));
}

public function test_excludes_prereleases_for_stable_install_unless_allowed(): void
{
    $this->assertFalse((new ReleaseChannelPolicy(false))->includesPrereleasesFor(ModuleVersion::fromString('1.0.8')));
    $this->assertTrue((new ReleaseChannelPolicy(true))->includesPrereleasesFor(ModuleVersion::fromString('1.0.8')));
}
```

- [ ] **Step 2: Failing checker tests** (the existing test builds the checker with mocked repository/fetcher; add the policy as third constructor arg):

```php
public function test_check_all_finds_prerelease_update_for_beta_install(): void
{
    $module = $this->moduleWithSource('event-registrations', '1.0.8-beta');
    $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection([$module]));
    $this->moduleRepository->shouldReceive('save')->once();
    $this->fetcher->shouldReceive('batchFetchLatestReleases')
        ->with([['owner' => 'o', 'repo' => 'event-registrations']], true)
        ->andReturn(['o/event-registrations' => $this->releaseInfo('1.0.9-beta', prerelease: true)]);

    $result = $this->checker->checkAll();

    $this->assertSame('1.0.9-beta', $result->updates->first()?->availableVersion);
    $this->assertSame([], $result->errors);
}

public function test_check_all_reports_github_errors_per_module(): void
{
    $module = $this->moduleWithSource('game-tables', '1.0.0-beta');
    $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection([$module]));
    $this->moduleRepository->shouldReceive('save');
    $this->fetcher->shouldReceive('batchFetchLatestReleases')
        ->andReturn(['o/game-tables' => UpdateException::githubRequestFailed('o/game-tables', 'HTTP 403')]);

    $result = $this->checker->checkAll();

    $this->assertTrue($result->updates->isEmpty());
    $this->assertStringContainsString('HTTP 403', $result->errors['game-tables']);
}

public function test_check_all_lists_modules_without_source(): void
{
    $module = $this->moduleWithoutSource('local-module', '1.0.0');
    $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection([$module]));

    $this->assertSame(['local-module'], $this->checker->checkAll()->modulesWithoutSource);
}

public function test_check_all_clears_stale_latest_version_when_up_to_date(): void
{
    $module = $this->moduleWithSource('m', '1.0.9-beta');
    $module->updateLatestAvailableVersion('1.0.9-beta');
    $this->moduleRepository->shouldReceive('all')->andReturn(new ModuleCollection([$module]));
    $this->moduleRepository->shouldReceive('save')->once();
    $this->fetcher->shouldReceive('batchFetchLatestReleases')
        ->andReturn(['o/m' => $this->releaseInfo('1.0.9-beta', prerelease: true)]);

    $this->checker->checkAll();

    $this->assertNull($module->latestAvailableVersion());
    $this->assertNotNull($module->lastUpdateCheckAt());
}
```

(`moduleWithSource`/`moduleWithoutSource`/`releaseInfo` are private helpers building a `Module` with `sourceOwner: 'o', sourceRepo: $name` and a `GitHubReleaseInfo`; adapt to the helpers already in the file.)

- [ ] **Step 3: Run** — fail.
- [ ] **Step 4: Implement**
  - `Module::clearLatestAvailableVersion(): void { $this->latestAvailableVersion = null; }`
  - Checker: group modules by "includes prereleases" (one `batchFetchLatestReleases` call per group, at most two), then per module: exception → `$errors[$name] = $e->getMessage()`; release newer → update DTO + `updateLatestAvailableVersion`; otherwise `clearLatestAvailableVersion()`; always `updateLastCheckAt(now)` and `save()`. Modules without source → `$modulesWithoutSource[]`.
  - Remove the now-unused `allowPrereleases`/`batchCheck` config reads; `checkForUpdate()`/`forceCheck()` use the policy and `getLatestRelease(..., $policy->includesPrereleasesFor($module->version()))`, letting `UpdateException` propagate.
  - Register `ReleaseChannelPolicy` in the provider that binds `ModuleUpdateCheckerInterface`.
- [ ] **Step 5: Run** checker + policy tests, then `php vendor/bin/phpunit --filter Updates` — pass.
- [ ] **Step 6: Commit** `fix: report update check errors and follow the installed release channel`

### Task 5: surface results in the page and the command; manual source command

**Files:**
- Modify: `src/app/Filament/Pages/ModuleUpdatesPage.php`
- Modify: `src/resources/views/filament/pages/module-updates.blade.php`
- Modify: `src/app/Console/Commands/Updates/CheckModuleUpdatesCommand.php`
- Create: `src/app/Console/Commands/Updates/SetModuleSourceCommand.php` (`module:set-source {name} {repository}`)
- Modify: `src/app/Application/Updates/DTOs/AvailableUpdateDTO.php` (`publishedAt` nullable + `static fromModule(Module $module): self`)
- Modify: `src/lang/es/filament.php`, `src/lang/en/filament.php`
- Test: `src/tests/Feature/Filament/Pages/ModuleUpdatesPageTest.php` (create)
- Test: `src/tests/Feature/Console/Commands/Updates/CheckModuleUpdatesCommandTest.php`
- Test: `src/tests/Feature/Console/Commands/Updates/SetModuleSourceCommandTest.php` (create)

**Interfaces:**
- Consumes: `checkAll(): UpdateCheckResultDTO` (Task 4).
- Produces: page public props `array $checkErrors` (moduleName => message) and `list<string> $modulesWithoutSource`; `mount()` fills `availableUpdates` from persisted state (`Module::hasAvailableUpdate()` via `AvailableUpdateDTO::fromModule`) without calling GitHub; `getNavigationBadge(): ?string` = count of modules with an available update (null when 0).

- [ ] **Step 1: Failing tests**
  - Page (Livewire test, admin user as in `ModulesPageTest`): mount shows a persisted update (module saved with `latest_available_version` > `version`); `checkForUpdates` with a mocked `ModuleUpdateCheckerInterface` returning errors → `assertSet('checkErrors', ['game-tables' => '...'])` and sends a danger notification; navigation badge equals `'1'`.
  - Command: errors are printed with `$this->error()` and exit code `1`; modules without source are printed as a warning.
  - `module:set-source game-tables acme/repo` saves owner/repo; malformed repository → exit code `1`; unknown module → exit code `1`.
- [ ] **Step 2: Run** — fail.
- [ ] **Step 3: Implement** the page/command/command changes; Blade: an error list (`x-filament::section` with danger color) per module, a "sin repositorio configurado" list, and `{{ $update->publishedAt?->format('d/m/Y') ?? '—' }}`. Translation keys under `filament.updates.modules.check_errors`, `.without_source`, `.without_source_hint` ("Añade \"repository\" a su module.json o usa module:set-source").
- [ ] **Step 4: Run** the three test classes — pass. `./vendor/bin/pint` on touched files.
- [ ] **Step 5: Commit** `feat: show update check errors and modules without a repository`

## Phase 2 — safe application

### Task 6: `ModulePackageInstaller` — stage, swap, revert, clean up

**Files:**
- Create: `src/app/Application/Updates/Services/ModulePackageInstallerInterface.php`
- Create: `src/app/Infrastructure/Updates/Services/ModulePackageInstaller.php`
- Modify: provider binding the update services (same one as Task 4)
- Test: `src/tests/Integration/Infrastructure/Updates/Services/ModulePackageInstallerTest.php`

**Interfaces:**
- Produces:

```php
interface ModulePackageInstallerInterface
{
    /** Extracts the release ZIP into modules/.staging-{name}-{id}/ and returns the module root inside it. */
    public function stage(string $zipPath, string $moduleName): string;

    /** Moves modules/{name} to modules/.previous-{name}-{id} and the staged root to modules/{name}. Returns the previous path. */
    public function swap(string $stagedRoot, string $moduleName): string;

    /** Puts the previous version back at modules/{name}, deleting the new one. */
    public function revert(string $previousPath, string $moduleName): void;

    /** Deletes a staging or previous directory. */
    public function discard(string $path): void;

    /** Deletes leftover .staging-{name}-* and .previous-{name}-* directories from interrupted runs. */
    public function cleanupLeftovers(string $moduleName): void;
}
```

- Throws `UpdateException::extractionFailed($moduleName, $reason)` for: unreadable ZIP; any entry that is absolute or contains `..` (zip-slip); not exactly one top-level directory; missing `module.json` in that directory; `module.json` `name` different from `$moduleName`. In every rejection the staging directory is removed and `modules/{name}` is untouched.
- All paths are derived from `config('modules.path')`; directories are created with `0775`.

- [ ] **Step 1: Failing tests** (real filesystem under a temp `modules.path`, ZIPs built in the test with `ZipArchive`):

```php
protected function setUp(): void
{
    parent::setUp();
    $this->modulesPath = sys_get_temp_dir().'/gf-installer-'.uniqid();
    File::makeDirectory($this->modulesPath, 0775, true);
    config(['modules.path' => $this->modulesPath]);
    $this->installer = new ModulePackageInstaller;
    $this->makeModule('game-tables', '1.0.0');
    $this->makeModule('channel-notifications', '1.0.0');
}

public function test_stage_and_swap_install_the_new_version(): void
{
    $zip = $this->releaseZip('game-tables', '1.1.0');

    $previous = $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

    $this->assertSame('1.1.0', $this->manifestVersion('game-tables'));
    $this->assertSame('1.0.0', json_decode(File::get($previous.'/module.json'), true)['version']);
}

public function test_swap_never_touches_sibling_modules(): void
{
    $zip = $this->releaseZip('game-tables', '1.1.0');

    $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

    $this->assertSame('channel-notifications', json_decode(File::get($this->modulesPath.'/channel-notifications/module.json'), true)['name']);
}

public function test_revert_restores_previous_version(): void
{
    $zip = $this->releaseZip('game-tables', '1.1.0');
    $previous = $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

    $this->installer->revert($previous, 'game-tables');

    $this->assertSame('1.0.0', $this->manifestVersion('game-tables'));
    $this->assertDirectoryDoesNotExist($previous);
}

/** @return iterable<string, array{0: array<string, string>}> */
public static function invalidPackages(): iterable
{
    yield 'other module' => [['tournaments-1.0.0/module.json' => '{"name":"tournaments","version":"1.0.0"}']];
    yield 'two roots' => [['a/module.json' => '{"name":"game-tables"}', 'b/x.txt' => 'x']];
    yield 'no manifest' => [['game-tables-1.1.0/readme.md' => 'x']];
    yield 'zip slip' => [['game-tables-1.1.0/module.json' => '{"name":"game-tables"}', '../evil.php' => '<?php']];
}

#[DataProvider('invalidPackages')]
public function test_stage_rejects_invalid_packages_without_touching_the_module(array $entries): void
{
    $zip = $this->zipWith($entries);

    try {
        $this->installer->stage($zip, 'game-tables');
        $this->fail('Expected UpdateException');
    } catch (UpdateException) {
    }

    $this->assertSame('1.0.0', $this->manifestVersion('game-tables'));
    $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
    $this->assertFileDoesNotExist(dirname($this->modulesPath).'/evil.php');
}

public function test_cleanup_leftovers_removes_only_this_modules_temp_dirs(): void
{
    File::makeDirectory($this->modulesPath.'/.staging-game-tables-old');
    File::makeDirectory($this->modulesPath.'/.previous-game-tables-old');
    File::makeDirectory($this->modulesPath.'/.staging-tournaments-old');

    $this->installer->cleanupLeftovers('game-tables');

    $this->assertDirectoryDoesNotExist($this->modulesPath.'/.staging-game-tables-old');
    $this->assertDirectoryDoesNotExist($this->modulesPath.'/.previous-game-tables-old');
    $this->assertDirectoryExists($this->modulesPath.'/.staging-tournaments-old');
}
```

Helpers: `makeModule($name, $version)` writes `module.json` + `src/Dummy.php`; `releaseZip($name, $version)` builds `{name}-{version}/module.json` (+ `public/build/manifest.json`) like the CI workflow; `zipWith(array $entries)` writes arbitrary entries (use `ZipArchive::addFromString`, which accepts `../evil.php`); `manifestVersion($name)`.

- [ ] **Step 2: Run** — fail (class missing).
- [ ] **Step 3: Implement** `ModulePackageInstaller`:
  - `stage()`: `$staging = "{$modulesPath}/.staging-{$name}-".Str::random(8)`; open ZIP; loop `for ($i = 0; $i < $zip->numFiles; $i++)` over `getNameIndex($i)`: reject if `str_starts_with($entry, '/')`, `preg_match('#(^|/)\.\.(/|$)#', $entry)`, or contains `\\`; collect `explode('/', $entry)[0]` as roots; require exactly one root and that `{root}/module.json` is an entry; `extractTo($staging)`; decode `{$staging}/{$root}/module.json`, require `name === $moduleName`; on any failure `discard($staging)` then throw. Return `"{$staging}/{$root}"`.
  - `swap()`: `$previous = "{$modulesPath}/.previous-{$name}-".Str::random(8)`; `rename($target, $previous)` (throw on false); `rename($stagedRoot, $target)` — on false, `rename($previous, $target)` and throw; then `discard(dirname($stagedRoot))`; return `$previous`.
  - `revert()`: if target exists `File::deleteDirectory($target)`; `rename($previous, $target)` (throw `UpdateException::rollbackFailed` on false).
  - `discard()`: `File::deleteDirectory($path)` when it exists.
  - `cleanupLeftovers()`: `glob("{$modulesPath}/.staging-{$name}-*", GLOB_ONLYDIR)` + `.previous-{$name}-*` → `discard`.
- [ ] **Step 4: Run** — pass.
- [ ] **Step 5: Commit** `feat: stage module releases and swap them in atomically`

### Task 7: restore backups into the module directory

**Files:**
- Modify: `src/app/Infrastructure/Updates/Services/ModuleBackupService.php` (`restoreBackup`)
- Test: `src/tests/Integration/Infrastructure/Updates/Services/ModuleBackupServiceTest.php`

- [ ] **Step 1: Failing test**

```php
public function test_restore_puts_files_back_inside_the_module_directory(): void
{
    // arrange a real module dir with module.json and src/Foo.php, mock moduleManager->find() to return it
    $backup = $this->service->createBackup($name);
    File::deleteDirectory($modulePath);

    $this->service->restoreBackup($name, $backup);

    $this->assertFileExists($modulePath.'/module.json');
    $this->assertFileExists($modulePath.'/src/Foo.php');
    $this->assertFileDoesNotExist(dirname($modulePath).'/module.json');
}
```

(follow the fixture style already used in the file.)
- [ ] **Step 2: Run** — fail (`module.json` lands in the parent).
- [ ] **Step 3: Implement** — in `restoreBackup()`: `File::ensureDirectoryExists($modulePath, 0775)` after deleting, then `$this->extractZipBackup($backupPath, $modulePath)`.
- [ ] **Step 4: Run** — pass.
- [ ] **Step 5: Commit** `fix: restore module backups into the module directory`

### Task 8: `ModuleUpdater` uses the installer and reverts on failure

**Files:**
- Modify: `src/app/Infrastructure/Updates/Services/ModuleUpdater.php`
- Test: `src/tests/Integration/Infrastructure/Updates/Services/ModuleUpdaterTest.php`

**Interfaces:**
- Consumes: `ModulePackageInstallerInterface` (Task 6), `ReleaseChannelPolicy` (Task 4), `getLatestRelease(..., bool $includePrereleases)` (Task 3).
- Constructor gains `ModulePackageInstallerInterface $installer` and `ReleaseChannelPolicy $channelPolicy` (append at the end; update the test's constructor call).

New flow of `update()` (replaces steps 1–8):
1. lock; history; `installer->cleanupLeftovers($name)`.
2. release = `getLatestRelease($owner, $repo, $channelPolicy->includesPrereleasesFor($module->version()))`; not newer → `noUpdateAvailable`.
3. backup (kept as manual safety net; restore via `rollback()` only).
4. download to `{tempPath}/{name}-{version}.zip`; verify checksum when `verify_checksum` is on — if the release has no checksum asset and verification is on, throw `UpdateException::checksumFetchFailed` (fail closed).
5. `$staged = installer->stage($zip, $name)` — the module is still enabled and untouched up to here.
6. disable module; `$previous = installer->swap($staged, $name)`; `publishPreBuiltAssets()`.
7. migrations + new seeders (unchanged helpers); health check.
8. save version; enable; `installer->discard($previous)`; history completed.
- `catch`: if `$previous !== null` → `installer->revert($previous, $name)`, `publishPreBuiltAssets()` again (old assets), enable, `markRolledBack()`; else if the module was disabled → enable; else nothing to undo; history failed. `finally`: delete the temp ZIP if it exists; discard `$staged` parent if it still exists; release lock.
- Delete the old private `applyUpdate()`.

- [ ] **Step 1: Failing tests** (use a real temp `modules.path` with two modules; `githubFetcher->downloadRelease` mocked with `andReturnUsing(fn ($r, $dest) => copy($this->fixtureZip, $dest) ? $dest : $dest)`; real `ModulePackageInstaller`; `moduleManager->disable/enable` mocked; `healthChecker->check` returns a passing DTO):

```php
public function test_update_installs_new_version_and_keeps_siblings(): void
{
    // modules: game-tables 1.0.0-beta (source o/game-tables), channel-notifications 1.0.0
    // release 1.0.1-beta with fixture zip game-tables-1.0.1-beta/module.json
    $result = $this->service->update(ModuleName::fromString('game-tables'));

    $this->assertTrue($result->isSuccess());
    $this->assertSame('1.0.1-beta', $this->manifestVersion('game-tables'));
    $this->assertSame('channel-notifications', $this->manifestName('channel-notifications'));
    $this->assertSame([], glob($this->modulesPath.'/.*-game-tables-*', GLOB_ONLYDIR));
    $this->assertFileDoesNotExist($this->tempDir.'/game-tables-1.0.1-beta.zip');
}

public function test_update_reverts_when_health_check_fails(): void
{
    // health check returns failing DTO
    $result = $this->service->update(ModuleName::fromString('game-tables'));

    $this->assertTrue($result->wasRolledBack());
    $this->assertSame('1.0.0-beta', $this->manifestVersion('game-tables'));
}

public function test_update_rejecting_package_leaves_module_enabled_and_untouched(): void
{
    // fixture zip contains tournaments-1.0.0/module.json
    $this->moduleManager->shouldNotReceive('disable');

    $result = $this->service->update(ModuleName::fromString('game-tables'));

    $this->assertFalse($result->isSuccess());
    $this->assertSame('1.0.0-beta', $this->manifestVersion('game-tables'));
}

public function test_update_requests_prereleases_for_beta_installs(): void
{
    $this->githubFetcher->shouldReceive('getLatestRelease')->with('o', 'game-tables', true)->once()->andReturn(null);

    $result = $this->service->update(ModuleName::fromString('game-tables'));

    $this->assertFalse($result->isSuccess()); // noUpdateAvailable is caught and returned as a failed result
}
```
- [ ] **Step 2: Run** — fail.
- [ ] **Step 3: Implement** the flow above. Keep `runMigrations()`/`runNewSeeders()` as they are.
- [ ] **Step 4: Run** `php vendor/bin/phpunit --filter "ModuleUpdaterTest|ModulePackageInstallerTest|ModuleBackupServiceTest"` — pass; PHPStan on `ModuleUpdater.php`.
- [ ] **Step 5: Commit** `fix: apply module updates through a staged swap that reverts on failure`

## Phase 3 — execution

### Task 9: queued updates that restart the worker

**Files:**
- Modify: `src/app/Infrastructure/Updates/Jobs/UpdateModuleJob.php`
- Test: `src/tests/Unit/Infrastructure/Updates/Jobs/UpdateModuleJobTest.php` (create)

**Interfaces:**
- Produces: `UpdateModuleJob implements ShouldQueue, ShouldBeUnique` (`uniqueId()` already exists, `uniqueFor = 900`). After `$updater->update()` returns (success or failure), `Artisan::call('queue:restart')` so the long-lived worker reloads module classes after finishing this job.

- [ ] **Step 1: Failing test**

```php
public function test_handle_updates_module_and_restarts_queue_workers(): void
{
    $updater = Mockery::mock(ModuleUpdaterInterface::class);
    $updater->shouldReceive('update')->once()->andReturn($this->result(UpdateStatus::Completed));
    Artisan::shouldReceive('call')->once()->with('queue:restart');

    (new UpdateModuleJob('game-tables'))->handle($updater);
}

public function test_job_is_unique_per_module(): void
{
    $this->assertInstanceOf(ShouldBeUnique::class, new UpdateModuleJob('game-tables'));
    $this->assertSame('module-update:game-tables', (new UpdateModuleJob('game-tables'))->uniqueId());
}
```

- [ ] **Step 2: Run** — fail.
- [ ] **Step 3: Implement** (`implements ShouldQueue, ShouldBeUnique`; `public int $uniqueFor = 900;`; call `Artisan::call('queue:restart')` in a `finally` after the update attempt).
- [ ] **Step 4: Run** — pass.
- [ ] **Step 5: Commit** `feat: run module updates as unique jobs and restart workers afterwards`

### Task 10: page dispatches jobs and polls progress

**Files:**
- Modify: `src/app/Filament/Pages/ModuleUpdatesPage.php`
- Modify: `src/resources/views/filament/pages/module-updates.blade.php`
- Modify: `src/lang/es/filament.php`, `src/lang/en/filament.php`
- Test: `src/tests/Feature/Filament/Pages/ModuleUpdatesPageTest.php`

**Interfaces:**
- Produces: `updateModule(string $moduleName)` → `UpdateModuleJob::dispatch($moduleName)`, adds the name to `public array $queuedModules` and stores `public ?string $queuedSince` (ISO time). `updateAllModules()` dispatches one job per available update (no early break). `pollUpdates(): void` — for each queued module, reads its newest `ModuleUpdateHistoryModel` with `started_at >= queuedSince`; when the status is terminal (`completed`, `failed`, `rolled_back`) it notifies (success/danger, translated), removes it from `queuedModules`, and reloads `availableUpdates` from persisted state. The view renders `wire:poll.3s="pollUpdates"` only while `queuedModules` is not empty, plus a "En cola…/Actualizando…" badge per module.

- [ ] **Step 1: Failing tests** (`Queue::fake()`; Livewire test as admin):
  - `updateModule('game-tables')` → `Queue::assertPushed(UpdateModuleJob::class, fn ($j) => $j->moduleName === 'game-tables')` and `assertSet('queuedModules', ['game-tables'])`.
  - `updateAllModules()` with two persisted updates → two jobs pushed.
  - `pollUpdates()` after creating a `completed` history row for `game-tables` → `assertSet('queuedModules', [])` and a success notification (`assertNotified()`).
- [ ] **Step 2: Run** — fail.
- [ ] **Step 3: Implement**; remove the synchronous `$updater->update()` call and the `isUpdating` loop. Translation keys: `filament.updates.modules.notifications.update_queued` ("Actualización de :module en cola"), `.status.queued` ("En cola"), `.status.running` ("Actualizando").
- [ ] **Step 4: Run** — pass; Pint on touched files.
- [ ] **Step 5: Commit** `feat: queue module updates from the admin page and follow their progress`

### Task 11: scheduled check and production runtime

**Files:**
- Modify: `src/routes/console.php`
- Modify: `docker/prod/supervisord.conf`
- Modify: `docker/prod/php.ini`
- Test: `src/tests/Feature/Console/ScheduleTest.php` (create)

- [ ] **Step 1: Failing test**

```php
public function test_module_update_check_is_scheduled_daily(): void
{
    $events = collect(app(Schedule::class)->events());

    $this->assertTrue($events->contains(
        fn (Event $event): bool => str_contains((string) $event->description, CheckModuleUpdatesJob::class)
            && $event->expression === '0 4 * * *'
    ));
}
```

- [ ] **Step 2: Run** — fail.
- [ ] **Step 3: Implement**
  - `routes/console.php`: `Schedule::job(new CheckModuleUpdatesJob)->dailyAt('04:00')->name(CheckModuleUpdatesJob::class)->withoutOverlapping();`
  - `supervisord.conf`: new program

```ini
[program:scheduler]
command=php /var/www/html/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
```

  - `php.ini`: `opcache.validate_timestamps=1` (keep `opcache.revalidate_freq=2`), with a comment: modules are updated at runtime from the admin panel, so PHP-FPM must notice changed files without a container restart.
- [ ] **Step 4: Run** the schedule test — pass. `docker compose -f docker-compose.prod.yml config` is not needed; verify after deploy (Task 13).
- [ ] **Step 5: Commit** `feat: check module updates daily and let PHP-FPM pick up updated files`

### Task 12: documentation

**Files:**
- Modify: `.claude/skills/modules.md` (manifest fields: `repository`), `.claude/rules/filament.md` ("Update system pages": queued updates, polling), `docs/module-ci-cd.md` (release → detection flow), module scaffolding stub if `module:make` generates `module.json` (grep `stubs` for `"provider"`) so new modules include `"repository"`.
- [ ] **Step 1:** Update the docs (Spanish-style capitalization in headings).
- [ ] **Step 2: Commit** `docs: describe the module repository field and the update flow`

## Rollout

### Task 13: verify, release and bootstrap production

- [ ] **Step 1:** Full gates: `php vendor/bin/phpunit` (host), `./vendor/bin/phpstan analyse` on `app/Infrastructure/Updates app/Application/Updates app/Filament/Pages/ModuleUpdatesPage.php app/Infrastructure/Modules`, `npm run lint`, `npm run type-check 2>&1 | grep -E '^resources/js'` (must be empty).
- [ ] **Step 2:** Push core; wait for CI + Deploy (`gh run watch`).
- [ ] **Step 3:** Release the 8 modules (Task 2 commits): bump patch in `module.json`, commit `chore: release X.Y.Z-beta`, tag `vX.Y.Z-beta`, push main + tag; wait for each `Module release` run.
- [ ] **Step 4 (ask the user before running — production writes):** for each instance container on `ssh forge` (currently `pgcwo8o8c0wwwwksg448sg0g-*` = runesword, `sws40ckccw848gsw8wgwgk4s-*` = tolerol), run `php artisan module:set-source <name> <owner/repo>` for each installed module (installed modules lack the `repository` field until their first update).
- [ ] **Step 5:** On runesword: `php artisan module:check-updates --force` must list `event-registrations 1.0.8-beta → 1.0.9-beta` (or newer). Then, with the user's go-ahead, run the update from the admin page and confirm: history `completed`, `modules/event-registrations/module.json` has the new version, no `.staging-*`/`.previous-*` dirs left, `php -i | grep validate_timestamps` = On, and `supervisorctl status` (or `ps`) shows `schedule:work`.
