<?php

declare(strict_types=1);

namespace App\Filament\Impersonation;

use Filament\Actions\Action;

/**
 * Filament actions that only read (switch the layout, move between files), so they still run
 * while an administrator impersonates in «Solo mirar» (core#576). Any other action opens its
 * modal as usual, but running it is refused. Actions that only open a modal with nothing to
 * submit («Ver detalles») need no mark.
 *
 * The mark travels with the action's extra attributes, which Filament keeps when it clones an
 * action for a row; an action whose attributes are replaced afterwards loses it and is refused.
 */
final class ReadOnlyActions
{
    public const string ATTRIBUTE = 'data-impersonation-reads-only';

    public static function allow(Action $action): Action
    {
        return $action->extraAttributes([self::ATTRIBUTE => 'true'], merge: true);
    }

    public static function allows(Action $action): bool
    {
        return ($action->getExtraAttributes()[self::ATTRIBUTE] ?? null) === 'true';
    }
}
