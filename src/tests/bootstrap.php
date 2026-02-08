<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Clear cached config to prevent stale Docker paths (/var/www/html)
$configCache = __DIR__ . '/../bootstrap/cache/config.php';
if (is_file($configCache)) {
    @unlink($configCache);
}

// Clear compiled views before running tests to prevent stale cache issues
$viewsPath = __DIR__ . '/../storage/framework/views';
if (is_dir($viewsPath)) {
    $files = glob($viewsPath . '/*.php');
    if ($files !== false) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}
