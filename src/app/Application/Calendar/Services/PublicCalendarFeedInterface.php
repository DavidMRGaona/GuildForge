<?php

declare(strict_types=1);

namespace App\Application\Calendar\Services;

use App\Application\Calendar\DTOs\CalendarSubscriptionLinksDTO;

/**
 * The site's public calendar feed (`/calendario.ics`, route `calendar.public.ics`):
 * what a guest sees in /calendario, from every registered source, for anyone to
 * subscribe to without an account. Modules show its address through this contract
 * instead of building it by hand.
 */
interface PublicCalendarFeedInterface
{
    /**
     * Absolute https address of the feed.
     */
    public function feedUrl(): string;

    /**
     * Name calendar services show for the feed (it carries the site's name).
     */
    public function calendarName(): string;

    /**
     * Feed, webcal, Google Calendar and Outlook addresses to subscribe to it.
     */
    public function subscriptionLinks(): CalendarSubscriptionLinksDTO;
}
