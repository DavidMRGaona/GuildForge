<?php

declare(strict_types=1);

namespace App\Application\Publications\DTOs;

use App\Domain\Publications\UserIds;

/**
 * Who takes part in a publication and who leads it: account ids, in lowercase, each once.
 */
final readonly class PublicationRosterDTO
{
    /** @var list<string> */
    public array $memberIds;

    /** @var list<string> */
    public array $leaderIds;

    /**
     * @param  list<string>  $memberIds  Who takes part (the players)
     * @param  list<string>  $leaderIds  Who leads (the directors), whether they take part or not
     *
     * @throws \InvalidArgumentException when an id is not an account id (a UUID)
     */
    public function __construct(array $memberIds = [], array $leaderIds = [])
    {
        $this->memberIds = UserIds::normalize($memberIds, 'roster');
        $this->leaderIds = UserIds::normalize($leaderIds, 'roster');
    }
}
