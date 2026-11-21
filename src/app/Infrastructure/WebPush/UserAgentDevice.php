<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

/**
 * Names a subscribed device from its user agent (core#803): the browser family and the system,
 * as keys the page translates. Rough on purpose: it only tells people's own devices apart.
 * An iPad with a desktop user agent reads as macOS.
 */
final class UserAgentDevice
{
    /** Checked in order: Edge, Opera and Samsung say «Chrome» too, Chrome says «Safari». */
    private const array BROWSERS = [
        'edge' => '/\bEdg(?:e|A|iOS)?\//',
        'opera' => '/\b(?:OPR|Opera)\//',
        'samsung' => '/\bSamsungBrowser\//',
        'firefox' => '/\b(?:Firefox|FxiOS)\//',
        'chrome' => '/\b(?:Chrome|CriOS)\//',
        'safari' => '/\bVersion\/[\d.]+.*\bSafari\//',
    ];

    /** Android says «Linux», iOS says «like Mac OS X». */
    private const array PLATFORMS = [
        'android' => '/\bAndroid\b/',
        'ios' => '/\b(?:iPhone|iPad|iPod)\b/',
        'chromeos' => '/\bCrOS\b/',
        'windows' => '/\bWindows\b/',
        'macos' => '/\bMacintosh\b/',
        'linux' => '/\bLinux\b/',
    ];

    /**
     * @return array{browser: string, platform: string}
     */
    public static function parse(string $userAgent): array
    {
        return [
            'browser' => self::first(self::BROWSERS, $userAgent),
            'platform' => self::first(self::PLATFORMS, $userAgent),
        ];
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function first(array $patterns, string $userAgent): string
    {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return 'other';
    }
}
