<?php

declare(strict_types=1);

namespace App\Infrastructure\Linking\Services;

/**
 * Marks one request or queued job for the linkable record registry. Bound scoped, so the
 * framework drops it between requests and between jobs; the registry keeps the sources it built
 * only while the scope it built them in is the current one.
 */
final class LinkableRecordSourceScope {}
