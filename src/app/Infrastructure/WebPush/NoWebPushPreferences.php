<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\WebPush\Services\WebPushPreferenceInterface;

/**
 * Push is off for every category until people can choose it per category (core#804): nothing
 * is pushed by default.
 */
final readonly class NoWebPushPreferences implements WebPushPreferenceInterface
{
    public function wantsPush(string $userId, MailCategoryDTO $category, ?MailSubjectDTO $subject): bool
    {
        return false;
    }
}
