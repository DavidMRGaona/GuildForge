<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\ImageOptimizationSettingsDTO;

/**
 * Service for optimizing images before upload to external storage.
 *
 * Handles resizing, compression, and format conversion while gracefully
 * degrading to the original image on failure.
 */
interface ImageOptimizationServiceInterface
{
    /**
     * Optimize an image.
     *
     * Returns the optimized image contents, or the original contents
     * if optimization is disabled, not applicable, or fails.
     *
     * @param string $contents The raw image file contents
     * @param string|null $mimeType The MIME type (auto-detected if null)
     * @param ImageOptimizationSettingsDTO|null $settings Custom settings (uses config defaults if null)
     * @return string The optimized (or original) image contents
     */
    public function optimize(string $contents, ?string $mimeType = null, ?ImageOptimizationSettingsDTO $settings = null): string;

    /**
     * Check if optimization is enabled globally.
     */
    public function isEnabled(): bool;

    /**
     * Check if a MIME type is eligible for optimization.
     *
     * @param string|null $mimeType The MIME type to check
     * @return bool True if the MIME type can be optimized
     */
    public function isOptimizableMimeType(?string $mimeType): bool;
}
