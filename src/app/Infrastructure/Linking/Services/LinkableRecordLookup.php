<?php

declare(strict_types=1);

namespace App\Infrastructure\Linking\Services;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
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
