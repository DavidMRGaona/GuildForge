<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\QrCodes;

use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use App\Infrastructure\QrCodes\QrCodeContentPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QrCodeContentPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function ownHttpsUrls(): iterable
    {
        yield 'home' => ['https://guild.example.org'];
        yield 'home with slash' => ['https://guild.example.org/'];
        yield 'path and query' => ['https://guild.example.org/enlaces/mesa-3?ref=cartel#top'];
        yield 'host in capitals' => ['HTTPS://Guild.Example.ORG/eventos'];
        yield 'explicit default port' => ['https://guild.example.org:443/eventos'];
        yield 'accented path' => ['https://guild.example.org/partidas/dragón'];
    }

    #[DataProvider('ownHttpsUrls')]
    public function test_it_accepts_https_urls_of_the_site(string $url): void
    {
        $this->expectNotToPerformAssertions();

        (new QrCodeContentPolicy('https://guild.example.org', allowsHttp: false))->assertAllowed($url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'other host' => ['https://evil.example.com/'];
        yield 'subdomain of the site' => ['https://www.guild.example.org/'];
        yield 'site as a subdomain' => ['https://guild.example.org.evil.example.com/'];
        yield 'plain http' => ['http://guild.example.org/'];
        yield 'other port' => ['https://guild.example.org:8443/'];
        yield 'user info' => ['https://guild.example.org@evil.example.com/'];
        yield 'user info before the site' => ['https://user:secret@guild.example.org/'];
        yield 'backslash' => ['https://evil.example.com\\@guild.example.org/'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data uri' => ['data:text/html,<script>alert(1)</script>'];
        yield 'protocol relative' => ['//guild.example.org/'];
        yield 'relative path' => ['/eventos'];
        yield 'mailto' => ['mailto:info@guild.example.org'];
        yield 'space' => ['https://guild.example.org/a b'];
        yield 'newline' => ["https://guild.example.org/\nx"];
        yield 'too long' => ['https://guild.example.org/'.str_repeat('a', QrCodeContentPolicy::MAX_LENGTH)];
    }

    #[DataProvider('rejectedUrls')]
    public function test_it_rejects_anything_but_https_urls_of_the_site(string $url): void
    {
        $this->expectException(InvalidQrCodeContentException::class);

        (new QrCodeContentPolicy('https://guild.example.org', allowsHttp: false))->assertAllowed($url);
    }

    public function test_it_accepts_http_only_where_allowed(): void
    {
        $policy = new QrCodeContentPolicy('http://localhost:8080', allowsHttp: true);

        $policy->assertAllowed('http://localhost:8080/enlaces');
        $policy->assertAllowed('https://localhost:8080/enlaces');

        $this->expectException(InvalidQrCodeContentException::class);

        $policy->assertAllowed('http://localhost/enlaces');
    }

    public function test_http_is_rejected_on_an_http_site_without_permission(): void
    {
        $this->expectException(InvalidQrCodeContentException::class);

        (new QrCodeContentPolicy('http://localhost:8080', allowsHttp: false))->assertAllowed('http://localhost:8080/');
    }

    public function test_the_port_of_the_site_url_is_required(): void
    {
        $policy = new QrCodeContentPolicy('https://guild.example.org:8443', allowsHttp: false);

        $policy->assertAllowed('https://guild.example.org:8443/eventos');

        $this->expectException(InvalidQrCodeContentException::class);

        $policy->assertAllowed('https://guild.example.org/eventos');
    }

    public function test_it_fails_closed_without_a_site_url(): void
    {
        $this->expectException(InvalidQrCodeContentException::class);

        (new QrCodeContentPolicy('', allowsHttp: false))->assertAllowed('https://guild.example.org/');
    }
}
