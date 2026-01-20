<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Image Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for image optimization before uploading to Cloudinary.
    | Images are resized and compressed to reduce bandwidth and storage costs.
    |
    */

    'optimization' => [
        // Enable or disable image optimization globally
        'enabled' => env('IMAGE_OPTIMIZATION_ENABLED', true),

        // Maximum dimensions - images larger than these will be scaled down
        'max_width' => (int) env('IMAGE_OPTIMIZATION_MAX_WIDTH', 2048),
        'max_height' => (int) env('IMAGE_OPTIMIZATION_MAX_HEIGHT', 2048),

        // Quality for JPEG/WebP encoding (1-100)
        'quality' => (int) env('IMAGE_OPTIMIZATION_QUALITY', 85),

        // Output format (null = keep original, or 'jpg', 'png', 'webp', 'gif')
        'format' => env('IMAGE_OPTIMIZATION_FORMAT'),

        // Skip animated GIF files (preserves animation)
        'skip_animated_gif' => true,

        // Minimum file size in bytes before optimization is applied
        // Files smaller than this threshold are returned unchanged
        'min_size_bytes' => 50 * 1024, // 50KB
    ],
];
