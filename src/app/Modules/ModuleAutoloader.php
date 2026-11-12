<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Registers the class autoloader of a module namespace once per process.
 *
 * The SPL autoloader stack outlives the application: each test and each job of a
 * long-lived worker boots a new one, so an autoloader added on every boot piles up for
 * good, and one whose closure captures an object keeps that object (and the
 * application it points to) alive until the process ends.
 */
final class ModuleAutoloader
{
    /** @var array<string, true> */
    private static array $registered = [];

    public static function register(string $namespace, string $path): void
    {
        $prefix = rtrim($namespace, '\\').'\\';
        $path = rtrim($path, '/');
        $key = $prefix.'|'.$path;

        if (isset(self::$registered[$key])) {
            return;
        }

        self::$registered[$key] = true;
        $prefixLength = strlen($prefix);

        spl_autoload_register(static function (string $class) use ($prefix, $prefixLength, $path): void {
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $file = $path.'/'.str_replace('\\', '/', substr($class, $prefixLength)).'.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
