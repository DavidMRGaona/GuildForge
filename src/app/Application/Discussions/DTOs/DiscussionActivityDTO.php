<?php

declare(strict_types=1);

namespace App\Application\Discussions\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The record's own discussion: how many messages it has, when the last one came, where to read it
 * and where to go to comment (which may sign the person in first).
 */
final readonly class DiscussionActivityDTO
{
    public function __construct(
        public string $url,
        public string $actionUrl,
        public int $commentCount,
        public ?DateTimeImmutable $lastPostedAt = null,
    ) {
        SubjectDiscussionDTO::assertUrl($url);
        SubjectDiscussionDTO::assertUrl($actionUrl);

        if ($commentCount < 0) {
            throw new InvalidArgumentException('A discussion cannot have a negative number of messages.');
        }
    }

    /**
     * @return array{url: string, actionUrl: string, commentCount: int, lastPostedAt: string|null}
     */
    public function toArray(TimezoneServiceInterface $timezone): array
    {
        return [
            'url' => $this->url,
            'actionUrl' => $this->actionUrl,
            'commentCount' => $this->commentCount,
            'lastPostedAt' => $timezone->iso($this->lastPostedAt),
        ];
    }
}
