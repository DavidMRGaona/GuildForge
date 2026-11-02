<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Domain\Search\Enums\SearchAudience;

/**
 * The audiences whose documents a person may find. Fails closed: when in doubt, only the public one.
 */
interface SearchAudienceResolverInterface
{
    /**
     * Guest or account that must verify its email: public; signed in: also authenticated; association member: also members.
     *
     * @return list<SearchAudience>
     */
    public function for(?object $user): array;
}
