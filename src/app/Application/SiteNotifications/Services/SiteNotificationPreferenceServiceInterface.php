<?php

declare(strict_types=1);

namespace App\Application\SiteNotifications\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;

/**
 * Which notices each account sees in the notification centre, by mail category (core#491).
 * The state of each category comes with the mail ones in MailPreferenceServiceInterface::sectionsFor().
 */
interface SiteNotificationPreferenceServiceInterface
{
    /**
     * Whether a notice of this category reaches the person: the category offers site notices,
     * and it is mandatory or the person did not turn it off nor mute what it is about. A pause of
     * the mail does not count. When the choices cannot be read, it does.
     */
    public function receives(object $user, MailCategoryDTO $category, ?MailSubjectDTO $subject = null): bool;

    /**
     * Save the person's site switches (true shows the notices). All or nothing: if one key is
     * unknown, mandatory, not shown to the person or offers no site notices, nothing is saved.
     *
     * @param  array<string, bool>  $changes
     *
     * @throws MailPreferenceNotEditableException
     */
    public function update(object $user, array $changes): void;
}
