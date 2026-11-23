<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Services;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Services\SettingsServiceInterface;
use Throwable;

/**
 * Which sources the index is up to date with (core#837): for each type, a fingerprint of the source as it
 * was when its usages were last synced without failing. The library shows the images of a source only while
 * that fingerprint matches the source now, so a source that has just declared ShowsMediaPreviews (or changed
 * class) never shows rows written before it could hide them, and one whose last sync failed shows nothing.
 * Anything unreadable counts as never synced: it fails closed.
 */
final readonly class MediaSourceSyncState
{
    public const string KEY = 'media_library_source_syncs';

    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    /**
     * What a sync is up to date with: the class and whether it shows its previews. Hashed, since the name of
     * an anonymous class holds a NUL byte, which PostgreSQL does not store in text.
     */
    public static function fingerprint(MediaUsageSourceInterface $source): string
    {
        return sha1($source::class.'|'.($source instanceof ShowsMediaPreviews ? 'previews' : 'concealed'));
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

        return array_filter(
            $decoded,
            static fn (mixed $fingerprint, mixed $type): bool => is_string($type) && is_string($fingerprint),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function isCurrent(string $type, MediaUsageSourceInterface $source): bool
    {
        return ($this->all()[$type] ?? null) === self::fingerprint($source);
    }

    /**
     * Records the outcome of a sync: synced types take the fingerprint of their source now, failed ones lose
     * theirs. Types of sources not read this time (a disabled module) are kept, so they need a new sync once
     * they come back only if they changed.
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
}
