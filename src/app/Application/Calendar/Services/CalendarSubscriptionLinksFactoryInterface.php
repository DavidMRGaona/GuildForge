<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\CalendarSubscriptionLinksDTO;

interface CalendarSubscriptionLinksFactoryInterface
{
    /**
     * The subscription addresses of a feed served at the given http(s) address,
     * with the name calendar services show for it.
     */
    public function for(string $feedUrl, string $calendarName): CalendarSubscriptionLinksDTO;
}
