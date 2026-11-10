<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Application\Content\DTOs\EditorImageMoveReportDTO;

/**
 * Moves the images that the core's rich editors stored on the local disk before core#481
 * (events, articles, the site's own guides, legal pages and «Nosotros») to Cloudinary.
 */
interface EditorImageMoverInterface
{
    /**
     * Uploads every local image referenced in the stored HTML, points the HTML at it and deletes
     * the local copy once every value using it is saved. Running it again moves nothing new; with
     * $dryRun nothing is uploaded, written or deleted.
     */
    public function move(bool $dryRun = false): EditorImageMoveReportDTO;
}
