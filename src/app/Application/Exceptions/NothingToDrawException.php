<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/**
 * Thrown by whatever draws a piece of a page for a person when it has nothing to show right now: a
 * site block's props() (no logos, no events and no link) or a profile section's props() (an empty
 * bio, no posts the person may read). The renderer leaves it out quietly, with no warning, so its
 * title is not drawn alone either. Any other exception is a failure and is warned about.
 */
class NothingToDrawException extends RuntimeException
{
    final public function __construct(string $message = 'There is nothing to draw.')
    {
        parent::__construct($message);
    }

    public static function make(): static
    {
        return new static;
    }
}
