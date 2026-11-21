<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;

/**
 * The categories each person receives as browser push notifications (core#804), chosen in the
 * «Avisos» tab of the account settings. For every browser of the account alike; which browsers
 * receive them is WebPushSubscriptionServiceInterface.
 */
interface WebPushPreferenceServiceInterface
{
    /**
     * @return list<string> Keys of the categories the person turned on, sorted; empty when they cannot be read.
     */
    public function enabledFor(object $user): array;

    /**
     * Save the person's push switches (true pushes the notices). All or nothing: if one key is
     * unknown, mandatory, offers no site notices or is not shown to the person, nothing is saved.
     *
     * @param  array<string, bool>  $changes
     *
     * @throws MailPreferenceNotEditableException
     */
    public function update(object $user, array $changes): void;

    /** Forgets every choice of the account (anonymized). */
    public function forgetUser(string $userId): void;
}
