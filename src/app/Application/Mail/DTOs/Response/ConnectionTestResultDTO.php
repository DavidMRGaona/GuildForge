<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

final readonly class ConnectionTestResultDTO
{
    public function __construct(
        public bool $success,
        public ?int $responseTimeMs = null,
        public ?string $errorMessage = null,
        public ?string $serverResponse = null,
    ) {}
}
