<?php

declare(strict_types=1);

namespace App\Application\Linking\Contracts;

use App\Application\Linking\DTOs\LinkableRecordDTO;

/**
 * Optional for a linkable record source: answers the records one account organizes, so another
 * module can let that person link their own data only to what is theirs (a venue booking to the
 * game tables they run) without depending on whoever owns the records.
 *
 * LinkableRecordLookupInterface::organizedBy() uses it and answers nothing for a source without
 * it. Unlike search(), which reaches people without panel access as visitors, this gets the
 * account's own id: the lookup only ever asks it about the signed-in person, about their own
 * records, so what only the owner sees (a private table, a hidden direction) may be answered.
 */
interface FindsLinkableRecordsOfOrganizer extends LinkableRecordSourceInterface
{
    /**
     * The records of this type the account organizes, as the owner defines it (for game tables,
     * who created one or directs it), among those the site shows that very person: the same rule
     * as the module's own pages for that viewer, never a draft only the panel opens. At most
     * $limit, the most relevant first (for game tables, the upcoming ones). Every DTO carries
     * type().
     *
     * @param  string  $userId  The account's uuid in lower case, never a visitor: the lookup has
     *                          already left out unknown, deactivated and anonymized accounts
     * @return list<LinkableRecordDTO>
     */
    public function organizedBy(string $userId, int $limit): array;
}
