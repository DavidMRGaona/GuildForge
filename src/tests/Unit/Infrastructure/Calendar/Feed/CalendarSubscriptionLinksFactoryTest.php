<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Feed;

use App\Application\Calendar\Services\CalendarSubscriptionLinksFactoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CalendarSubscriptionLinksFactoryTest extends TestCase
{
    #[Test]
    public function the_webcal_address_replaces_the_https_scheme(): void
    {
        $links = $this->factory()->for('https://example.test/calendario.ics', 'Calendario · Asociación');

        $this->assertSame('https://example.test/calendario.ics', $links->feedUrl);
        $this->assertSame('webcal://example.test/calendario.ics', $links->webcalUrl);
    }

    #[Test]
    public function the_webcal_address_replaces_the_http_scheme(): void
    {
        $links = $this->factory()->for('http://localhost:8080/calendario.ics', 'Calendario');

        $this->assertSame('webcal://localhost:8080/calendario.ics', $links->webcalUrl);
    }

    #[Test]
    public function the_google_link_subscribes_to_the_webcal_address(): void
    {
        $links = $this->factory()->for('https://example.test/calendario.ics', 'Calendario');

        $this->assertStringStartsWith('https://calendar.google.com/calendar/r?', $links->googleUrl);
        $this->assertSame(['cid' => 'webcal://example.test/calendario.ics'], $this->queryOf($links->googleUrl));
    }

    #[Test]
    public function the_outlook_link_subscribes_to_the_https_address_with_its_name(): void
    {
        $links = $this->factory()->for('https://example.test/calendario.ics', 'Calendario · Asociación');

        $this->assertStringStartsWith('https://outlook.live.com/calendar/0/addfromweb?', $links->outlookUrl);
        $this->assertSame(
            ['url' => 'https://example.test/calendario.ics', 'name' => 'Calendario · Asociación'],
            $this->queryOf($links->outlookUrl),
        );
    }

    #[Test]
    public function the_links_serialise_for_the_frontend(): void
    {
        $links = $this->factory()->for('https://example.test/calendario.ics', 'Calendario');

        $this->assertSame([
            'feedUrl' => $links->feedUrl,
            'webcalUrl' => $links->webcalUrl,
            'googleUrl' => $links->googleUrl,
            'outlookUrl' => $links->outlookUrl,
        ], $links->toArray());
    }

    private function factory(): CalendarSubscriptionLinksFactoryInterface
    {
        return app(CalendarSubscriptionLinksFactoryInterface::class);
    }

    /**
     * @return array<string, string>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
