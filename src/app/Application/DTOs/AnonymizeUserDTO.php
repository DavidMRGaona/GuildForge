<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class AnonymizeUserDTO
{
    public function __construct(
        public string $userId,
        public string $contentAction,
        public ?string $transferToUserId = null,
    ) {
    }

    /**
     * @param  array{user_id: string, content_action: string, transfer_to_user_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            contentAction: $data['content_action'],
            transferToUserId: $data['transfer_to_user_id'] ?? null,
        );
    }
}
