<?php

/*
 * This file is part of the Laravel Cloudinary package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | An HTTP or HTTPS URL to notify your application (a webhook) when the process of uploads, deletes, and any API
    | that accepts notification_url has completed.
    |
    |
    */
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Cloudinary settings. Cloudinary is a cloud hosted
    | media management service for all file uploads, storage, delivery and transformation needs.
    |
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

    /**
     * Upload Preset From Cloudinary Dashboard
     */
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),

    /**
     * Route to get cloud_image_url from Blade Upload Widget
     */
    'upload_route' => env('CLOUDINARY_UPLOAD_ROUTE'),

    /**
     * Controller action to get cloud_image_url from Blade Upload Widget
     */
    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),
];
