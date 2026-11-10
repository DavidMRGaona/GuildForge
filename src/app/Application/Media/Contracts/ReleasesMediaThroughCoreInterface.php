<?php

declare(strict_types=1);

namespace App\Application\Media\Contracts;

/**
 * Marks a media source whose module never deletes files of its folders by hand: every file a record lets go
 * of goes through MediaFileReleaserInterface (or the DeletesCloudinaryImages trait), which keeps files that
 * anyone else still uses. Only files of such sources may be chosen from the library in a form, since a file
 * there can end up shared between records.
 */
interface ReleasesMediaThroughCoreInterface {}
