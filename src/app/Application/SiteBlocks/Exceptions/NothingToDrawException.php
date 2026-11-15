<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Exceptions;

use RuntimeException;

/**
 * Thrown by SiteBlockInterface::props() when the block has nothing to show right now (no logos, no events
 * and no link): the renderer leaves it out quietly, with no warning, so its title is not drawn alone either.
 * Any other exception is a failure and is warned about.
 */
final class NothingToDrawException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The site block has nothing to draw.');
    }
}
