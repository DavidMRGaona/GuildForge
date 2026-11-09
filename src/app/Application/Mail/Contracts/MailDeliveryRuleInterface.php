<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

use App\Application\Mail\DTOs\MailCategoryDTO;

/**
 * One reason an optional mail may be held back from an account (its category turned off, a
 * pause...). Rules are tagged with TAG in the container; mandatory categories never reach them.
 */
interface MailDeliveryRuleInterface
{
    public const string TAG = 'mail.delivery_rules';

    /**
     * Whether the account still gets this optional mail. A rule that throws lets it through.
     */
    public function allows(object $user, MailCategoryDTO $category): bool;
}
