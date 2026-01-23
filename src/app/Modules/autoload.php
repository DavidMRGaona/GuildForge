<?php

declare(strict_types=1);

/**
 * Module Autoloader
 *
 * This file registers SPL autoloaders for all modules in the modules directory.
 * It's loaded early via composer's "files" autoload to ensure module classes
 * are available before service providers boot.
 *
 * This autoloader works independently of whether modules are enabled or not -
 * it simply makes the classes available for autoloading.
 */

(static function (): void {
    $modulesPath = dirname(__DIR__, 2) . '/modules';

    if (!is_dir($modulesPath)) {
        return;
    }

    // Scan for module directories
    $modules = glob($modulesPath . '/*', GLOB_ONLYDIR);

    if ($modules === false) {
        return;
    }

    foreach ($modules as $modulePath) {
        $moduleName = basename($modulePath);
        $srcPath = $modulePath . '/src';

        if (!is_dir($srcPath)) {
            continue;
        }

        // Convert module name to namespace (kebab-case to PascalCase)
        // e.g., "announcements" -> "Announcements", "test-sdk" -> "TestSdk"
        $studlyName = str_replace(' ', '', ucwords(str_replace('-', ' ', $moduleName)));
        $namespace = 'Modules\\' . $studlyName . '\\';

        // Register autoloader for this module
        spl_autoload_register(static function (string $class) use ($namespace, $srcPath): void {
            if (!str_starts_with($class, $namespace)) {
                return;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $srcPath . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
})();
