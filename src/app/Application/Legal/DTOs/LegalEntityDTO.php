<?php

declare(strict_types=1);

namespace App\Application\Legal\DTOs;

final readonly class LegalEntityDTO
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $dpoEmail,
    ) {}
}
