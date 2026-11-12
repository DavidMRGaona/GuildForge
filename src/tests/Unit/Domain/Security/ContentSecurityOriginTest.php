<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use App\Domain\Security\ContentSecurityOrigin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentSecurityOriginTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validOrigins(): iterable
    {
        yield 'host' => ['https://player.example.com'];
        yield 'two labels' => ['https://example.org'];
        yield 'hyphen' => ['https://www.video-host.example'];
        yield 'port' => ['https://embed.example.com:8443'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidOrigins(): iterable
    {
        yield 'http' => ['http://player.example.com'];
        yield 'wildcard host' => ['https://*.example.com'];
        yield 'bare wildcard' => ['*'];
        yield 'bare scheme' => ['https:'];
        yield 'scheme only' => ['https://'];
        yield 'self keyword' => ["'self'"];
        yield 'unsafe keyword' => ["'unsafe-inline'"];
        yield 'data scheme' => ['data:'];
        yield 'blob scheme' => ['blob:'];
        yield 'path' => ['https://player.example.com/embed'];
        yield 'trailing slash' => ['https://player.example.com/'];
        yield 'query' => ['https://player.example.com?x=1'];
        yield 'uppercase' => ['https://Player.Example.com'];
        yield 'single label' => ['https://localhost'];
        yield 'ipv4' => ['https://127.0.0.1'];
        yield 'ipv6' => ['https://[::1]'];
        yield 'credentials' => ['https://user@player.example.com'];
        yield 'space' => ['https://player.example.com https://other.example.com'];
        yield 'semicolon' => ["https://player.example.com; script-src 'unsafe-inline'"];
        yield 'port zero' => ['https://player.example.com:0'];
        yield 'port too large' => ['https://player.example.com:65536'];
        yield 'leading hyphen' => ['https://-player.example.com'];
        yield 'empty' => [''];
        yield 'newline' => ["https://player.example.com\n"];
    }

    #[DataProvider('validOrigins')]
    public function test_it_accepts_exact_https_origins(string $value): void
    {
        $origin = ContentSecurityOrigin::tryFrom($value);

        $this->assertNotNull($origin);
        $this->assertSame($value, $origin->value);
    }

    #[DataProvider('invalidOrigins')]
    public function test_it_rejects_anything_else(string $value): void
    {
        $this->assertNull(ContentSecurityOrigin::tryFrom($value));
    }
}
