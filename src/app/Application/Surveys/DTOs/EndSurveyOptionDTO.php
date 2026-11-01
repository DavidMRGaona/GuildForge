<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * One answer to "which survey goes out when this subject ends", as the form shows it. The value
 * means something only to the surveys module; the group puts related options under one heading.
 */
final readonly class EndSurveyOptionDTO
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $group = null,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('An end survey option needs a value.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException("End survey option '{$value}' needs a label.");
        }
    }
}
