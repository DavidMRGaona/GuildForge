<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class ModuleRouteDTO
{
    public function __construct(
        public string $routeName,
        public string $label,
        public string $module,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            routeName: $data['routeName'] ?? '',
            label: $data['label'] ?? '',
            module: $data['module'] ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'routeName' => $this->routeName,
            'label' => $this->label,
            'module' => $this->module,
        ];
    }
}
