<?php

declare(strict_types=1);

namespace App\Application\Media\Contracts;

/**
 * Marks a media source whose images the media library may show (core#837). The library hides every image
 * by default: a file shows its thumbnail, its preview, «Abrir original» and «Copiar URL», and can be chosen
 * in a form, only when the active source that claims its folder implements this interface, none of its
 * usages is hidden (MediaUsageDTO::$hidden) and the source is not hidden (avatars).
 *
 * A source declares it once it is sure every image of its folders may be seen by whoever opens the library;
 * mixed cases (a private category, a secret achievement) mark those usages as hidden. Files of unknown origin
 * and of sources without it are shown with a placeholder. It never changes whether a file is used or can be
 * deleted.
 */
interface ShowsMediaPreviews {}
