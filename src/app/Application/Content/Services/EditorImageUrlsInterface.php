<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

/**
 * Delivery URLs of the images inserted with the rich text editors (core#481).
 */
interface EditorImageUrlsInterface
{
    /**
     * Full delivery URL of an editor image ("<origin>/content/<year>/<month>/<file>"), built
     * without asking the image service whether it exists; null for any other path.
     */
    public function urlFor(string $path): ?string;
}
