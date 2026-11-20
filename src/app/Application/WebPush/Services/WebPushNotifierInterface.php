<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;

/**
 * Sends a notice the bell just stored to the account's browsers (core#803), when push is on,
 * the person wants that category pushed and nobody is impersonating. Queued after the commit;
 * never throws, so a push problem never costs the notice.
 */
interface WebPushNotifierInterface
{
    public function notify(string $userId, string $noticeId, MailCategoryDTO $category, ?MailSubjectDTO $subject): void;
}
