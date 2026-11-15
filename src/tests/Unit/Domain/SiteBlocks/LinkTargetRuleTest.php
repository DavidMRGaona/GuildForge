<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\SiteBlocks;

use App\Domain\SiteBlocks\LinkTargetRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkTargetRuleTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function links(): array
    {
        return [
            'https address' => ['https://example.com/a?b=1#c', 'https://example.com/a?b=1#c'],
            'https in capitals' => ['HTTPS://Example.com', 'HTTPS://Example.com'],
            'path of the site' => ['/eventos', '/eventos'],
            'root' => ['/', '/'],
            'path with query' => ['/buscar?q=rol', '/buscar?q=rol'],
            'trimmed' => ['  https://example.com  ', 'https://example.com'],
            'javascript' => ['javascript:alert(1)', null],
            'javascript in capitals' => ['JaVaScRiPt:alert(1)', null],
            'javascript with a tab' => ["java\tscript:alert(1)", null],
            'data' => ['data:text/html;base64,PHNjcmlwdD4=', null],
            'mailto' => ['mailto:a@example.com', null],
            'protocol relative' => ['//evil.example', null],
            'backslash host' => ['/\\evil.example', null],
            'backslash in address' => ['https://example.com\\@evil.example', null],
            'relative without slash' => ['eventos', null],
            'http' => ['http://example.com', null],
            'no host' => ['https://', null],
            'user name' => ['https://user@example.com', null],
            'user and password' => ['https://user:secret@example.com', null],
            'space inside' => ['https://exa mple.com', null],
            'newline' => ["/eventos\nhttps://evil.example", null],
            'empty' => ['', null],
            'blank' => ['   ', null],
            'not a string' => [['https://example.com'], null],
            'null' => [null, null],
            'too long' => ['https://example.com/'.str_repeat('a', 2100), null],
        ];
    }

    #[DataProvider('links')]
    public function test_it_only_lets_through_https_and_paths_of_the_site(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, LinkTargetRule::normalize($value));
    }

    public function test_http_is_only_allowed_when_the_caller_says_so(): void
    {
        $this->assertNull(LinkTargetRule::normalize('http://example.com'));
        $this->assertSame('http://example.com', LinkTargetRule::normalize('http://example.com', allowInsecure: true));
        // The switch widens nothing else
        $this->assertNull(LinkTargetRule::normalize('javascript:alert(1)', allowInsecure: true));
        $this->assertNull(LinkTargetRule::normalize('//evil.example', allowInsecure: true));
    }
}
