<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCodes;

use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;

/**
 * A QR code printed on a poster sends people wherever it points, so the core only encodes
 * URLs of the site itself: https, same host and port as app.url, no user info. Plain http is
 * accepted only where the binding allows it (local and testing). Fails closed: a missing or
 * unreadable site URL rejects everything.
 */
final readonly class QrCodeContentPolicy
{
    /** Well inside what a code still scans comfortably from a poster */
    public const int MAX_LENGTH = 1024;

    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(
        private string $siteUrl,
        private bool $allowsHttp,
    ) {}

    /**
     * @throws InvalidQrCodeContentException
     */
    public function assertAllowed(string $url): void
    {
        if ($url === '' || strlen($url) > self::MAX_LENGTH) {
            throw InvalidQrCodeContentException::because(sprintf('the URL must have 1 to %d characters', self::MAX_LENGTH));
        }

        // Whitespace, control characters and backslashes are read differently by parsers and browsers
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            throw InvalidQrCodeContentException::because('the URL contains whitespace, control characters or backslashes');
        }

        $target = $this->origin($url);
        $site = $this->origin($this->siteUrl);

        if ($site === null) {
            throw InvalidQrCodeContentException::because('the site URL (app.url) is not configured');
        }

        if ($target === null) {
            throw InvalidQrCodeContentException::because('the URL must be absolute, with scheme and host and no user info');
        }

        $allowedSchemes = $this->allowsHttp ? ['https', 'http'] : ['https'];
        if (! in_array($target['scheme'], $allowedSchemes, true)) {
            throw InvalidQrCodeContentException::because(sprintf('the URL must use %s', implode(' or ', $allowedSchemes)));
        }

        if ($target['host'] !== $site['host'] || ! $this->samePort($target, $site)) {
            throw InvalidQrCodeContentException::because('the URL must point to this site');
        }
    }

    /**
     * @return array{scheme: string, host: string, port: int|null}|null
     */
    private function origin(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (! isset(self::DEFAULT_PORTS[$scheme])) {
            return null;
        }

        return [
            'scheme' => $scheme,
            'host' => strtolower($parts['host']),
            'port' => $parts['port'] ?? null,
        ];
    }

    /**
     * The site's explicit port must be repeated; without one, the URL may only name its scheme's default.
     *
     * @param  array{scheme: string, host: string, port: int|null}  $target
     * @param  array{scheme: string, host: string, port: int|null}  $site
     */
    private function samePort(array $target, array $site): bool
    {
        $targetPort = $target['port'] ?? self::DEFAULT_PORTS[$target['scheme']];

        if ($site['port'] !== null) {
            return $targetPort === $site['port'];
        }

        return $targetPort === self::DEFAULT_PORTS[$target['scheme']];
    }
}
