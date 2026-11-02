<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

use App\Domain\Search\Enums\SearchAudience;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One searchable record offered by an indexed search source.
 */
final readonly class SearchDocumentDTO
{
    /** Length of the stored source id column. */
    public const int MAX_ID_LENGTH = 100;

    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public string $url,
        public ?string $keywords = null,
        public ?string $meta = null,
        public ?string $imagePublicId = null,
        public ?DateTimeImmutable $startsAt = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?DateTimeImmutable $publishedAt = null,
        public bool $finished = false,
        public SearchAudience $audience = SearchAudience::Public,
        public ?string $locale = null,
    ) {
        if (mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf('A search document id cannot be longer than %d characters.', self::MAX_ID_LENGTH));
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException('A search document needs a title.');
        }

        if (trim($url) === '') {
            throw new InvalidArgumentException('A search document needs a url.');
        }
    }
}
