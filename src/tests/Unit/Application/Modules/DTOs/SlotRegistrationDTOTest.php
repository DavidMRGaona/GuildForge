<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\SlotRegistrationDTO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SlotRegistrationDTOTest extends TestCase
{
    #[Test]
    public function it_creates_slot_registration_dto_from_constructor_with_all_properties(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'before-header',
            component: 'components/AnnouncementBanner.vue',
            module: 'announcements',
            order: 10,
            props: ['variant' => 'primary'],
            dataKeys: ['announcements'],
        );

        $this->assertSame('before-header', $dto->slot);
        $this->assertSame('components/AnnouncementBanner.vue', $dto->component);
        $this->assertSame('announcements', $dto->module);
        $this->assertSame(10, $dto->order);
        $this->assertSame(['variant' => 'primary'], $dto->props);
        $this->assertSame(['announcements'], $dto->dataKeys);
    }

    #[Test]
    public function it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'slot' => 'after-header',
            'component' => 'components/Banner.vue',
            'module' => 'marketing',
            'order' => 5,
            'props' => ['color' => 'blue'],
            'dataKeys' => ['bannerData', 'userPrefs'],
        ];

        $dto = SlotRegistrationDTO::fromArray($data);

        $this->assertSame('after-header', $dto->slot);
        $this->assertSame('components/Banner.vue', $dto->component);
        $this->assertSame('marketing', $dto->module);
        $this->assertSame(5, $dto->order);
        $this->assertSame(['color' => 'blue'], $dto->props);
        $this->assertSame(['bannerData', 'userPrefs'], $dto->dataKeys);
    }

    #[Test]
    public function it_creates_from_array_with_minimal_fields(): void
    {
        $data = [
            'slot' => 'before-footer',
            'component' => 'components/Widget.vue',
            'module' => 'widgets',
        ];

        $dto = SlotRegistrationDTO::fromArray($data);

        $this->assertSame('before-footer', $dto->slot);
        $this->assertSame('components/Widget.vue', $dto->component);
        $this->assertSame('widgets', $dto->module);
        $this->assertSame(0, $dto->order);
        $this->assertSame([], $dto->props);
        $this->assertSame([], $dto->dataKeys);
    }

    #[Test]
    public function it_uses_default_values_for_optional_fields(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'before-content',
            component: 'components/Ad.vue',
            module: 'ads',
        );

        $this->assertSame(0, $dto->order);
        $this->assertSame([], $dto->props);
        $this->assertSame([], $dto->dataKeys);
    }

    #[Test]
    public function to_array_returns_correct_representation(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'before-header',
            component: 'components/Alert.vue',
            module: 'alerts',
            order: 1,
            props: ['type' => 'warning'],
            dataKeys: ['activeAlerts'],
        );

        $expected = [
            'slot' => 'before-header',
            'component' => 'components/Alert.vue',
            'module' => 'alerts',
            'order' => 1,
            'props' => ['type' => 'warning'],
            'dataKeys' => ['activeAlerts'],
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function to_array_includes_empty_arrays_for_optional_fields(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'after-footer',
            component: 'components/Tracker.vue',
            module: 'analytics',
        );

        $result = $dto->toArray();

        $this->assertSame([], $result['props']);
        $this->assertSame([], $result['dataKeys']);
    }

    #[Test]
    public function it_handles_complex_props(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'before-header',
            component: 'components/ComplexWidget.vue',
            module: 'complex',
            props: [
                'nested' => ['key' => 'value'],
                'array' => [1, 2, 3],
                'bool' => true,
            ],
        );

        $this->assertSame(['key' => 'value'], $dto->props['nested']);
        $this->assertSame([1, 2, 3], $dto->props['array']);
        $this->assertTrue($dto->props['bool']);
    }

    #[Test]
    public function it_handles_multiple_data_keys(): void
    {
        $dto = new SlotRegistrationDTO(
            slot: 'before-content',
            component: 'components/DataWidget.vue',
            module: 'data',
            dataKeys: ['users', 'posts', 'comments', 'settings'],
        );

        $this->assertCount(4, $dto->dataKeys);
        $this->assertContains('users', $dto->dataKeys);
        $this->assertContains('settings', $dto->dataKeys);
    }
}
