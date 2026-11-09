<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

use App\Application\Mail\DTOs\MailSubjectDTO;

/**
 * Optional, next to HasMailCategory: the one thing (a game, a campaign, a tournament...) a mail
 * notification is about, so the person can stop the optional notices of that thing alone.
 */
interface HasMailSubject
{
    /**
     * Null when this mail is about nothing in particular. If it throws, the mail is sent as if
     * it had no subject.
     */
    public function mailSubject(): ?MailSubjectDTO;
}
