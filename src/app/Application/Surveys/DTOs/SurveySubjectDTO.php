<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use App\Application\Surveys\Enums\SurveySubjectStatus;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Snapshot of something a survey is about (a game table, a tournament, an event…).
 *
 * A survey stores it when it opens, so its data outlives the subject. Organizers are
 * carried by user id (the account UUID) only: their names are resolved when shown. They
 * feed statistics ("by organizer"); who may manage the subject's surveys is asked live
 * through SurveySubjectSourceInterface::canManage().
 *
 * Dates are instants (compared as such, whatever their timezone); either may be null.
 */
final readonly class SurveySubjectDTO
{
    /** Stable kebab-case type, as returned by SurveySubjectSourceInterface::sourceType(). */
    public const string SOURCE_TYPE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /** @var list<string> */
    public array $organizerIds;

    /** @var list<SurveySubjectAttributeDTO> */
    public array $attributes;

    /**
     * @param  list<string>  $organizerIds
     * @param  list<SurveySubjectAttributeDTO>  $attributes
     * @param  string|null  $url  Public page of the subject, if it has one
     * @param  SurveySubjectStatus  $status  Whether it has ended, live: never part of the snapshot
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
        public SurveySubjectStatus $status = SurveySubjectStatus::Active,
    ) {
        if (preg_match(self::SOURCE_TYPE_PATTERN, $sourceType) !== 1) {
            throw new InvalidArgumentException("Survey subject source type '{$sourceType}' must be kebab-case.");
        }

        if (trim($subjectId) === '') {
            throw new InvalidArgumentException('A survey subject needs an id.');
        }

        if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable && $endsAt < $startsAt) {
            throw new InvalidArgumentException("Survey subject '{$subjectId}' ends before it starts.");
        }

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
