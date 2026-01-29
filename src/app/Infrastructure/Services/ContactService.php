<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\ContactMessageDTO;
use App\Application\Services\ContactServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;

final readonly class ContactService implements ContactServiceInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {
    }

    public function sendContactMessage(ContactMessageDTO $dto): bool
    {
        $contactEmail = $this->getContactEmail();

        if ($contactEmail === '') {
            return false;
        }

        Mail::to($contactEmail)->send(new ContactFormMail(
            senderName: $dto->senderName,
            senderEmail: $dto->senderEmail,
            messageBody: $dto->messageBody,
        ));

        return true;
    }

    public function isContactEmailConfigured(): bool
    {
        return $this->getContactEmail() !== '';
    }

    private function getContactEmail(): string
    {
        return (string) $this->settings->get('contact_email', '');
    }
}
