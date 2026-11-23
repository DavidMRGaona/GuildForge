<?php

declare(strict_types=1);

namespace App\Application\Linking\Services;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What consumers of linkable records use instead of asking the sources themselves: it fails
 * closed in one place.
 *
 * A type without a source (its module disabled or uninstalled, a source that cannot be built)
 * and a source that throws or answers with something else than what was asked are treated
 * alike: the record is not shown. Only the second is an error and is logged; the first is
 * ordinary (a module was turned off) and logs nothing. Nothing here throws.
 *
 * Drafts and panel-only records are for who may open the panel: a viewer without admin.access
 * (or whose check fails, logged as an error) is asked for as LinkableRecordSourceInterface::VISITOR,
 * so they still see what is published. Unknown, deactivated and anonymized accounts are visitors too.
 */
interface LinkableRecordLookupInterface
{
    /** Default number of records a search returns. */
    public const int DEFAULT_SEARCH_LIMIT = 20;

    /** Most records a search may ask a source for. */
    public const int MAX_SEARCH_LIMIT = 50;

    /** More distinct ids than this in one findMany() call are still answered, with a warning. */
    public const int MANY_IDS_WARNING = 500;

    /**
     * The record as the viewer may see it, or null when it does not exist, the viewer may not
     * see it, its type has no source or the source failed.
     */
    public function find(string $type, string $id, string $viewerId): ?LinkableRecordDTO;

    /**
     * The records of the type among $ids that the viewer may see, keyed by id, in the order
     * asked (repeated ids count once). An id that does not exist or is hidden is simply absent.
     * Ids are matched exactly as given (lowercase uuids are the norm): an id spelled otherwise
     * than the source's answer is dropped.
     *
     * Pass one page of ids. FindsLinkableRecordsInBatches::MAX_IDS caps what a source gets per
     * call, not this call: a batch source is asked once per MAX_IDS ids, any other source once
     * per id through find(). Every id is answered, never truncated; more than MANY_IDS_WARNING
     * distinct ids are logged as a warning. Empty when the type has no source or the
     * source failed for any of them; records answered for ids not asked, or of another type, are
     * dropped and logged.
     *
     * @param  list<string>  $ids
     * @return array<string, LinkableRecordDTO>
     */
    public function findMany(string $type, array $ids, string $viewerId): array;

    /**
     * Records of the type matching the query that the viewer may see, at most $limit (capped at
     * MAX_SEARCH_LIMIT; below 1 nothing). Empty when the type has no source or the source failed.
     *
     * @return list<LinkableRecordDTO>
     */
    public function search(string $type, string $query, string $viewerId, int $limit = self::DEFAULT_SEARCH_LIMIT): array;

    /**
     * Records of the type the account organizes (FindsLinkableRecordsOfOrganizer), at most
     * $limit (capped at MAX_SEARCH_LIMIT; below 1 nothing), the most relevant first.
     *
     * Pass only the signed-in account (the request's user, the impersonated one while
     * impersonating), never someone else's: the source may answer what only that person sees,
     * such as their private records. Offering these to choose from is not enough either: check
     * again on the server, when saving, that what was chosen is still among them.
     *
     * Empty when the type has no source, the source does not implement the contract, the account
     * is not a uuid, unknown, deactivated or anonymized, or the source failed (logged); records of
     * another type are dropped and logged. Nothing here throws.
     *
     * A record answered twice is kept once. There is no panel check before asking (the person may
     * not open the panel), so every panelUrl is dropped unless the account may open it
     * (admin.access, asked once per call).
     *
     * @return list<LinkableRecordDTO>
     */
    public function organizedBy(string $type, Authenticatable $organizer, int $limit = self::DEFAULT_SEARCH_LIMIT): array;

    /**
     * Label of every type that can be linked right now, keyed by type, in registration order
     * (e.g. to fill a type picker). A source whose label cannot be read is left out and logged.
     *
     * @return array<string, string>
     */
    public function types(): array;
}
