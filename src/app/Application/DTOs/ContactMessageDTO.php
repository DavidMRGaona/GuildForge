<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class ContactMessageDTO
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $messageBody,
    ) {
    }

    /**
     * @param  array{name: string, email: string, message: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            senderName: $data['name'],
            senderEmail: $data['email'],
            messageBody: $data['message'],
        );
    }
}
