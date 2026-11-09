<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;

/**
 * One reason an optional mail may be held back from an account (its category turned off, a
 * pause, a muted subject...). Rules are tagged with TAG in the container; mandatory categories
 * never reach them.
 */
interface MailDeliveryRuleInterface
{
    public const string TAG = 'mail.delivery_rules';

    /**
     * Whether the account still gets this optional mail. A rule that throws lets it through.
     *
     * @param  MailSubjectDTO|null  $subject  The one thing the mail is about (HasMailSubject), or null.
     */
    public function allows(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool;
}
