<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Application\Modules\Services\ModuleSlotRegistryInterface;

final class ModuleSlotRegistry implements ModuleSlotRegistryInterface
{
    /** @var array<SlotRegistrationDTO> */
    private array $slots = [];

    public function register(SlotRegistrationDTO $slot): void
    {
        $this->slots[] = $slot;
    }

    public function registerMany(array $slots): void
    {
        foreach ($slots as $slot) {
            $this->register($slot);
        }
    }

    public function all(): array
    {
        return $this->slots;
    }

    public function forSlot(string $slotName): array
    {
        $filtered = array_values(
            array_filter(
                $this->slots,
                fn (SlotRegistrationDTO $slot): bool => $slot->slot === $slotName
            )
        );

        usort($filtered, fn (SlotRegistrationDTO $a, SlotRegistrationDTO $b): int => $a->order <=> $b->order);

        return $filtered;
    }

    public function forModule(string $moduleName): array
    {
        return array_values(
            array_filter(
                $this->slots,
                fn (SlotRegistrationDTO $slot): bool => $slot->module === $moduleName
            )
        );
    }

    public function grouped(): array
    {
        $groups = [];

        foreach ($this->slots as $slot) {
            $slotName = $slot->slot;
            if (! isset($groups[$slotName])) {
                $groups[$slotName] = [];
            }
            $groups[$slotName][] = $slot;
        }

        // Sort slots within each group by order
        foreach ($groups as $slotName => $slots) {
            usort($slots, fn (SlotRegistrationDTO $a, SlotRegistrationDTO $b): int => $a->order <=> $b->order);
            $groups[$slotName] = $slots;
        }

        return $groups;
    }

    public function toInertiaPayload(): array
    {
        $grouped = $this->grouped();
        $payload = [];

        foreach ($grouped as $slotName => $slots) {
            $payload[$slotName] = array_map(
                fn (SlotRegistrationDTO $slot): array => $slot->toArray(),
                $slots
            );
        }

        return $payload;
    }

    public function unregisterModule(string $moduleName): void
    {
        $this->slots = array_values(
            array_filter(
                $this->slots,
                fn (SlotRegistrationDTO $slot): bool => $slot->module !== $moduleName
            )
        );
    }

    public function clear(): void
    {
        $this->slots = [];
    }
}
