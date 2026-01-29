<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\ContactMessageDTO;

interface ContactServiceInterface
{
    /**
     * Send a contact form message to the configured recipient.
     *
     * @return bool True if the email was sent, false if contact email is not configured
     */
    public function sendContactMessage(ContactMessageDTO $dto): bool;

    /**
     * Check if the contact email is configured in settings.
     */
    public function isContactEmailConfigured(): bool;
}
