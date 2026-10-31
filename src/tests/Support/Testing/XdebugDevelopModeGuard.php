<?php

declare(strict_types=1);

namespace Tests\Support\Testing;

/**
 * Keeps the suites (core and modules) from running under Xdebug's develop mode.
 *
 * In that mode Xdebug keeps the local variables of the last exceptions' stacks. Filament holds a
 * reference to the action being mounted, so an exception thrown meanwhile keeps that reference
 * shared, and Livewire's dehydration then writes the snapshot form of the action into the
 * component the test inspects: callAction() finds no action name and returns without running it,
 * depending on the tests that ran before. CI never loads Xdebug.
 */
final class XdebugDevelopModeGuard
{
    /**
     * @param  list<string>  $modes
     */
    public static function problem(array $modes): ?string
    {
        if (! in_array('develop', $modes, true)) {
            return null;
        }

        return 'Xdebug is in develop mode, which can make Filament action tests pass without running the action. '
            .'Run the tests with XDEBUG_MODE=off (or XDEBUG_MODE=coverage for coverage), as make test does.';
    }

    /**
     * @return list<string>
     */
    public static function activeModes(): array
    {
        if (! function_exists('xdebug_info')) {
            return [];
        }

        $modes = xdebug_info('mode');

        return is_array($modes) ? array_values(array_filter($modes, is_string(...))) : [];
    }
}
