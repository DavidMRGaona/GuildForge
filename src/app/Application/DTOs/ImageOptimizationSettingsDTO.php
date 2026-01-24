<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Immutable DTO containing settings for image optimization.
 */
final readonly class ImageOptimizationSettingsDTO
{
    public function __construct(
        public int $maxWidth = 2048,
        public int $maxHeight = 2048,
        public int $quality = 85,
        public ?string $format = null,
        public bool $skipAnimatedGif = true,
        public int $minSizeBytes = 51200, // 50KB
    ) {}

    /**
     * Create an instance with default values.
     */
    public static function defaults(): self
    {
        return new self;
    }

    /**
     * Create an instance from application configuration.
     */
    public static function fromConfig(): self
    {
        /** @var array{enabled?: bool, max_width?: int, max_height?: int, quality?: int, format?: string|null, skip_animated_gif?: bool, min_size_bytes?: int} $config */
        $config = config('images.optimization', []);

        return new self(
            maxWidth: (int) ($config['max_width'] ?? 2048),
            maxHeight: (int) ($config['max_height'] ?? 2048),
            quality: (int) ($config['quality'] ?? 85),
            format: isset($config['format']) && $config['format'] !== '' ? (string) $config['format'] : null,
            skipAnimatedGif: (bool) ($config['skip_animated_gif'] ?? true),
            minSizeBytes: (int) ($config['min_size_bytes'] ?? 51200),
        );
    }

    /**
     * Create an instance with custom values, falling back to config defaults.
     *
     * @param  array{maxWidth?: int, maxHeight?: int, quality?: int, format?: string|null, skipAnimatedGif?: bool, minSizeBytes?: int}  $overrides
     */
    public static function withOverrides(array $overrides): self
    {
        $defaults = self::fromConfig();

        return new self(
            maxWidth: $overrides['maxWidth'] ?? $defaults->maxWidth,
            maxHeight: $overrides['maxHeight'] ?? $defaults->maxHeight,
            quality: $overrides['quality'] ?? $defaults->quality,
            format: array_key_exists('format', $overrides) ? $overrides['format'] : $defaults->format,
            skipAnimatedGif: $overrides['skipAnimatedGif'] ?? $defaults->skipAnimatedGif,
            minSizeBytes: $overrides['minSizeBytes'] ?? $defaults->minSizeBytes,
        );
    }
}
