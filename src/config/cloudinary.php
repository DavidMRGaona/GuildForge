<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary account
    |--------------------------------------------------------------------------
    |
    | Fallback credentials for the "images" disk (filesystems.disks.images.url
    | takes precedence), as cloudinary://key:secret@cloud-name.
    |
    */
    'cloud_url' => env('CLOUDINARY_URL', 'cloudinary://'.env('CLOUDINARY_KEY').':'.env('CLOUDINARY_SECRET').'@'.env('CLOUDINARY_CLOUD_NAME')),

    /*
    |--------------------------------------------------------------------------
    | Frontend delivery settings
    |--------------------------------------------------------------------------
    |
    | Shared with the frontend through Inertia so image URLs are built at
    | runtime instead of being inlined at build time. Module assets are
    | compiled by CI into a distributable ZIP that knows nothing about this
    | installation, so these values must never be baked into a bundle.
    |
    | The cloud name falls back to the host of CLOUDINARY_URL
    | (cloudinary://key:secret@cloud-name) to avoid requiring a new variable.
    |
    */
    'cloud_name' => env('VITE_CLOUDINARY_CLOUD_NAME')
        ?: env('CLOUDINARY_CLOUD_NAME')
        ?: parse_url((string) env('CLOUDINARY_URL'), PHP_URL_HOST),

    'prefix' => env('VITE_CLOUDINARY_PREFIX') ?: env('CLOUDINARY_PREFIX'),
];
