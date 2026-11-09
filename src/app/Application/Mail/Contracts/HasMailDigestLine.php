<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

use App\Application\Mail\DTOs\MailDigestLineDTO;

/**
 * Optional, next to HasMailCategory: the line a notice of a digestible category takes in the
 * daily digest (core#333). Without it, or when it returns null or throws, the digest shows the
 * subject of the mail and links to its button.
 */
interface HasMailDigestLine
{
    /**
     * Written when the notice is kept, in the locale of that moment.
     */
    public function mailDigestLine(object $notifiable): ?MailDigestLineDTO;
}
