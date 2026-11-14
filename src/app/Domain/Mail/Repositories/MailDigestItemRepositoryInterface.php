<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\Mail\ValueObjects\MailDigestItem;
use App\Domain\ValueObjects\UserId;
use Closure;
use DateTimeInterface;

/**
 * Notices kept for each person's daily digest (core#333). Instants are compared in the storage
 * zone; the account's deletion removes its rows.
 */
interface MailDigestItemRepositoryInterface
{
    public function add(UserId $userId, string $category, string $line, ?string $url, ?string $subjectType, ?string $subjectId, ?string $actorId = null): void;

    /**
     * Delete the notices kept because of the account, whoever they belong to (core#537).
     */
    public function deleteForActor(string $actorId): void;

    /**
     * Delete the lines kept for the daily digest about a thing, whoever they belong to: of one
     * category when the task they asked for is done, of every category when the thing is gone
     * (core#681). How many lines went.
     */
    public function deleteForSubject(string $type, string $id, ?string $category = null): int;

    /**
     * Accounts with notices kept before the instant.
     *
     * @return list<string>
     */
    public function userIdsWithItemsBefore(DateTimeInterface $before): array;

    /**
     * Run $send inside one transaction with the person's notices kept before the instant,
     * oldest first and locked, and delete them once it returns. If it throws, nothing is
     * deleted and the exception goes on.
     *
     * @param  Closure(list<MailDigestItem>): void  $send
     */
    public function takeForUser(UserId $userId, DateTimeInterface $before, Closure $send): void;

    /**
     * Delete the notices kept before the instant, whoever they belong to.
     *
     * @return int How many were deleted.
     */
    public function pruneBefore(DateTimeInterface $before): int;
}
