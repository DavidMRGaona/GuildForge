<?php

declare(strict_types=1);

namespace App\Application\Proposals\Contracts;

use App\Domain\Proposals\Enums\ProposalType;

/**
 * A step run inside the approval transaction of a proposal, before it is marked approved and
 * published (the extension point where module requests are applied, core#581). Throwing rolls the
 * whole approval back: the proposal stays in review and the exception reaches the caller.
 *
 * Registered with the container tag TAG.
 */
interface ProposalApprovalStepInterface
{
    public const string TAG = 'proposals.approval-steps';

    /**
     * @param  ?string  $reviewerId  null when submitting published it directly
     */
    public function apply(ProposalType $type, string $id, ?string $reviewerId): void;
}
