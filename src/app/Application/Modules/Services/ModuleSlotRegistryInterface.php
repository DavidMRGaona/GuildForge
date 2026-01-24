<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\SlotRegistrationDTO;

interface ModuleSlotRegistryInterface
{
    /**
     * Register a slot component from a module.
     */
    public function register(SlotRegistrationDTO $slot): void;

    /**
     * Register multiple slot components from a module.
     *
     * @param  array<SlotRegistrationDTO>  $slots
     */
    public function registerMany(array $slots): void;

    /**
     * Get all registered slot components.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function all(): array;

    /**
     * Get slot components for a specific slot position.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function forSlot(string $slotName): array;

    /**
     * Get slot components for a specific module.
     *
     * @return array<SlotRegistrationDTO>
     */
    public function forModule(string $moduleName): array;

    /**
     * Get slot components grouped by slot position, sorted by order.
     *
     * @return array<string, array<SlotRegistrationDTO>>
     */
    public function grouped(): array;

    /**
     * Convert registry to Inertia payload format.
     *
     * @return array<string, array<array<string, mixed>>>
     */
    public function toInertiaPayload(): array;

    /**
     * Unregister all slot components for a module.
     */
    public function unregisterModule(string $moduleName): void;

    /**
     * Clear all registered slot components.
     */
    public function clear(): void;
}
