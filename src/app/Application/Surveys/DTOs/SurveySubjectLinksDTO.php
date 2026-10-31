<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * Where a subject's page sends one person for its surveys: the page that manages them, for
 * whoever organizes the subject, and the surveys that person can answer or see the results of.
 */
final readonly class SurveySubjectLinksDTO
{
    /**
     * @param  string|null  $manageUrl  The subject's surveys page, only for whoever manages the subject
     * @param  list<SurveyLinkDTO>  $surveys
     */
    public function __construct(
        public ?string $manageUrl = null,
        public array $surveys = [],
    ) {
        if ($manageUrl !== null && trim($manageUrl) === '') {
            throw new InvalidArgumentException('A survey subject manage URL cannot be blank.');
        }
    }

    public static function none(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->manageUrl === null && $this->surveys === [];
    }
}
