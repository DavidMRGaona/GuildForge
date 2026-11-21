<?php

declare(strict_types=1);

namespace App\Application\Activity\DTOs;

use App\Domain\Activity\ActivityIdentifierRule;
use InvalidArgumentException;

/**
 * A value of a fact's attribute (core#774): the stable id of what it names (a game system's id,
 * never its name) and its label, already translated. Consumers compare ids and only show labels.
 */
final readonly class ActivityAttributeValueDTO
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
        if (! ActivityIdentifierRule::isStableId($id)) {
            throw new InvalidArgumentException('An activity attribute value needs a stable id (letters, digits, ".", "_", ":", "-").');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException("Activity attribute value '{$id}' needs a label.");
        }
    }
}
