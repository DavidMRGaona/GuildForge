<?php

declare(strict_types=1);

namespace App\Infrastructure\Linking\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Linking\Services\LinkableRecordSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class LinkableRecordLookup implements LinkableRecordLookupInterface
{
    public function __construct(
        private LinkableRecordSourceRegistryInterface $registry,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO
    {
        $source = $this->registry->source($type);

        if (! $source instanceof LinkableRecordSourceInterface) {
            return null;
        }

        $viewerId = $this->askedAs($viewerId, $type);

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

        if (count($ids) > self::MANY_IDS_WARNING) {
            // Still answered in full: the caller should ask one page at a time
            Log::warning('[LinkableRecordLookup] More ids asked at once than a page holds', [
                'source' => $source::class,
                'type' => $type,
                'count' => count($ids),
                'batches' => $source instanceof FindsLinkableRecordsInBatches,
            ]);
        }

        $viewerId = $this->askedAs($viewerId, $type);
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
        $viewerId = $this->askedAs($viewerId, $type);

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
     * The viewer id a source gets: the viewer's own only when that account may open the panel,
     * where drafts and panel-only records can be opened; anyone else is asked for as a visitor,
     * so no source has to check panel access itself. Accounts are resolved as the sources do:
     * a uuid, not deactivated (soft-deleted) and not anonymized. A failing check is a visitor.
     */
    private function askedAs(string $viewerId, string $type): string
    {
        if (! Str::isUuid($viewerId)) {
            return LinkableRecordSourceInterface::VISITOR;
        }

        try {
            $viewer = UserModel::query()->whereNull('anonymized_at')->find($viewerId);

            if ($viewer instanceof UserModel && $this->authorization->can($viewer, 'admin.access')) {
                return $viewerId;
            }
        } catch (Throwable $exception) {
            Log::error('[LinkableRecordLookup] Panel access could not be checked, viewer asked for as a visitor', [
                'type' => $type,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }

        return LinkableRecordSourceInterface::VISITOR;
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
