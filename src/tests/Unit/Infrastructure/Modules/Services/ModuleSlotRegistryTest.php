<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Infrastructure\Modules\Services\ModuleSlotRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleSlotRegistryTest extends TestCase
{
    private ModuleSlotRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModuleSlotRegistry();
    }

    #[Test]
    public function it_can_register_a_slot(): void
    {
        $slot = new SlotRegistrationDTO(
            slot: 'before-header',
            component: 'components/Banner.vue',
            module: 'announcements',
        );

        $this->registry->register($slot);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($slot, $this->registry->all()[0]);
    }

    #[Test]
    public function it_can_register_many_slots(): void
    {
        $slots = [
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner.vue',
                module: 'announcements',
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
        ];

        $this->registry->registerMany($slots);

        $this->assertCount(2, $this->registry->all());
    }

    #[Test]
    public function it_returns_slots_for_specific_slot_position(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner1.vue',
                module: 'announcements',
                order: 1,
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner2.vue',
                module: 'marketing',
                order: 0,
            ),
        ]);

        $beforeHeaderSlots = $this->registry->forSlot('before-header');

        $this->assertCount(2, $beforeHeaderSlots);
        // Should be sorted by order
        $this->assertSame('marketing', $beforeHeaderSlots[0]->module);
        $this->assertSame('announcements', $beforeHeaderSlots[1]->module);
    }

    #[Test]
    public function it_returns_slots_for_specific_module(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner.vue',
                module: 'announcements',
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Footer.vue',
                module: 'announcements',
            ),
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
        ]);

        $announcementSlots = $this->registry->forModule('announcements');

        $this->assertCount(2, $announcementSlots);
        foreach ($announcementSlots as $slot) {
            $this->assertSame('announcements', $slot->module);
        }
    }

    #[Test]
    public function it_groups_slots_by_slot_position(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner1.vue',
                module: 'announcements',
                order: 1,
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner2.vue',
                module: 'marketing',
                order: 0,
            ),
        ]);

        $grouped = $this->registry->grouped();

        $this->assertArrayHasKey('before-header', $grouped);
        $this->assertArrayHasKey('after-footer', $grouped);
        $this->assertCount(2, $grouped['before-header']);
        $this->assertCount(1, $grouped['after-footer']);
        // Should be sorted within groups
        $this->assertSame('marketing', $grouped['before-header'][0]->module);
    }

    #[Test]
    public function it_converts_to_inertia_payload(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner.vue',
                module: 'announcements',
                order: 0,
                props: ['variant' => 'info'],
                dataKeys: ['announcements'],
            ),
        ]);

        $payload = $this->registry->toInertiaPayload();

        $this->assertArrayHasKey('before-header', $payload);
        $this->assertCount(1, $payload['before-header']);
        $this->assertSame('before-header', $payload['before-header'][0]['slot']);
        $this->assertSame('components/Banner.vue', $payload['before-header'][0]['component']);
        $this->assertSame('announcements', $payload['before-header'][0]['module']);
        $this->assertSame(['variant' => 'info'], $payload['before-header'][0]['props']);
        $this->assertSame(['announcements'], $payload['before-header'][0]['dataKeys']);
    }

    #[Test]
    public function it_can_unregister_module_slots(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner.vue',
                module: 'announcements',
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
        ]);

        $this->registry->unregisterModule('announcements');

        $this->assertCount(1, $this->registry->all());
        $this->assertSame('analytics', $this->registry->all()[0]->module);
    }

    #[Test]
    public function it_can_clear_all_slots(): void
    {
        $this->registry->registerMany([
            new SlotRegistrationDTO(
                slot: 'before-header',
                component: 'components/Banner.vue',
                module: 'announcements',
            ),
            new SlotRegistrationDTO(
                slot: 'after-footer',
                component: 'components/Tracker.vue',
                module: 'analytics',
            ),
        ]);

        $this->registry->clear();

        $this->assertCount(0, $this->registry->all());
    }

    #[Test]
    public function for_slot_returns_empty_array_when_no_matches(): void
    {
        $slots = $this->registry->forSlot('nonexistent-slot');

        $this->assertSame([], $slots);
    }

    #[Test]
    public function for_module_returns_empty_array_when_no_matches(): void
    {
        $slots = $this->registry->forModule('nonexistent-module');

        $this->assertSame([], $slots);
    }

    #[Test]
    public function to_inertia_payload_returns_empty_array_when_no_slots(): void
    {
        $payload = $this->registry->toInertiaPayload();

        $this->assertSame([], $payload);
    }
}
