<?php

declare(strict_types=1);

namespace App\Domain\Mail\Repositories;

use App\Domain\Mail\ValueObjects\MutedMailSubject;
use App\Domain\ValueObjects\UserId;

/**
 * The things each person muted. No row means the thing is not muted.
 */
interface MailSubjectMuteRepositoryInterface
{
    /**
     * Newest first.
     *
     * @return list<MutedMailSubject>
     */
    public function mutedFor(UserId $userId): array;

    public function isMuted(UserId $userId, string $type, string $id): bool;

    /**
     * Mute a thing, or refresh the names stored for it.
     */
    public function mute(UserId $userId, string $type, string $id, string $label, ?string $typeLabel): void;

    /**
     * The mute removed, or null when the thing was not muted.
     */
    public function unmute(UserId $userId, string $type, string $id): ?MutedMailSubject;

    /**
     * Remove every person's mute of a thing that no longer exists.
     */
    public function forgetSubject(string $type, string $id): void;
}
