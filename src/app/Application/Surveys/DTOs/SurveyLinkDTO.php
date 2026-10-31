<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use App\Application\Surveys\Enums\SurveyLinkAction;
use InvalidArgumentException;

/**
 * A survey of a subject that one person can reach from the subject's page: answer it, or see
 * its results when it shows them to whoever answered.
 */
final readonly class SurveyLinkDTO
{
    public function __construct(
        public string $title,
        public string $url,
        public SurveyLinkAction $action,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('A survey link needs a title.');
        }

        if (trim($url) === '') {
            throw new InvalidArgumentException("Survey link '{$title}' needs a URL.");
        }
    }
}
