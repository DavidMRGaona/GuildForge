<?php

declare(strict_types=1);

namespace App\Application\Media\Services;

/**
 * What a record calls when it lets go of a stored file (replaced, cleared or deleted with the record). Once the
 * transaction commits, a queued check deletes the file only if nobody uses it any more, as the media library
 * would; any doubt keeps it, and it shows as unused in the library. Never throws, and never deletes by hand:
 * a file may be shared with other records since files can be chosen from the library.
 */
interface MediaFileReleaserInterface
{
    /**
     * @param  string  $storedPath  the path as the record stored it (e.g. events/2026/11/<uuid>.jpg)
     */
    public function release(string $storedPath): void;
}
