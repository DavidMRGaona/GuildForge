<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use App\Application\Linking\Contracts\FindsLinkableRecordsOfOrganizer;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use RuntimeException;

/**
 * Linkable record source that also answers what each account organizes: whatever a test puts in
 * $organized under the account's id. It throws while $fails is set.
 */
final class OrganizerFakeLinkableRecordSource extends FakeLinkableRecordSource implements FindsLinkableRecordsOfOrganizer
{
    /** @var array<string, list<LinkableRecordDTO>> */
    public static array $organized = [];

    /** @var list<array{string, int}> Account id and limit of every organizedBy() call, in order. */
    public static array $organizerCalls = [];

    public static bool $fails = false;

    public function type(): string
    {
        return 'organized-fake';
    }

    public function organizedBy(string $userId, int $limit): array
    {
        self::$organizerCalls[] = [$userId, $limit];

        if (self::$fails) {
            throw new RuntimeException('Organized records unavailable');
        }

        return array_slice(self::$organized[$userId] ?? [], 0, $limit);
    }
}
