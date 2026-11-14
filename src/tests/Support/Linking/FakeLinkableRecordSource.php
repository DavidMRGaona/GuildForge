<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * Linkable record source used in tests to simulate a module offering its records.
 *
 * Its records are whatever a test puts in $records; every viewer sees them all.
 */
class FakeLinkableRecordSource implements LinkableRecordSourceInterface
{
    /** @var list<LinkableRecordDTO> */
    public static array $records = [];

    /** @var list<string> Ids asked through find(), in order. */
    public static array $askedIds = [];

    /** @var list<string> Viewer id of every call, in order. */
    public static array $viewerIds = [];

    public function type(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake record';
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        static::$viewerIds[] = $viewerId;

        return array_slice(static::$records, 0, $limit);
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        static::$askedIds[] = $id;
        static::$viewerIds[] = $viewerId;

        foreach (static::$records as $record) {
            if ($record->id === $id) {
                return $record;
            }
        }

        return null;
    }
}
