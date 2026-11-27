<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        /** The public name (core#855): asked for when registering, optional for other callers */
        public ?string $displayName = null,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, display_name?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            displayName: $data['display_name'] ?? null,
        );
    }
}
