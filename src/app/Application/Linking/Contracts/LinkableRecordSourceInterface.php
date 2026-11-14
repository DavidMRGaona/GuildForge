<?php

declare(strict_types=1);

namespace App\Application\Linking\Contracts;

use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * Offers a part of the site's records (events, tournaments, bookings…) so another module can
 * link its own data to them without depending on whoever owns them.
 *
 * Modules return their implementations from ModuleServiceProvider::registerLinkableRecordSources();
 * the core offers its events. Unlike the site search, a source reaches drafts and records that only
 * the panel shows, so it decides, for each viewer, what that person may see.
 *
 * Consumers go through LinkableRecordLookupInterface, which treats a type without a source and a
 * source that throws alike: the record is not shown (the second is logged as an error).
 * Viewer ids are account UUIDs; an id with no account sees only what a visitor would. The lookup
 * only hands a source the viewer's own id when that account may open the panel (admin.access);
 * anyone else reaches it as VISITOR, so a source never checks panel access to hide its drafts.
 */
interface LinkableRecordSourceInterface
{
    /**
     * Viewer id the lookup passes for whoever may not open the panel (or has no usable account):
     * no account has it, so the source answers what a visitor would see.
     */
    public const string VISITOR = '';

    /**
     * Unique, stable kebab-case type of the records this source offers (e.g. 'event'). Whoever
     * links to a record stores it, so it must never change.
     */
    public function type(): string;

    /**
     * Human-readable, translated label for this kind of record (e.g. «Evento»).
     */
    public function label(): string;

    /**
     * Records matching the query, for a picker in the panel: only those the viewer may see,
     * at most $limit, the most relevant first. An empty query lists the latest ones.
     * Every DTO's type equals type().
     *
     * @return list<LinkableRecordDTO>
     */
    public function search(string $query, string $viewerId, int $limit): array;

    /**
     * The record, or null both when it does not exist and when the viewer may not see it, so
     * nobody learns that a record exists without being allowed to see it. The DTO's type equals
     * type() and its id equals $id.
     */
    public function find(string $id, string $viewerId): ?LinkableRecordDTO;
}
