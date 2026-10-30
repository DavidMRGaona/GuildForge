<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Snapshot of something a survey is about (a game table, a tournament, an event…).
 *
 * A survey stores it when it opens, so its data outlives the subject. Organizers are
 * carried by user id only: their names are resolved when shown.
 */
final readonly class SurveySubjectDTO
{
    /** @var list<string> */
    public array $organizerIds;

    /** @var list<SurveySubjectAttributeDTO> */
    public array $attributes;

    /**
     * @param  list<string>  $organizerIds
     * @param  list<SurveySubjectAttributeDTO>  $attributes
     * @param  string|null  $url  Public page of the subject, if it has one
     */
    public function __construct(
        public string $sourceType,
        public string $subjectId,
        public string $title,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        array $organizerIds = [],
        array $attributes = [],
        public ?string $url = null,
    ) {
        $keys = [];

        foreach ($attributes as $attribute) {
            if (isset($keys[$attribute->key])) {
                throw new InvalidArgumentException("Survey subject attribute key '{$attribute->key}' is used twice.");
            }

            $keys[$attribute->key] = true;
        }

        $this->organizerIds = array_values(array_unique($organizerIds));
        $this->attributes = $attributes;
    }

    public function attribute(string $key): ?SurveySubjectAttributeDTO
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute->key === $key) {
                return $attribute;
            }
        }

        return null;
    }
}
