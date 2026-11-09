<?php

declare(strict_types=1);

namespace App\Application\Discussions\Contracts;

use App\Application\Discussions\DTOs\SubjectDiscussionDTO;

/**
 * The activity of a record's discussion elsewhere (a forum), asked by the page that shows the
 * record: an event, a game table, a campaign. Types are those of the publication sources
 * (`event`, `game-table`, `campaign`).
 *
 * A forum module implements it; without one the core's default answers null and the page shows
 * nothing. The implementation decides what each person may see (a visitor is a null user) and
 * never calls the forum while answering: it reads what it keeps.
 */
interface SubjectDiscussionInterface
{
    public function forSubject(string $type, string $id, ?string $userId): ?SubjectDiscussionDTO;
}
