<?php

declare(strict_types=1);

namespace App\Application\Discussions\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Activity about something the record is about (for instance its game system): how many
 * discussions there are and when their last message came.
 */
final readonly class TopicActivityDTO
{
    public function __construct(
        public string $label,
        public string $url,
        public int $discussionCount,
        public ?DateTimeImmutable $lastPostedAt = null,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('A topic needs a label.');
        }

        SubjectDiscussionDTO::assertUrl($url);

        if ($discussionCount < 0) {
            throw new InvalidArgumentException('A topic cannot have a negative number of discussions.');
        }
    }

    /**
     * @return array{label: string, url: string, discussionCount: int, lastPostedAt: string|null}
     */
    public function toArray(TimezoneServiceInterface $timezone): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'discussionCount' => $this->discussionCount,
            'lastPostedAt' => $timezone->iso($this->lastPostedAt),
        ];
    }
}
