<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Application\DTOs\ImageOptimizationSettingsDTO;
use App\Infrastructure\Services\ImageOptimizationService;
use Psr\Log\NullLogger;
use Tests\TestCase;

final class ImageOptimizationServiceTest extends TestCase
{
    private ImageOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageOptimizationService(new NullLogger());
    }

    public function test_is_optimizable_mime_type_returns_true_for_jpeg(): void
    {
        $this->assertTrue($this->service->isOptimizableMimeType('image/jpeg'));
        $this->assertTrue($this->service->isOptimizableMimeType('image/jpg'));
    }

    public function test_is_optimizable_mime_type_returns_true_for_png(): void
    {
        $this->assertTrue($this->service->isOptimizableMimeType('image/png'));
    }

    public function test_is_optimizable_mime_type_returns_true_for_webp(): void
    {
        $this->assertTrue($this->service->isOptimizableMimeType('image/webp'));
    }

    public function test_is_optimizable_mime_type_returns_true_for_gif(): void
    {
        $this->assertTrue($this->service->isOptimizableMimeType('image/gif'));
    }

    public function test_is_optimizable_mime_type_returns_false_for_svg(): void
    {
        $this->assertFalse($this->service->isOptimizableMimeType('image/svg+xml'));
    }

    public function test_is_optimizable_mime_type_returns_false_for_pdf(): void
    {
        $this->assertFalse($this->service->isOptimizableMimeType('application/pdf'));
    }

    public function test_is_optimizable_mime_type_returns_false_for_video(): void
    {
        $this->assertFalse($this->service->isOptimizableMimeType('video/mp4'));
        $this->assertFalse($this->service->isOptimizableMimeType('video/webm'));
    }

    public function test_is_optimizable_mime_type_returns_false_for_null(): void
    {
        $this->assertFalse($this->service->isOptimizableMimeType(null));
    }

    public function test_is_optimizable_mime_type_is_case_insensitive(): void
    {
        $this->assertTrue($this->service->isOptimizableMimeType('IMAGE/JPEG'));
        $this->assertTrue($this->service->isOptimizableMimeType('Image/Png'));
    }

    public function test_optimize_returns_original_for_non_image_mime_types(): void
    {
        $contents = 'This is a text file, not an image';

        $result = $this->service->optimize($contents, 'text/plain');

        $this->assertSame($contents, $result);
    }

    public function test_optimize_returns_original_for_small_files(): void
    {
        // Create a small "fake" JPEG content (< 50KB)
        $contents = str_repeat('x', 1024); // 1KB

        $options = new ImageOptimizationSettingsDTO(
            minSizeBytes: 51200, // 50KB threshold
        );

        $result = $this->service->optimize($contents, 'image/jpeg', $options);

        // Should return original because size < threshold
        $this->assertSame($contents, $result);
    }

    public function test_optimize_returns_original_when_mime_type_is_null(): void
    {
        $contents = 'random binary content';

        $result = $this->service->optimize($contents, null);

        $this->assertSame($contents, $result);
    }

    public function test_is_enabled_returns_true_by_default(): void
    {
        config(['images.optimization.enabled' => true]);

        $this->assertTrue($this->service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled_in_config(): void
    {
        config(['images.optimization.enabled' => false]);

        $this->assertFalse($this->service->isEnabled());
    }

    public function test_optimize_returns_original_when_disabled(): void
    {
        config(['images.optimization.enabled' => false]);

        $contents = 'any content';

        $result = $this->service->optimize($contents, 'image/jpeg');

        $this->assertSame($contents, $result);
    }
}
