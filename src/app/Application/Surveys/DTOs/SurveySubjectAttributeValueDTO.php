<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * A value a subject attribute can take, as listed by its source: its stable id (the one the
 * source's snapshots carry as the attribute's value id) and its label, already translated.
 */
final readonly class SurveySubjectAttributeValueDTO
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A survey subject attribute value needs an id.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException("Survey subject attribute value '{$id}' needs a label.");
        }
    }
}
