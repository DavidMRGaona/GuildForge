<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\ImageOptimizationSettingsDTO;
use Tests\TestCase;

final class ImageOptimizationSettingsDTOTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $dto = new ImageOptimizationSettingsDTO(
            maxWidth: 1920,
            maxHeight: 1080,
            quality: 90,
            format: 'webp',
            skipAnimatedGif: false,
            minSizeBytes: 10240,
        );

        $this->assertSame(1920, $dto->maxWidth);
        $this->assertSame(1080, $dto->maxHeight);
        $this->assertSame(90, $dto->quality);
        $this->assertSame('webp', $dto->format);
        $this->assertFalse($dto->skipAnimatedGif);
        $this->assertSame(10240, $dto->minSizeBytes);
    }

    public function test_defaults_returns_dto_with_default_values(): void
    {
        $dto = ImageOptimizationSettingsDTO::defaults();

        $this->assertSame(2048, $dto->maxWidth);
        $this->assertSame(2048, $dto->maxHeight);
        $this->assertSame(85, $dto->quality);
        $this->assertNull($dto->format);
        $this->assertTrue($dto->skipAnimatedGif);
        $this->assertSame(51200, $dto->minSizeBytes);
    }

    public function test_from_config_reads_from_configuration(): void
    {
        config([
            'images.optimization.max_width' => 3000,
            'images.optimization.max_height' => 2000,
            'images.optimization.quality' => 75,
            'images.optimization.format' => 'jpg',
            'images.optimization.skip_animated_gif' => false,
            'images.optimization.min_size_bytes' => 100000,
        ]);

        $dto = ImageOptimizationSettingsDTO::fromConfig();

        $this->assertSame(3000, $dto->maxWidth);
        $this->assertSame(2000, $dto->maxHeight);
        $this->assertSame(75, $dto->quality);
        $this->assertSame('jpg', $dto->format);
        $this->assertFalse($dto->skipAnimatedGif);
        $this->assertSame(100000, $dto->minSizeBytes);
    }

    public function test_from_config_uses_defaults_when_config_missing(): void
    {
        config(['images.optimization' => []]);

        $dto = ImageOptimizationSettingsDTO::fromConfig();

        $this->assertSame(2048, $dto->maxWidth);
        $this->assertSame(2048, $dto->maxHeight);
        $this->assertSame(85, $dto->quality);
        $this->assertNull($dto->format);
        $this->assertTrue($dto->skipAnimatedGif);
        $this->assertSame(51200, $dto->minSizeBytes);
    }

    public function test_from_config_handles_empty_string_format_as_null(): void
    {
        config([
            'images.optimization.format' => '',
        ]);

        $dto = ImageOptimizationSettingsDTO::fromConfig();

        $this->assertNull($dto->format);
    }

    public function test_with_overrides_applies_partial_overrides(): void
    {
        config([
            'images.optimization.max_width' => 2048,
            'images.optimization.max_height' => 2048,
            'images.optimization.quality' => 85,
            'images.optimization.format' => null,
            'images.optimization.skip_animated_gif' => true,
            'images.optimization.min_size_bytes' => 51200,
        ]);

        $dto = ImageOptimizationSettingsDTO::withOverrides([
            'maxWidth' => 1024,
            'quality' => 70,
        ]);

        $this->assertSame(1024, $dto->maxWidth);
        $this->assertSame(2048, $dto->maxHeight);
        $this->assertSame(70, $dto->quality);
        $this->assertNull($dto->format);
        $this->assertTrue($dto->skipAnimatedGif);
        $this->assertSame(51200, $dto->minSizeBytes);
    }

    public function test_with_overrides_can_override_format_to_null(): void
    {
        config([
            'images.optimization.format' => 'webp',
        ]);

        $dto = ImageOptimizationSettingsDTO::withOverrides([
            'format' => null,
        ]);

        $this->assertNull($dto->format);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = ImageOptimizationSettingsDTO::defaults();

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
