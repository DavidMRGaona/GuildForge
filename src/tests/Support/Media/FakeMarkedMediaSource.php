<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;

/**
 * Fake source that declares its module releases files only through the core and that its images may be
 * shown in the library (core#837), as every core source a form reuses from does.
 */
final class FakeMarkedMediaSource extends FakeMediaSource implements ReleasesMediaThroughCoreInterface, ShowsMediaPreviews {}
