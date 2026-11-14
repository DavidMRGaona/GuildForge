<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\DTOs\Response\TagResponseDTO;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * Which tags a web proposal may carry (core#654), the same split the panel uses: a category (a root
 * tag) and additional tags (subtags). Whoever proposes picks only additional tags; the category is
 * picked by whoever reviews, so only someone who may publish their proposals directly (and so
 * reviews their own) is offered the categories. The form, the request and the service all ask here.
 */
interface ProposalTagOptionsInterface
{
    /**
     * Subtags that apply to the type, in the panel's order.
     *
     * @return list<TagResponseDTO>
     */
    public function additionalTags(ProposalType $type): array;

    /**
     * Root tags of the type when the person may publish this type directly; empty otherwise.
     *
     * @return list<TagResponseDTO>
     */
    public function categoriesFor(ProposalType $type, string $userId): array;

    /**
     * Whether the type has any category at all (the panel then requires one to save).
     */
    public function hasCategories(ProposalType $type): bool;
}
