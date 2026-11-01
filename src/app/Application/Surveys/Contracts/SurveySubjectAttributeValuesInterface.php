<?php

declare(strict_types=1);

namespace App\Application\Surveys\Contracts;

use App\Application\Surveys\DTOs\SurveySubjectAttributeValueDTO;

/**
 * Optional for a SurveySubjectSourceInterface: the values an attribute of its subjects can take
 * (the game systems of its tables, say), so another module can offer them in its settings
 * without depending on the source's module. Ids are the ones its snapshots carry as the
 * attribute's value id.
 */
interface SurveySubjectAttributeValuesInterface
{
    /**
     * Sorted as they should be shown; empty for an attribute the source does not list.
     *
     * @return list<SurveySubjectAttributeValueDTO>
     */
    public function attributeValues(string $key): array;
}
