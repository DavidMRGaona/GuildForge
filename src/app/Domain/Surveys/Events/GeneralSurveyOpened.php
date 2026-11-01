<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Events;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A general survey (not about a module's record) has opened and asked to be announced.
 *
 * The surveys module dispatches it once the opening has committed, only the first time the
 * survey opens and only when whoever made it ticked the announcement. A module that posts to
 * external channels listens to it; without one, nothing happens. It carries what a public
 * announcement shows and nothing personal: no author, no audience.
 *
 * `$url` is where to answer: the survey's public link when it has one, otherwise the page that
 * asks to sign in. `$closesAt` is an instant: compare it as such, whatever its timezone.
 */
final readonly class GeneralSurveyOpened
{
    public function __construct(
        public string $surveyId,
        public string $title,
        public ?string $description,
        public bool $isPoll,
        public ?DateTimeImmutable $closesAt,
        public string $url,
    ) {
        if (trim($surveyId) === '') {
            throw new InvalidArgumentException('A general survey announcement needs the survey id.');
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException("General survey '{$surveyId}' needs a title to be announced.");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException("General survey '{$surveyId}' needs an absolute http(s) URL.");
        }
    }
}
