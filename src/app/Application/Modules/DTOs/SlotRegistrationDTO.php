<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class SlotRegistrationDTO
{
    /**
     * @param  array<string, mixed>  $props  Static props to pass to the component
     * @param  array<string>  $dataKeys  Inertia page props to inject as component props
     * @param  array<string, mixed>|null  $profileTab  Tab metadata for profile-sections slot
     */
    public function __construct(
        public string $slot,
        public string $component,
        public string $module,
        public int $order = 0,
        public array $props = [],
        public array $dataKeys = [],
        public ?array $profileTab = null,
    ) {
    }

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
            profileTab: $data['profileTab'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'slot' => $this->slot,
            'component' => $this->component,
            'module' => $this->module,
            'order' => $this->order,
            'props' => $this->props,
            'dataKeys' => $this->dataKeys,
        ];

        if ($this->profileTab !== null) {
            $result['profileTab'] = $this->profileTab;
        }

        return $result;
    }
}
