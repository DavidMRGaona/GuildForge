<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap for the modules (phpstan-modules.neon): makes the classes of their tests and
 * database directories loadable, which app/Modules/autoload.php leaves out because it only maps
 * src/ and runs on every request. Only PHPStan loads this file.
 */
(static function (): void {
    $modules = glob(__DIR__.'/modules/*', GLOB_ONLYDIR);

    if ($modules === false) {
        return;
    }

    foreach ($modules as $modulePath) {
        // The same namespace as app/Modules/autoload.php: "game-tables" -> "Modules\GameTables\"
        $studlyName = str_replace(' ', '', ucwords(str_replace('-', ' ', basename($modulePath))));
        $prefixes = [
            'Modules\\'.$studlyName.'\\Tests\\' => $modulePath.'/tests/',
            'Modules\\'.$studlyName.'\\Database\\Seeders\\' => $modulePath.'/database/seeders/',
            'Modules\\'.$studlyName.'\\Database\\Factories\\' => $modulePath.'/database/factories/',
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $directory) {
                if (! str_starts_with($class, $prefix)) {
                    continue;
                }

                $file = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        });
    }
})();
