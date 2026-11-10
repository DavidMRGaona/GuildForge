<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;

/**
 * Fake source that declares its module releases files only through the core.
 */
final class FakeMarkedMediaSource extends FakeMediaSource implements ReleasesMediaThroughCoreInterface {}
