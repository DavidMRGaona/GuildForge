<?php

declare(strict_types=1);

namespace App\Application\Publications\DTOs;

use App\Application\Publications\Enums\PublicationState;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Live view of a published record (an event, a game table, a campaign…), as its source gives it.
 *
 * Whoever announces it elsewhere reads it again whenever PublicationChanged arrives and compares
 * it with what it announced last, so nothing here is a snapshot to keep in step by hand.
 *
 * Dates are instants (compared as such, whatever their timezone). `isFull` and `registrationOpen`
 * are false for records that take no registrations.
 */
final readonly class PublicationDTO
{
    /** Stable kebab-case type, as returned by PublicationSourceInterface::type(). */
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*\z/';

    /** Longest id a source may use. */
    public const int MAX_ID_LENGTH = 100;

    /** @var list<PublicationTopicDTO> */
    public array $topics;

    /**
     * @param  string  $summary  Plain text, without markup; may be empty
     * @param  string  $url  Absolute http(s) URL of the record's public page
     * @param  list<PublicationTopicDTO>  $topics
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $title,
        public string $summary,
        public string $url,
        public PublicationState $state,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        array $topics = [],
        public bool $isFull = false,
        public bool $registrationOpen = false,
        public ?DateTimeImmutable $registrationClosesAt = null,
    ) {
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            throw new InvalidArgumentException("Publication type '{$type}' must be kebab-case.");
        }

        if (trim($id) === '' || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf('A publication id must have 1 to %d characters.', self::MAX_ID_LENGTH));
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException("Publication '{$type}:{$id}' needs a title.");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException("Publication '{$type}:{$id}' needs an absolute http(s) URL.");
        }

        if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable && $endsAt < $startsAt) {
            throw new InvalidArgumentException("Publication '{$type}:{$id}' ends before it starts.");
        }

        $keys = [];

        foreach ($topics as $topic) {
            if (isset($keys[$topic->key])) {
                throw new InvalidArgumentException("Publication topic key '{$topic->key}' is used twice.");
            }

            $keys[$topic->key] = true;
        }

        $this->topics = $topics;
    }
}
