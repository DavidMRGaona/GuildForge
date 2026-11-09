<?php

declare(strict_types=1);

namespace App\Application\Publications\DTOs;

use InvalidArgumentException;

/**
 * Something a publication is about besides its own kind, such as the game system of a table
 * (`game-system.<uuid>`). The key is stable and chosen by the source; the label is what people read.
 */
final readonly class PublicationTopicDTO
{
    /** 1 to 64 characters from [a-z0-9._-], starting with a letter or digit. */
    public const string KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    public function __construct(
        public string $key,
        public string $label,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Publication topic key '{$key}' must be 1 to 64 characters from [a-z0-9._-].");
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException("Publication topic '{$key}' needs a label.");
        }
    }
}
