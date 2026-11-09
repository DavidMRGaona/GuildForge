<?php

declare(strict_types=1);

namespace App\Application\Discussions\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use InvalidArgumentException;

/**
 * What a page shows of a record's discussions elsewhere: its own discussion, if the person may see
 * it, and the activity of its topics. Never empty: with nothing to show the answer is null.
 */
final readonly class SubjectDiscussionDTO
{
    /** @var list<TopicActivityDTO> */
    public array $topics;

    /**
     * @param  list<TopicActivityDTO>  $topics
     */
    public function __construct(
        public ?DiscussionActivityDTO $discussion,
        array $topics = [],
    ) {
        if (! $discussion instanceof DiscussionActivityDTO && $topics === []) {
            throw new InvalidArgumentException('A subject discussion needs its discussion or a topic: answer null instead.');
        }

        $this->topics = $topics;
    }

    /**
     * @throws InvalidArgumentException unless it is an absolute http(s) URL
     */
    public static function assertUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('A discussion link must be an absolute http(s) URL.');
        }
    }

    /**
     * For the page (Inertia), dates in the display zone.
     *
     * @return array{discussion: array{url: string, actionUrl: string, commentCount: int, lastPostedAt: string|null}|null, topics: list<array{label: string, url: string, discussionCount: int, lastPostedAt: string|null}>}
     */
    public function toArray(TimezoneServiceInterface $timezone): array
    {
        return [
            'discussion' => $this->discussion?->toArray($timezone),
            'topics' => array_map(static fn (TopicActivityDTO $topic): array => $topic->toArray($timezone), $this->topics),
        ];
    }
}
