<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Feed;

use App\Application\Calendar\DTOs\CalendarSubscriptionLinksDTO;
use App\Application\Calendar\Services\CalendarSubscriptionLinksFactoryInterface;
use App\Application\Calendar\Services\PublicCalendarFeedInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Contracts\Routing\UrlGenerator;

final readonly class PublicCalendarFeed implements PublicCalendarFeedInterface
{
    public const string ROUTE = 'calendar.public.ics';

    public function __construct(
        private UrlGenerator $urls,
        private SiteBrandingServiceInterface $branding,
        private CalendarSubscriptionLinksFactoryInterface $links,
    ) {}

    public function feedUrl(): string
    {
        return $this->urls->route(self::ROUTE);
    }

    public function calendarName(): string
    {
        return (string) __('calendar.public.feed_name', ['site' => $this->branding->siteName()]);
    }

    public function subscriptionLinks(): CalendarSubscriptionLinksDTO
    {
        return $this->links->for($this->feedUrl(), $this->calendarName());
    }
}
