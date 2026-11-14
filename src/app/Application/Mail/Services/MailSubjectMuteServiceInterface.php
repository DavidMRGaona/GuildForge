<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Domain\Mail\ValueObjects\MutedMailSubject;

/**
 * Muting the optional mail about one thing (a game, a campaign, a tournament...), whatever its
 * category. Mandatory mail about it is always sent. Only accounts mute things: anything else gets
 * an empty list and nothing is stored.
 */
interface MailSubjectMuteServiceInterface
{
    /**
     * What the person muted, newest first.
     *
     * @return list<MutedMailSubject>
     */
    public function mutedFor(object $user): array;

    public function isMuted(object $user, MailSubjectDTO $subject): bool;

    /**
     * Mute the thing for the person (again: the stored names are refreshed).
     */
    public function mute(object $user, MailSubjectDTO $subject): void;

    /**
     * The mute removed, or null when the person had not muted the thing.
     */
    public function unmute(object $user, string $type, string $id): ?MutedMailSubject;

    /**
     * Forget a thing that no longer exists, for everybody. Modules call it when they delete the
     * thing, so the profile does not keep listing it; its notices in the notification centre go
     * too (core#491), and so do the lines about it still waiting for the daily digest (core#681).
     */
    public function forgetSubject(string $type, string $id): void;
}
