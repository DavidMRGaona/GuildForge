<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * What a subject's form offers for the survey that goes out when the subject ends: the options,
 * the one in force, and whether it can still change. The own survey option, when there is one,
 * starts from one of the base templates.
 */
final readonly class EndSurveyChoiceDTO
{
    /**
     * @param  list<EndSurveyOptionDTO>  $options
     * @param  string|null  $ownValue  The option that prepares a survey of the subject's own
     * @param  list<EndSurveyOptionDTO>  $baseTemplates  What the own survey can start from
     * @param  string|null  $lockedReason  Why it can no longer change, already translated
     * @param  string|null  $editUrl  Where the subject's end survey is edited (only seen, once locked), when there is one
     */
    public function __construct(
        public array $options,
        public string $selected,
        public ?string $ownValue = null,
        public array $baseTemplates = [],
        public bool $locked = false,
        public ?string $lockedReason = null,
        public ?string $editUrl = null,
    ) {
        if ($options === []) {
            throw new InvalidArgumentException('An end survey choice needs at least one option.');
        }

        $values = array_map(static fn (EndSurveyOptionDTO $option): string => $option->value, $options);
        if (! in_array($selected, $values, true)) {
            throw new InvalidArgumentException("The selected end survey '{$selected}' is not one of the options.");
        }

        if ($ownValue !== null && ! in_array($ownValue, $values, true)) {
            throw new InvalidArgumentException("The own end survey option '{$ownValue}' is not one of the options.");
        }

        if ($ownValue !== null && $baseTemplates === []) {
            throw new InvalidArgumentException('An own end survey needs templates to start from.');
        }

        if ($locked && trim((string) $lockedReason) === '') {
            throw new InvalidArgumentException('A locked end survey choice needs a reason.');
        }

        if ($editUrl !== null && trim($editUrl) === '') {
            throw new InvalidArgumentException('An end survey edit URL cannot be blank.');
        }
    }
}
