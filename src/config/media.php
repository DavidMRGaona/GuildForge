<?php

declare(strict_types=1);

return [
    // Whether the panel media library may delete unused files from Cloudinary. Off by default, so a local
    // copy or a staging site sharing the account and prefix of a real site can never remove its files
    'delete_enabled' => (bool) env('MEDIA_LIBRARY_DELETE_ENABLED', false),
];
