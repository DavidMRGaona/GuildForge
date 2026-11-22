<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Contracts\ShowsMediaPreviews;

/**
 * Fake source whose images the library may show (core#837).
 */
final class FakePreviewedMediaSource extends FakeMediaSource implements ShowsMediaPreviews {}
