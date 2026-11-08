<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Feed;

use App\Application\Calendar\Services\PublicCalendarFeedInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Infrastructure\Calendar\Feed\PublicCalendarFeedDocument;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PublicCalendarFeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function the_feed_address_is_the_named_public_route(): void
    {
        $this->assertSame(route('calendar.public.ics'), $this->feed()->feedUrl());
    }

    #[Test]
    public function the_calendar_is_named_after_the_site(): void
    {
        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('siteName')->andReturn('Asociación de ejemplo');
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $this->assertSame('Calendario · Asociación de ejemplo', $this->feed()->calendarName());
    }

    #[Test]
    public function the_subscription_links_point_at_the_public_feed(): void
    {
        $links = $this->feed()->subscriptionLinks();

        $this->assertSame(route('calendar.public.ics'), $links->feedUrl);
        $this->assertStringStartsWith('webcal://', $links->webcalUrl);
        $this->assertStringEndsWith('/calendario.ics', $links->webcalUrl);
    }

    #[Test]
    public function the_document_is_still_written_when_the_cache_fails(): void
    {
        $cache = Mockery::mock(Repository::class);
        $cache->shouldReceive('remember')->andThrow(new RuntimeException('Cache unreachable'));
        $this->app->instance(Repository::class, $cache);

        $document = $this->app->make(PublicCalendarFeedDocument::class)->contents();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $document);
        $this->assertStringContainsString("\r\nX-WR-CALNAME:", $document);
    }

    private function feed(): PublicCalendarFeedInterface
    {
        return $this->app->make(PublicCalendarFeedInterface::class);
    }
}
