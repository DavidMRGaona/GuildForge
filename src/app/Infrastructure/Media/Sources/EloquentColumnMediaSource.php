<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Media usage source for an Eloquent model that keeps one stored image path in one column.
 * Public so modules can extend it for their own models.
 */
abstract class EloquentColumnMediaSource implements MediaUsageSourceInterface
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        protected readonly MediaFileIdResolverInterface $resolver,
    ) {}

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    abstract protected function column(): string;

    /**
     * Translated name of what the file is for («Imagen»).
     */
    abstract protected function role(): string;

    abstract protected function titleOf(Model $record): string;

    abstract protected function editUrlOf(Model $record, object $user): ?string;

    /**
     * The editor's content folder belongs to the sources that read rich text.
     *
     * @return list<string>
     */
    public function excludedSubfolders(): array
    {
        return ['content/'];
    }

    public function hidden(): bool
    {
        return false;
    }

    /**
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable
    {
        $column = $this->column();

        $records = $this->query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->lazyById(self::CHUNK_SIZE);

        foreach ($records as $record) {
            $usage = $this->usageOf($record);

            if ($usage instanceof MediaUsageDTO) {
                yield $usage;
            }
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        $record = $this->find($recordId);
        $usage = $record instanceof Model ? $this->usageOf($record) : null;

        return $usage instanceof MediaUsageDTO ? [$usage] : [];
    }

    public function usesFile(MediaFileId $file): bool
    {
        $column = $this->column();
        $query = $this->query()->select($column);

        // Prefilter in SQL as wide as the resolver: stored forms may be URLs without extension, URL-encoded
        // or with backslashes, so only the longest run of characters that survive all of those is matched.
        // The exact id is confirmed in PHP below.
        $piece = $this->longestPlainRun($file->value);

        if ($piece !== '') {
            $wrapped = $query->getQuery()->getGrammar()->wrap($column);
            // The wrapped identifier comes from the source's own column(), never from input
            $sql = "{$wrapped} LIKE ? ESCAPE '\\'";
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $piece);

            $query->where(function (Builder $inner) use ($column, $file, $sql, $escaped): void {
                $inner->where($column, $file->value)
                    // @phpstan-ignore argument.type
                    ->orWhereRaw($sql, ['%'.$escaped.'%']);
            });
        }

        $candidates = $query->lazy(self::CHUNK_SIZE);

        foreach ($candidates as $candidate) {
            $stored = $candidate->getAttribute($column);

            if (! is_string($stored) || $stored === '') {
                continue;
            }

            $id = $this->resolver->fromStoredPath($stored);

            if ($id instanceof MediaFileId && $id->equals($file)) {
                return true;
            }
        }

        return false;
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        $record = $this->find($recordId);

        return $record instanceof Model ? $this->editUrlOf($record, $user) : null;
    }

    /**
     * Base query over the records, trashed ones included: a soft-deleted record can still be restored.
     *
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        $query = $this->modelClass()::query();

        if ($this->usesSoftDeletes()) {
            // @phpstan-ignore method.notFound
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Longest run of letters, digits, hyphens and underscores: never encoded, never case-folded by the resolver.
     */
    private function longestPlainRun(string $id): string
    {
        $runs = preg_split('/[^A-Za-z0-9_-]+/', $id, -1, PREG_SPLIT_NO_EMPTY);
        $best = '';

        foreach ($runs === false ? [] : $runs as $run) {
            if (strlen($run) > strlen($best)) {
                $best = $run;
            }
        }

        return $best;
    }

    private function usageOf(Model $record): ?MediaUsageDTO
    {
        $stored = $record->getAttribute($this->column());

        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        $recordId = $record->getKey();

        return new MediaUsageDTO(
            storedPath: $stored,
            recordId: is_scalar($recordId) ? (string) $recordId : '',
            role: $this->role(),
            title: $this->titleOf($record),
        );
    }

    private function find(string $recordId): ?Model
    {
        $model = new ($this->modelClass());

        // PostgreSQL rejects a malformed uuid instead of finding nothing
        if (in_array(HasUuids::class, class_uses_recursive($model), true) && ! Str::isUuid($recordId)) {
            return null;
        }

        return $this->query()->whereKey($recordId)->first();
    }

    private function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass()), true);
    }
}
