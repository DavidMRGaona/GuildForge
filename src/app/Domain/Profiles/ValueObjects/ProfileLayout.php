<?php

declare(strict_types=1);

namespace App\Domain\Profiles\ValueObjects;

/**
 * The owner's choice of sections and stats for their public profile, in order, stored in
 * `user_profiles.layout` as `{"sections":[{"key","enabled"}],"stats":[{"key","enabled"}]}`.
 *
 * Reading never fails: anything unreadable is left out and counts as the defaults, so a section or stat
 * missing here takes its own default. Keys nobody registers now (a disabled module) are kept, so turning
 * the module on again brings the owner's choice back.
 */
final readonly class ProfileLayout
{
    /** Per list: far more than any site registers, so a stored value never grows without bound */
    public const int MAX_ENTRIES = 50;

    /**
     * @param  list<ProfileLayoutEntry>  $sections
     * @param  list<ProfileLayoutEntry>  $stats
     */
    private function __construct(
        public array $sections,
        public array $stats,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    public static function fromArray(mixed $data): self
    {
        if (! is_array($data)) {
            return self::empty();
        }

        return new self(self::entriesOf($data['sections'] ?? null), self::entriesOf($data['stats'] ?? null));
    }

    /**
     * The owner's new choice (core#743), for what is offered now: their entries in their order, keys not
     * offered or repeated left out, followed by what this layout says of keys not offered now (a
     * disabled module), so turning that module on again brings their choice back.
     *
     * @param  list<ProfileLayoutEntry>  $sections
     * @param  list<ProfileLayoutEntry>  $stats
     * @param  list<string>  $offeredSections  the keys of the sections offered now
     * @param  list<string>  $offeredStats  the keys of the stats offered now
     */
    public function chosen(array $sections, array $stats, array $offeredSections, array $offeredStats): self
    {
        return new self(
            $this->merged($sections, $this->sections, $offeredSections),
            $this->merged($stats, $this->stats, $offeredStats),
        );
    }

    /**
     * Whether nothing was chosen: every section and stat takes its default.
     */
    public function isEmpty(): bool
    {
        return $this->sections === [] && $this->stats === [];
    }

    /**
     * The owner's choice for a section, or null when they never made one.
     */
    public function sectionEnabled(string $key): ?bool
    {
        return $this->enabledIn($this->sections, $key);
    }

    /**
     * The owner's choice for a stat, or null when they never made one.
     */
    public function statEnabled(string $key): ?bool
    {
        return $this->enabledIn($this->stats, $key);
    }

    /**
     * @return array{sections: list<array{key: string, enabled: bool}>, stats: list<array{key: string, enabled: bool}>}
     */
    public function toArray(): array
    {
        return [
            'sections' => array_map(static fn (ProfileLayoutEntry $entry): array => $entry->toArray(), $this->sections),
            'stats' => array_map(static fn (ProfileLayoutEntry $entry): array => $entry->toArray(), $this->stats),
        ];
    }

    /**
     * @return list<ProfileLayoutEntry>
     */
    private static function entriesOf(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $result = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null) || $entry['key'] === '' || ! is_bool($entry['enabled'] ?? null)) {
                continue;
            }

            // The first place of a repeated key wins
            if (isset($result[$entry['key']])) {
                continue;
            }

            $result[$entry['key']] = new ProfileLayoutEntry($entry['key'], $entry['enabled']);

            if (count($result) === self::MAX_ENTRIES) {
                break;
            }
        }

        return array_values($result);
    }

    /**
     * @param  list<ProfileLayoutEntry>  $chosen
     * @param  list<ProfileLayoutEntry>  $stored
     * @param  list<string>  $offered
     * @return list<ProfileLayoutEntry>
     */
    private function merged(array $chosen, array $stored, array $offered): array
    {
        $offered = array_flip($offered);
        $result = [];

        foreach ($chosen as $entry) {
            if (isset($offered[$entry->key]) && ! isset($result[$entry->key])) {
                $result[$entry->key] = $entry;
            }
        }

        foreach ($stored as $entry) {
            if (! isset($offered[$entry->key]) && ! isset($result[$entry->key])) {
                $result[$entry->key] = $entry;
            }
        }

        return array_slice(array_values($result), 0, self::MAX_ENTRIES);
    }

    /**
     * @param  list<ProfileLayoutEntry>  $entries
     */
    private function enabledIn(array $entries, string $key): ?bool
    {
        foreach ($entries as $entry) {
            if ($entry->key === $key) {
                return $entry->enabled;
            }
        }

        return null;
    }
}
