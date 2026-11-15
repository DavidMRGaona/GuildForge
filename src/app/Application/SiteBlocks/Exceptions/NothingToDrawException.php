<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Exceptions;

use RuntimeException;

/**
 * Thrown by SiteBlockInterface::props() when the block has nothing to show right now (no logos, no events
 * and no link), and by ProfileSectionInterface::props() when the section has nothing to show (an empty
 * bio, no posts the person may read): the renderer leaves it out quietly, with no warning, so its title
 * is not drawn alone either. Any other exception is a failure and is warned about.
 */
final class NothingToDrawException extends RuntimeException
{
    public static function make(): self
    {
        return new self('There is nothing to draw.');
    }
}
