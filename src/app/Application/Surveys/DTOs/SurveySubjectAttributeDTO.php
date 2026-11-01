<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * A fact about a survey subject used to slice statistics (e.g. game system, format).
 *
 * The key is a stable snake_case identifier; the label is already translated. Both are
 * frozen in the survey's snapshot, so results keep their meaning after the subject or
 * the module that offered it is gone.
 *
 * The value id is the value's stable identifier in its module (a game system's id, say), for
 * whoever has to recognise the value after its label changes; null when the value has none.
 * A source that lists an attribute's values (SurveySubjectAttributeValuesInterface) uses the
 * same ids.
 */
final readonly class SurveySubjectAttributeDTO
{
    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        public string $key,
        public string $label,
        public string|int|float|bool|null $value,
        public ?string $valueId = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Survey subject attribute key '{$key}' must be snake_case.");
        }

        if ($valueId !== null && trim($valueId) === '') {
            throw new InvalidArgumentException("Survey subject attribute '{$key}' has a blank value id.");
        }

        if ($valueId !== null && $value === null) {
            throw new InvalidArgumentException("Survey subject attribute '{$key}' has a value id but no value.");
        }
    }
}
