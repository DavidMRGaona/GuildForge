<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * What someone picked in a subject's form for its end survey: one of the offered values and,
 * for the own survey, the template it starts from.
 */
final readonly class EndSurveySelection
{
    public function __construct(
        public string $value,
        public ?string $baseTemplate = null,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('An end survey selection needs a value.');
        }
    }
}
