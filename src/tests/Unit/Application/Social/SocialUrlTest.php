<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Social;

use App\Application\Social\SocialNetwork;
use App\Application\Social\SocialUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialUrlTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function normalizedProvider(): array
    {
        return [
            'adds https' => ['www.instagram.com/example', 'https://www.instagram.com/example'],
            'keeps https' => ['https://x.com/example', 'https://x.com/example'],
            'upgrades http' => ['http://x.com/example', 'https://x.com/example'],
            'trims' => ['  discord.gg/abc  ', 'https://discord.gg/abc'],
            'keeps query and handle' => ['www.tiktok.com/@example?lang=es', 'https://www.tiktok.com/@example?lang=es'],
            'empty' => ['', ''],
            'not a string' => [null, ''],
        ];
    }

    #[DataProvider('normalizedProvider')]
    public function test_normalizes_stored_values(mixed $stored, string $expected): void
    {
        $this->assertSame($expected, SocialUrl::normalize($stored));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,x'],
            'ftp' => ['ftp://example.com/x'],
            'spaces' => ['example.com/a b'],
            'credentials' => ['user:pass@example.com/x'],
            'no dot host' => ['localhost/x'],
            'protocol relative' => ['//example.com'],
            'only scheme' => ['https://'],
            'html' => ['<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('brokenProvider')]
    public function test_broken_values_are_dropped_not_fabricated(string $stored): void
    {
        $this->assertSame('', SocialUrl::normalize($stored));
        $this->assertFalse(SocialUrl::isValid($stored));
    }

    public function test_whatsapp_only_accepts_chat_and_group_hosts(): void
    {
        $this->assertTrue(SocialUrl::isValid('chat.whatsapp.com/AbC123', SocialNetwork::Whatsapp));
        $this->assertTrue(SocialUrl::isValid('wa.me/34600123456', SocialNetwork::Whatsapp));
        $this->assertTrue(SocialUrl::isValid('www.whatsapp.com/channel/abc', SocialNetwork::Whatsapp));
        $this->assertFalse(SocialUrl::isValid('example.com/x', SocialNetwork::Whatsapp));
        $this->assertFalse(SocialUrl::isValid('chat.whatsapp.com.evil.test/x', SocialNetwork::Whatsapp));
        $this->assertTrue(SocialUrl::isValid('example.com/x', SocialNetwork::Youtube));
    }

    public function test_network_keys(): void
    {
        $this->assertSame('social_twitch', SocialNetwork::Twitch->settingKey());
        $this->assertSame('social_whatsapp_visible', SocialNetwork::Whatsapp->visibilityKey());
    }
}
