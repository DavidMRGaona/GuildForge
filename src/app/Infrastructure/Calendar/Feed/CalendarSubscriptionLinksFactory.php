<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Feed;

use App\Application\Calendar\DTOs\CalendarSubscriptionLinksDTO;
use App\Application\Calendar\Services\CalendarSubscriptionLinksFactoryInterface;

final readonly class CalendarSubscriptionLinksFactory implements CalendarSubscriptionLinksFactoryInterface
{
    private const string GOOGLE = 'https://calendar.google.com/calendar/r';

    private const string OUTLOOK = 'https://outlook.live.com/calendar/0/addfromweb';

    public function for(string $feedUrl, string $calendarName): CalendarSubscriptionLinksDTO
    {
        $webcalUrl = (string) preg_replace('#^https?://#', 'webcal://', $feedUrl);

        return new CalendarSubscriptionLinksDTO(
            feedUrl: $feedUrl,
            webcalUrl: $webcalUrl,
            // Google subscribes by the webcal address; Outlook on the web wants the https one
            googleUrl: self::GOOGLE.'?'.$this->query(['cid' => $webcalUrl]),
            outlookUrl: self::OUTLOOK.'?'.$this->query(['url' => $feedUrl, 'name' => $calendarName]),
        );
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function query(array $parameters): string
    {
        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
