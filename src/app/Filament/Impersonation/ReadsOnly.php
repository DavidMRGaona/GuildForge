<?php

declare(strict_types=1);

namespace App\Filament\Impersonation;

use Attribute;

/**
 * Marks a public Livewire method that only changes what the page shows (open a guide, switch
 * a filter), so it still runs while an administrator impersonates in «Solo mirar» (core#576).
 * Every other public method of a panel page is refused then: core and module pages only mark
 * what they are sure writes nothing.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ReadsOnly {}
