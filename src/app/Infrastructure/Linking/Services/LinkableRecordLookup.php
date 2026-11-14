<?php

declare(strict_types=1);

namespace App\Infrastructure\Linking\Services;

use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use Generator;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class LinkableRecordLookup implements LinkableRecordLookupInterface
{
    public function __construct(
        private LinkableRecordSourceRegistryInterface $registry,
    ) {}

    public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO
    {
        $source = $this->registry->source($type);

        if (! $source instanceof LinkableRecordSourceInterface) {
            return null;
        }

        try {
            $record = $source->find($id, $viewerId);
        } catch (Throwable $exception) {
            $this->reportFailure('find', $source, $type, $exception);

            return null;
        }

        if ($record instanceof LinkableRecordDTO && ($record->type !== $type || $record->id !== $id)) {
            Log::error('[LinkableRecordLookup] Source answered with another record, not shown', [
                'source' => $source::class,
                'type' => $type,
                'id' => $id,
                'answeredType' => $record->type,
                'answeredId' => $record->id,
            ]);

            return null;
        }

        return $record;
    }

    public function findMany(string $type, array $ids, string $viewerId): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        $source = $this->registry->source($type);

        if (! $source instanceof LinkableRecordSourceInterface) {
            return [];
        }

        $answered = [];
        $strays = 0;

        try {
            foreach ($this->answers($source, $ids, $viewerId) as [$asked, $records]) {
                // Read inside the try: a source breaking its own return type fails here too
                foreach ($records as $record) {
                    if ($record->type !== $type || ! isset($asked[$record->id])) {
                        $strays++;

                        continue;
                    }

                    $answered[$record->id] ??= $record;
                }
            }
        } catch (Throwable $exception) {
            $this->reportFailure('findMany', $source, $type, $exception);

            return [];
        }

        if ($strays > 0) {
            Log::error('[LinkableRecordLookup] Source answered with records not asked for, not shown', [
                'source' => $source::class,
                'type' => $type,
                'count' => $strays,
            ]);
        }

        $records = [];

        foreach ($ids as $id) {
            if (isset($answered[$id])) {
                $records[$id] = $answered[$id];
            }
        }

        return $records;
    }

    public function search(string $type, string $query, string $viewerId, int $limit = self::DEFAULT_SEARCH_LIMIT): array
    {
        if ($limit < 1) {
            return [];
        }

        $source = $this->registry->source($type);

        if (! $source instanceof LinkableRecordSourceInterface) {
            return [];
        }

        $limit = min($limit, self::MAX_SEARCH_LIMIT);

        $records = [];
        $strays = 0;

        try {
            // Read inside the try: a source breaking its own return type fails here too
            foreach ($source->search($query, $viewerId, $limit) as $record) {
                if ($record->type !== $type) {
                    $strays++;

                    continue;
                }

                $records[] = $record;
            }
        } catch (Throwable $exception) {
            $this->reportFailure('search', $source, $type, $exception);

            return [];
        }

        if ($strays > 0) {
            Log::error('[LinkableRecordLookup] Source answered with records of another type, not shown', [
                'source' => $source::class,
                'type' => $type,
                'count' => $strays,
            ]);
        }

        return array_slice($records, 0, $limit);
    }

    public function types(): array
    {
        $types = [];

        foreach ($this->registry->sources() as $type => $source) {
            try {
                $types[$type] = $source->label();
            } catch (Throwable $exception) {
                $this->reportFailure('label', $source, $type, $exception);
            }
        }

        return $types;
    }

    /**
     * What the source answers for the ids, one call at a time: a batch of at most MAX_IDS when
     * it can, otherwise one find() per id. Each answer comes with the ids that call asked.
     *
     * @param  non-empty-list<string>  $ids
     * @return Generator<int, array{array<string, true>, list<LinkableRecordDTO>}>
     */
    private function answers(LinkableRecordSourceInterface $source, array $ids, string $viewerId): Generator
    {
        if ($source instanceof FindsLinkableRecordsInBatches) {
            foreach (array_chunk($ids, FindsLinkableRecordsInBatches::MAX_IDS) as $chunk) {
                yield [array_fill_keys($chunk, true), $source->findMany($chunk, $viewerId)];
            }

            return;
        }

        foreach ($ids as $id) {
            $record = $source->find($id, $viewerId);

            yield [[$id => true], $record instanceof LinkableRecordDTO ? [$record] : []];
        }
    }

    private function reportFailure(string $operation, LinkableRecordSourceInterface $source, string $type, Throwable $exception): void
    {
        Log::error('[LinkableRecordLookup] Linkable record source failed, nothing shown', [
            'operation' => $operation,
            'source' => $source::class,
            'type' => $type,
            'exception' => $exception::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
