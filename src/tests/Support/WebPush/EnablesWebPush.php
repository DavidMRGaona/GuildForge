<?php

declare(strict_types=1);

namespace Tests\Support\WebPush;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;

/**
 * Turns browser push notifications on for a test with keys made for tests only, and stores
 * subscriptions as a browser would send them (a real P-256 key, so the payload can be encrypted).
 */
trait EnablesWebPush
{
    public const string VAPID_PUBLIC_KEY = 'BIiq-ImwLDB8OcUrATp0p_oGpRJ2nrH7cU-S0-GOTpwHm-7cUORDXVuq2Ct_MD2IrHe-FNzW1KAvn7un17hY47s';

    public const string VAPID_PRIVATE_KEY = '1CQ89SCFpoxtZzHmc88YgS-2gW0F9xn_arKDlcfYrZQ';

    public const string BROWSER_P256DH = 'BCRdgiAY4WYUzixZ3XtywS7UdIG6IcXQ_vA-JgDXJo2U8V6BoH_aZXv2OVNVKIIQWpGA2fjDIp0yDLtzv8Lo1xY';

    public const string BROWSER_AUTH = 'i_cwCkOkhMpiueZ_nRSLNw';

    public const string ENDPOINT = 'https://fcm.googleapis.com/fcm/send/example-device-token';

    public const string CHROME_ON_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function enableWebPush(array $overrides = []): void
    {
        config(array_merge([
            'web-push.public_key' => self::VAPID_PUBLIC_KEY,
            'web-push.private_key' => self::VAPID_PRIVATE_KEY,
            'web-push.subject' => 'mailto:avisos@example.test',
            'web-push.extra_hosts' => '',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function subscribe(UserModel $user, string $endpoint = self::ENDPOINT, array $attributes = []): WebPushSubscriptionModel
    {
        return WebPushSubscriptionModel::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => self::BROWSER_P256DH,
            'auth' => self::BROWSER_AUTH,
            'browser' => 'chrome',
            'platform' => 'android',
            ...$attributes,
        ]);
    }
}
