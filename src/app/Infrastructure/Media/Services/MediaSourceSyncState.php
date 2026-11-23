<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaSourceRefreshFailureModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Which sources the index is up to date with (core#837): for each type, a fingerprint of the source as it
 * was when its usages were last synced without failing. The fingerprint is the class, whether it shows its
 * previews and the version of the code that gives it: the module's installed version, or the core's for core
 * sources. The library shows a file's image only while every source that claims it, and every source with a
 * usage of it, is up to date, so rows written by older code (which may hide less) are never trusted, and a
 * source whose last sync failed shows nothing. Anything unreadable counts as never synced: it fails closed.
 *
 * A failed refresh of one record (core#844) leaves its type out too, in a table of its own so failures written
 * at once never lose each other: the row may still say what the source no longer says (a usage now hidden).
 * Only a sync that read the source completely brings it back, and only if no refresh failed while it ran.
 */
final readonly class MediaSourceSyncState
{
    public const string KEY = 'media_library_source_syncs';

    public function __construct(
        private SettingsServiceInterface $settings,
        private MediaSourceRegistryInterface $registry,
        private ModuleManagerServiceInterface $modules,
        private CoreVersionServiceInterface $core,
    ) {}

    /**
     * Hashed, since the name of an anonymous class holds a NUL byte, which PostgreSQL does not store in text.
     */
    public static function fingerprint(MediaUsageSourceInterface $source, string $version): string
    {
        return sha1($source::class.'|'.($source instanceof ShowsMediaPreviews ? 'previews' : 'concealed').'|'.$version);
    }

    /**
     * The fingerprint of each source as it is now, by type. A source whose type or version cannot be read is
     * left out: it is never up to date.
     *
     * @param  iterable<MediaUsageSourceInterface>  $sources
     * @return array<string, string>
     */
    public function fingerprints(iterable $sources): array
    {
        $fingerprints = [];
        /** @var array<string, ?string> $versions by module, '' for the core */
        $versions = [];

        foreach ($sources as $source) {
            try {
                $type = $source->type();
                $module = $this->registry->moduleOf($type) ?? '';
                $version = array_key_exists($module, $versions) ? $versions[$module] : ($versions[$module] = $this->versionOf($module));
            } catch (Throwable) {
                continue;
            }

            if ($version !== null) {
                $fingerprints[$type] = self::fingerprint($source, $version);
            }
        }

        return $fingerprints;
    }

    /**
     * The types of the given sources whose last sync ran with the source as it is now and did not fail, and
     * whose records were all refreshed since without failing.
     *
     * @param  iterable<MediaUsageSourceInterface>  $sources
     * @return list<string>
     */
    public function upToDate(iterable $sources): array
    {
        $failures = $this->refreshFailures();

        if ($failures === null) {
            return [];
        }

        $synced = $this->all();

        return array_keys(array_filter(
            $this->fingerprints($sources),
            static fn (string $fingerprint, string $type): bool => ($synced[$type] ?? null) === $fingerprint
                && ! array_key_exists($type, $failures),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Leaves a type out of date after a refresh of one of its records failed or could not be queued. Never
     * throws: a failure that cannot be written is logged, since nothing else can conceal the type then.
     */
    public function markRefreshFailed(string $type): void
    {
        try {
            MediaSourceRefreshFailureModel::query()->upsert(
                [[
                    'source_type' => $type,
                    'token' => (string) Str::uuid(),
                    'failed_at' => (new MediaSourceRefreshFailureModel)->fromDateTime(CarbonImmutable::now()),
                ]],
                ['source_type'],
                ['token', 'failed_at'],
            );
        } catch (Throwable $e) {
            Log::error('[Media] A failed media refresh could not be recorded; the library may show its files until the next sync.', [
                'type' => $type,
                'exception' => $e,
            ]);
        }
    }

    /**
     * @return array<string, string>|null the token of each type's refresh failure, null when unreadable
     */
    public function refreshFailures(): ?array
    {
        try {
            /** @var array<string, string> */
            return MediaSourceRefreshFailureModel::query()->pluck('token', 'source_type')->all();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string> fingerprint of each type's last sync that did not fail
     */
    public function all(): array
    {
        try {
            $stored = $this->settings->get(self::KEY);
            $decoded = is_string($stored) && $stored !== '' ? json_decode($stored, true, 3, JSON_THROW_ON_ERROR) : [];
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $state = [];

        foreach ($decoded as $type => $fingerprint) {
            if (is_string($type) && is_string($fingerprint)) {
                $state[$type] = $fingerprint;
            }
        }

        return $state;
    }

    /**
     * Records the outcome of a sync: synced types take the given fingerprint, failed ones lose theirs. Types
     * of sources not read this time (a disabled module) are kept: they come back up to date only if they did
     * not change meanwhile. A synced type's refresh failure is cleared only when it is the one read before
     * the sync read the source: one written meanwhile has another token and stays.
     *
     * @param  array<string, string>  $synced  fingerprint by type
     * @param  list<string>  $failed  types
     * @param  array<string, string>  $refreshFailuresBefore  token by type, as refreshFailures() gave them
     */
    public function record(array $synced, array $failed, array $refreshFailuresBefore = []): void
    {
        $state = [...$this->all(), ...$synced];

        foreach ($failed as $type) {
            unset($state[$type]);
        }

        ksort($state);
        $this->settings->set(self::KEY, (string) json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        foreach (array_diff_key(array_intersect_key($refreshFailuresBefore, $synced), array_flip($failed)) as $type => $token) {
            $this->clearRefreshFailure((string) $type, $token);
        }
    }

    /**
     * A failure left in place keeps the type concealed: it fails closed.
     */
    private function clearRefreshFailure(string $type, string $token): void
    {
        try {
            MediaSourceRefreshFailureModel::query()
                ->where('source_type', $type)
                ->where('token', $token)
                ->delete();
        } catch (Throwable $e) {
            Log::warning('[Media] A media refresh failure could not be cleared; the type stays concealed until the next sync.', [
                'type' => $type,
                'exception' => $e,
            ]);
        }
    }

    /**
     * The installed version of the module, or the core's for '' (a core source); null when it cannot be read.
     */
    private function versionOf(string $module): ?string
    {
        try {
            if ($module === '') {
                return 'core:'.$this->core->getCurrentVersion()->value();
            }

            $installed = $this->modules->findByName($module);

            return $installed instanceof Module ? $module.':'.$installed->version()->value() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
