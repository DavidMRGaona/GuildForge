<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;

/**
 * The daily digest (core#333): notices of digestible categories the person chose to receive
 * once a day are kept instead of sent, and go out together at the hour set in the panel.
 */
interface MailDigestServiceInterface
{
    /**
     * Keep the notice for the person's next digest when they chose it for this category.
     * Only called once every delivery rule let the mail through.
     *
     * @return bool True when it was kept (so it must not be sent now). False when the person wants
     *              it at once, the category is mandatory or not digestible, or the notice cannot be
     *              written as a line (its mail is not a MailMessage).
     */
    public function holds(object $user, MailCategoryDTO $category, object $notification, ?MailSubjectDTO $subject): bool;

    /**
     * Send the digest of every person with notices kept before the last digest hour. Safe to run
     * as often as wanted: what was sent is gone, and a failure keeps the notices for next time.
     *
     * @return int How many people's digests were queued.
     */
    public function dispatchDue(): int;

    /**
     * Wall-clock time of the digest in the display zone ("08:00").
     */
    public function time(): string;
}
