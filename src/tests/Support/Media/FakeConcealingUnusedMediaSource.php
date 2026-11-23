<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\ConcealsUnusedMediaFiles;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;

/**
 * Fake source whose used images the library may show, but whose unused files may be private (core#843), as
 * the forum's: the images of a deleted thread in a members-only category stay behind without any usage.
 */
final class FakeConcealingUnusedMediaSource extends FakeMediaSource implements ConcealsUnusedMediaFiles, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews {}
