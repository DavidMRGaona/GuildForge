<?php

declare(strict_types=1);

namespace App\Application\Activity\Contracts;

use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;

/**
 * Optional for an ActivitySourceInterface (core#774): the attributes its facts carry and the values
 * each can take (every game system of its tables, say), so a rule builder can offer «played 3
 * different systems» or «played Pathfinder» without depending on the source's module. Value ids are
 * the ones its facts carry.
 */
interface OffersActivityAttributes
{
    /**
     * Every attribute key its facts may carry.
     *
     * @return list<ActivityAttributeDTO>
     */
    public function attributes(): array;

    /**
     * Sorted as they should be shown; empty for an attribute the source does not list.
     *
     * @return list<ActivityAttributeValueDTO>
     */
    public function attributeValues(string $key): array;
}
