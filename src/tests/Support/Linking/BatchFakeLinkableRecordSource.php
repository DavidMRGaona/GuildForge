<?php

declare(strict_types=1);

namespace Tests\Support\Linking;

use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use LogicException;

/**
 * A source that answers a batch in one call, with the records of $records asked for. It records
 * every batch it is asked, and refuses to be asked one record at a time.
 */
class BatchFakeLinkableRecordSource extends FakeLinkableRecordSource implements FindsLinkableRecordsInBatches
{
    /** @var list<list<string>> */
    public static array $batches = [];

    public function type(): string
    {
        return 'batch-fake';
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        throw new LogicException('A batch source must be asked through findMany().');
    }

    public function findMany(array $ids, string $viewerId): array
    {
        static::$batches[] = $ids;

        return array_values(array_filter(
            static::$records,
            fn (LinkableRecordDTO $record): bool => in_array($record->id, $ids, true),
        ));
    }
}
