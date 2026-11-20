<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;

/**
 * Whether a notice the bell just got should also go out as a push message (core#803). Asked only
 * for notices of categories with site notices that the person receives, a muted subject already
 * excluded. Off by default for every category: nothing is pushed until the person chooses it.
 */
interface WebPushPreferenceInterface
{
    public function wantsPush(string $userId, MailCategoryDTO $category, ?MailSubjectDTO $subject): bool;
}
