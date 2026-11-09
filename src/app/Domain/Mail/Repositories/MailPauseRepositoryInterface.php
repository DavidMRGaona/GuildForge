<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\Mail\ValueObjects\MailPause;
use App\Domain\ValueObjects\UserId;
use DateTimeInterface;

/**
 * Each person's pause of the optional mail. No row means no pause.
 */
interface MailPauseRepositoryInterface
{
    /**
     * The stored pause, running or already over: telling them apart is up to the caller.
     */
    public function find(UserId $userId): ?MailPause;

    /**
     * Start a pause, replacing the one the person already has.
     *
     * @param  DateTimeInterface|null  $until  Null for a pause that lasts until the person resumes.
     */
    public function pause(UserId $userId, ?DateTimeInterface $until): MailPause;

    public function resume(UserId $userId): void;
}
