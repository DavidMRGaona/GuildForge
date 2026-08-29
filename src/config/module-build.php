<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Vite environment variables
    |--------------------------------------------------------------------------
    |
    | Module assets are compiled by running `npm run build` inside the module
    | directory, so Vite looks for a `.env` file there and never finds one.
    | Any `import.meta.env.VITE_*` reference would be inlined as an empty
    | string, silently producing broken URLs at runtime.
    |
    | These values are forwarded to the module build process instead. They are
    | resolved here (and not through `env()` at call time) so they survive
    | `config:cache`, which skips loading the `.env` file altogether.
    |
    */
    'vite_env' => [
        'VITE_CLOUDINARY_CLOUD_NAME' => env('VITE_CLOUDINARY_CLOUD_NAME', env('CLOUDINARY_CLOUD_NAME')),
        'VITE_CLOUDINARY_PREFIX' => env('VITE_CLOUDINARY_PREFIX', env('CLOUDINARY_PREFIX')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Required Vite environment variables
    |--------------------------------------------------------------------------
    |
    | Building a module without these would generate assets that look valid but
    | request broken URLs. The build fails fast instead.
    |
    | Empty on purpose: Cloudinary settings are no longer inlined at build time.
    | They are resolved at runtime from Inertia props (see config/cloudinary.php
    | and resources/js/utils/cloudinary.ts), because module assets are compiled
    | by CI into a distributable package shared across installations. Add a
    | variable here only if a bundle genuinely cannot work without it.
    |
    */
    'required_vite_env' => [],

];
