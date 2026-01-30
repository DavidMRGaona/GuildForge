<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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
