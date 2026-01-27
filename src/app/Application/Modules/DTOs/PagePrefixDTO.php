<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class PagePrefixDTO
{
    public function __construct(
        public string $prefix,
        public string $module,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            prefix: $data['prefix'] ?? '',
            module: $data['module'] ?? '',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'module' => $this->module,
        ];
    }
}
