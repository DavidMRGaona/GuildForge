<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\WebPush;

use App\Infrastructure\WebPush\UserAgentDevice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentDeviceTest extends TestCase
{
    /**
     * @param  array{browser: string, platform: string}  $expected
     */
    #[DataProvider('userAgents')]
    public function test_it_names_the_browser_and_the_system(string $userAgent, array $expected): void
    {
        $this->assertSame($expected, UserAgentDevice::parse($userAgent));
    }

    /**
     * @return array<string, array{string, array{browser: string, platform: string}}>
     */
    public static function userAgents(): array
    {
        return [
            'chrome on android' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
                ['browser' => 'chrome', 'platform' => 'android'],
            ],
            'safari on iphone' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1',
                ['browser' => 'safari', 'platform' => 'ios'],
            ],
            'edge on windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
                ['browser' => 'edge', 'platform' => 'windows'],
            ],
            'firefox on linux' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0',
                ['browser' => 'firefox', 'platform' => 'linux'],
            ],
            'safari on a mac' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',
                ['browser' => 'safari', 'platform' => 'macos'],
            ],
            'opera' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 OPR/116.0.0.0',
                ['browser' => 'opera', 'platform' => 'windows'],
            ],
            'samsung internet' => [
                'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/27.0 Chrome/125.0.0.0 Mobile Safari/537.36',
                ['browser' => 'samsung', 'platform' => 'android'],
            ],
            'chrome os' => [
                'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                ['browser' => 'chrome', 'platform' => 'chromeos'],
            ],
            'nothing known' => ['', ['browser' => 'other', 'platform' => 'other']],
        ];
    }
}
