<?php

declare(strict_types=1);

namespace App\Application\Registration\DTOs;

use App\Domain\Registration\Enums\RegistrationFieldType;

/**
 * A registration field as read from its module once, when it is available (core#699).
 */
final readonly class RegistrationFieldDTO
{
    /**
     * @param  array<int, mixed>  $rules
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $description,
        public RegistrationFieldType $type,
        public array $rules,
    ) {}

    /**
     * What the registration page needs to draw the field: never the rules.
     *
     * @return array{key: string, label: string, description: ?string, type: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'type' => $this->type->value,
        ];
    }
}
