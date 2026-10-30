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
 */
final readonly class SurveySubjectAttributeDTO
{
    private const string KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        public string $key,
        public string $label,
        public string|int|float|bool|null $value,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Survey subject attribute key '{$key}' must be snake_case.");
        }
    }
}
