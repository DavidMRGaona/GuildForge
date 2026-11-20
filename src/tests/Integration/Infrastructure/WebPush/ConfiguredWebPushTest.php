<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\WebPush;

use App\Application\WebPush\Services\WebPushConfigurationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

final class ConfiguredWebPushTest extends TestCase
{
    use EnablesWebPush;

    public function test_it_is_on_with_every_setting(): void
    {
        $this->enableWebPush();
        $configuration = app(WebPushConfigurationInterface::class);

        $this->assertTrue($configuration->enabled());
        $this->assertFalse($configuration->isIncomplete());
        $this->assertSame(self::VAPID_PUBLIC_KEY, $configuration->publicKey());
        $this->assertSame('mailto:avisos@example.test', $configuration->vapid()?->subject);
    }

    public function test_it_is_off_and_not_incomplete_without_any_setting(): void
    {
        config(['web-push.public_key' => null, 'web-push.private_key' => null, 'web-push.subject' => null]);
        $configuration = app(WebPushConfigurationInterface::class);

        $this->assertFalse($configuration->enabled());
        $this->assertFalse($configuration->isIncomplete());
        $this->assertNull($configuration->publicKey());
        $this->assertNull($configuration->vapid());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function brokenSettings(): array
    {
        return [
            'no private key' => [['web-push.private_key' => '']],
            'no subject' => [['web-push.subject' => null]],
            'a subject that is no address' => [['web-push.subject' => 'avisos@example.test']],
            'a plain http subject' => [['web-push.subject' => 'http://example.test']],
            'a public key of the wrong size' => [['web-push.public_key' => 'BIiq-ImwLDB8OcUrATp0p_oGpRJ2nrH7cU']],
            'a private key that is no base64url' => [['web-push.private_key' => '1CQ89SCFpoxtZzHmc88YgS-2gW0F9xn_arKDlcfY/+=']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('brokenSettings')]
    public function test_it_is_off_and_incomplete_with_a_missing_or_invalid_setting(array $overrides): void
    {
        $this->enableWebPush($overrides);
        $configuration = app(WebPushConfigurationInterface::class);

        $this->assertFalse($configuration->enabled());
        $this->assertTrue($configuration->isIncomplete());
        $this->assertNull($configuration->publicKey());
    }

    public function test_extra_push_services_reach_the_endpoint_rule(): void
    {
        $this->enableWebPush(['web-push.extra_hosts' => 'push.example.test']);

        $this->assertTrue(app(WebPushConfigurationInterface::class)->endpointRule()->allows('https://push.example.test/abc'));
    }
}
