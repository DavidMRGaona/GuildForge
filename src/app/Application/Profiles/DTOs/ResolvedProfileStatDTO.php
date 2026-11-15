<?php

declare(strict_types=1);

namespace App\Application\Profiles\DTOs;

/**
 * A stat counted for the person viewing, ready for the browser.
 */
final readonly class ResolvedProfileStatDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
        public ?string $url,
    ) {}

    /**
     * @return array{key: string, label: string, value: int, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'url' => $this->url,
        ];
    }
}
