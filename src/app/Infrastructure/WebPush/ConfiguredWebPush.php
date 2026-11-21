<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\WebPush\DTOs\VapidKeysDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Domain\WebPush\WebPushEndpointRule;

/**
 * Push settings from `config/web-push.php` (core#803). The keys must form one P-256 pair (the
 * public one an uncompressed point on the curve, the private one its 32-byte scalar) and the
 * subject must be a `mailto:` or `https:` address, as push services require.
 */
final readonly class ConfiguredWebPush implements WebPushConfigurationInterface
{
    private const string BASE64URL = '/^[A-Za-z0-9_-]+\z/';

    public function enabled(): bool
    {
        return $this->vapid() instanceof VapidKeysDTO;
    }

    public function publicKey(): ?string
    {
        return $this->vapid()?->publicKey;
    }

    public function vapid(): ?VapidKeysDTO
    {
        $publicKey = $this->setting('public_key');
        $privateKey = $this->setting('private_key');
        $subject = $this->setting('subject');

        if (! $this->isKeyPair($privateKey, $publicKey) || ! $this->isSubject($subject)) {
            return null;
        }

        return new VapidKeysDTO(subject: $subject, publicKey: $publicKey, privateKey: $privateKey);
    }

    public function isIncomplete(): bool
    {
        $anySet = $this->setting('public_key') !== '' || $this->setting('private_key') !== '' || $this->setting('subject') !== '';

        return $anySet && ! $this->enabled();
    }

    public function endpointRule(): WebPushEndpointRule
    {
        return new WebPushEndpointRule(WebPushEndpointRule::parseHosts($this->setting('extra_hosts')));
    }

    private function setting(string $key): string
    {
        $value = config('web-push.'.$key);

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Two keys of one P-256 pair: the right sizes are not enough, a public key off the curve or one
     * that does not match the private key would fail every message.
     */
    private function isKeyPair(#[\SensitiveParameter] string $privateKey, string $publicKey): bool
    {
        $public = $this->decode($publicKey);
        $private = $this->decode($privateKey);

        return $public !== null && $private !== null && P256Key::pairs($private, $public);
    }

    private function isSubject(string $subject): bool
    {
        if (str_starts_with($subject, 'mailto:')) {
            return filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        return str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL) !== false;
    }

    private function decode(string $value): ?string
    {
        if (preg_match(self::BASE64URL, $value) !== 1) {
            return null;
        }

        $bytes = base64_decode(strtr($value, '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }
}
