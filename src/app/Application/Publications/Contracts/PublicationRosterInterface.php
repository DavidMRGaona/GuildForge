<?php

declare(strict_types=1);

namespace App\Application\Publications\Contracts;

use App\Application\Publications\DTOs\PublicationMembershipDTO;
use App\Application\Publications\DTOs\PublicationRosterDTO;

/**
 * Optional capability of a publication source: who takes part in its records (the players of
 * a campaign) and who leads them (its directors). A source implements it next to
 * PublicationSourceInterface; listeners check it with instanceof. The owner dispatches
 * PublicationRosterChanged when someone joins, leaves, starts or stops leading.
 *
 * User ids are account UUIDs: listeners pass them in lowercase, and sources compare them without
 * regard to case.
 */
interface PublicationRosterInterface
{
    /**
     * Who takes part and who leads, as it is now, for exactly the records find() returns: null
     * when the record does not exist or is not published, and the roster of a cancelled or ended
     * record that is still published (its state is find()'s). Leaders may take part as well.
     */
    public function roster(string $id): ?PublicationRosterDTO;

    /**
     * The records of this source the user takes part in or leads, published and neither cancelled
     * nor ended, so a listener can send a person's complete list. isLeader is true for a leader
     * whether or not they also take part. Empty for an unknown user.
     *
     * @return list<PublicationMembershipDTO>
     */
    public function rostersOf(string $userId): array;
}
