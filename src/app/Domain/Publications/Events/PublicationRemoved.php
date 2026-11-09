<?php

declare(strict_types=1);

namespace App\Domain\Publications\Events;

use InvalidArgumentException;

/**
 * A record offered through a publication source was deleted. Dispatched by its owner once the
 * deletion has committed, whether or not it was published.
 */
final readonly class PublicationRemoved
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        if (trim($type) === '' || trim($id) === '') {
            throw new InvalidArgumentException('A publication removal needs the type and the id.');
        }
    }
}
