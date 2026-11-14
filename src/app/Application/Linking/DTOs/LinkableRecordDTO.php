<?php

declare(strict_types=1);

namespace App\Application\Linking\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A record another part of the site can link to (an event, a tournament, a booking…), as its
 * source shows it to one viewer. Read live every time: whoever stores a link keeps only the
 * type and id, never this title, so a title never outlives the viewer's right to see it.
 */
final readonly class LinkableRecordDTO
{
    /** Stable kebab-case type, as returned by LinkableRecordSourceInterface::type(). */
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\z/';

    /** Longest id a source may use (consumers store it in a column of this size). */
    public const int MAX_ID_LENGTH = 100;

    /**
     * @param  DateTimeImmutable|null  $date  To sort and show the record: an instant (shown in the display zone), or a calendar day when $dateIsDay
     * @param  bool  $dateIsDay  True when $date is a calendar day, read as it is and never converted between zones
     * @param  string|null  $panelUrl  Absolute http(s) URL of the record's panel page, only when this viewer may open it
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $title,
        public ?DateTimeImmutable $date = null,
        public bool $dateIsDay = false,
        public ?string $panelUrl = null,
    ) {
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            throw new InvalidArgumentException("Linkable record type '{$type}' must be kebab-case.");
        }

        if (trim($id) === '' || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf('A linkable record id must have 1 to %d characters.', self::MAX_ID_LENGTH));
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException("Linkable record '{$type}:{$id}' needs a title.");
        }

        if ($dateIsDay && ! $date instanceof DateTimeImmutable) {
            throw new InvalidArgumentException("Linkable record '{$type}:{$id}' is dated by day but has no date.");
        }

        if ($panelUrl !== null && ! $this->isAbsoluteHttpUrl($panelUrl)) {
            throw new InvalidArgumentException("Linkable record '{$type}:{$id}' needs an absolute http(s) panel URL.");
        }
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && $host !== '';
    }
}
