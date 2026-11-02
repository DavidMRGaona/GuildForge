<?php

declare(strict_types=1);

namespace App\Application\Search\Contracts;

use App\Application\Search\DTOs\SearchDocumentDTO;

/**
 * A source whose records are copied into the search index and found from there.
 */
interface IndexedSearchSourceInterface
{
    /**
     * Stable identifier of the kind of record (e.g. "event"); unique across sources.
     */
    public function type(): string;

    /**
     * Plural, user-facing name of the group (e.g. "Eventos").
     */
    public function label(): string;

    /**
     * Singular, user-facing name of one record (e.g. "Evento").
     */
    public function itemLabel(): string;

    /**
     * User-facing tag for records that are over (e.g. "Pasado"), or null when the type has none.
     */
    public function pastLabel(): ?string;

    /**
     * The document for one record, or null when it must not be searchable (missing, hidden, cancelled).
     */
    public function document(string $id): ?SearchDocumentDTO;

    /**
     * Every searchable record.
     *
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable;
}
