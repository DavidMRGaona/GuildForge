<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Application\Content\DTOs\EditorImageMoveReportDTO;
use InvalidArgumentException;

/**
 * Moves the images that the rich editors stored on the local disk before core#481 to Cloudinary: those
 * of every registered editor text source, the core's (events, articles, the site's own guides, legal
 * pages and «Nosotros») and the modules' (core#496).
 */
interface EditorImageMoverInterface
{
    /**
     * Uploads every local image referenced in the stored HTML, points the HTML at it and deletes
     * the local copy once every text naming it, in every source, is saved. Running it again moves
     * nothing new; with $dryRun nothing is uploaded, written or deleted. With $source only the texts
     * of that source type move, and a local copy that a text of another source names is kept. No
     * local copy is deleted while some texts cannot be read (see the report's held reasons).
     *
     * Images count as local when their URL is relative or on the site's host; $hosts adds previous
     * hosts of the site (lowercase host names). A "/storage/" URL on any other host is left alone and
     * reported, saying whether its file is here.
     *
     * @param  list<string>  $hosts
     *
     * @throws InvalidArgumentException when $source is not the type of a registered source
     */
    public function move(bool $dryRun = false, array $hosts = [], ?string $source = null): EditorImageMoveReportDTO;
}
