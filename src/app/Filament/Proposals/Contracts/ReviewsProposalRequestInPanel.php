<?php

declare(strict_types=1);

namespace App\Filament\Proposals\Contracts;

use Filament\Schemas\Components\Component;

/**
 * Optional, for an EventProposalRequestInterface that wants its own review form in the panel
 * (core#581). By default the core builds one from the request's fields, which is what most
 * requests need. The components must be named after the fields (their state is the request's
 * values) and the core still checks what they hold against fields() before saving.
 */
interface ReviewsProposalRequestInPanel
{
    /**
     * @return list<Component>
     */
    public function reviewComponents(): array;
}
