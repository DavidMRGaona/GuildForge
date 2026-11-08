<?php

declare(strict_types=1);

namespace App\Application\Calendar\DTOs;

/**
 * The addresses people use to subscribe to a calendar feed: the feed itself
 * (https), the same address as webcal:// (Apple Calendar, Outlook, ICSx⁵) and the
 * one-tap pages of Google Calendar and Outlook on the web, since browsers other
 * than Safari do not hand webcal:// to an app.
 */
final readonly class CalendarSubscriptionLinksDTO
{
    public function __construct(
        public string $feedUrl,
        public string $webcalUrl,
        public string $googleUrl,
        public string $outlookUrl,
    ) {}

    /**
     * @return array{feedUrl: string, webcalUrl: string, googleUrl: string, outlookUrl: string}
     */
    public function toArray(): array
    {
        return [
            'feedUrl' => $this->feedUrl,
            'webcalUrl' => $this->webcalUrl,
            'googleUrl' => $this->googleUrl,
            'outlookUrl' => $this->outlookUrl,
        ];
    }
}
