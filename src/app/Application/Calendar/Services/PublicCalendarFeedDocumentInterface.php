<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

interface PublicCalendarFeedDocumentInterface
{
    /**
     * The iCalendar document of the public feed. It is the same for everyone and
     * may be served from a short-lived cache.
     */
    public function contents(): string;
}
