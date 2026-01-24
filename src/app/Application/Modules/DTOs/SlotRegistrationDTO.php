<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class SlotRegistrationDTO
{
    /**
     * @param  array<string, mixed>  $props  Static props to pass to the component
     * @param  array<string>  $dataKeys  Inertia page props to inject as component props
     */
    public function __construct(
        public string $slot,
        public string $component,
        public string $module,
        public int $order = 0,
        public array $props = [],
        public array $dataKeys = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slot: $data['slot'] ?? '',
            component: $data['component'] ?? '',
            module: $data['module'] ?? '',
            order: $data['order'] ?? 0,
            props: $data['props'] ?? [],
            dataKeys: $data['dataKeys'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'component' => $this->component,
            'module' => $this->module,
            'order' => $this->order,
            'props' => $this->props,
            'dataKeys' => $this->dataKeys,
        ];
    }
}
