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
use Throwable;

/**
 * Which sources the index is up to date with (core#837): for each type, a fingerprint of the source as it
 * was when its usages were last synced without failing. The fingerprint is the class, whether it shows its
 * previews and the version of the code that gives it: the module's installed version, or the core's for core
 * sources. The library shows a file's image only while every source that claims it, and every source with a
 * usage of it, is up to date, so rows written by older code (which may hide less) are never trusted, and a
 * source whose last sync failed shows nothing. Anything unreadable counts as never synced: it fails closed.
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
     * The types of the given sources whose last sync ran with the source as it is now and did not fail.
     *
     * @param  iterable<MediaUsageSourceInterface>  $sources
     * @return list<string>
     */
    public function upToDate(iterable $sources): array
    {
        $synced = $this->all();

        return array_keys(array_filter(
            $this->fingerprints($sources),
            static fn (string $fingerprint, string $type): bool => ($synced[$type] ?? null) === $fingerprint,
            ARRAY_FILTER_USE_BOTH,
        ));
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
     * not change meanwhile.
     *
     * @param  array<string, string>  $synced  fingerprint by type
     * @param  list<string>  $failed  types
     */
    public function record(array $synced, array $failed): void
    {
        $state = [...$this->all(), ...$synced];

        foreach ($failed as $type) {
            unset($state[$type]);
        }

        ksort($state);
        $this->settings->set(self::KEY, (string) json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
