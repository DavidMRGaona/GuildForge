<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use App\Application\Realtime\Services\RealtimeSubscriptionAuthorizerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Config\Repository;

/**
 * Signs private channel subscriptions as the Pusher protocol (spoken by Reverb) expects:
 * `key:HMAC-SHA256(socket_id:channel, secret)`. Only private channels, only active accounts, and
 * only what the channel registry grants; presence and encrypted channels are not offered.
 */
final readonly class ReverbSubscriptionAuthorizer implements RealtimeSubscriptionAuthorizerInterface
{
    private const string PRIVATE_PREFIX = 'private-';

    private const string SOCKET_ID = '/^\d{1,20}\.\d{1,20}$/D';

    public function __construct(
        private RealtimeConfigurationInterface $configuration,
        private RealtimeChannelRegistryInterface $channels,
        private Repository $config,
    ) {}

    public function authorize(?object $user, string $socketId, string $channelName): ?string
    {
        if (! $this->configuration->enabled()
            || ! $user instanceof UserModel
            || $user->anonymized_at !== null
            || $user->trashed()
            || preg_match(self::SOCKET_ID, $socketId) !== 1
            || ! str_starts_with($channelName, self::PRIVATE_PREFIX)
            || str_starts_with($channelName, self::PRIVATE_PREFIX.'encrypted-')) {
            return null;
        }

        if (! $this->channels->authorize($user, substr($channelName, strlen(self::PRIVATE_PREFIX)))) {
            return null;
        }

        $connection = 'broadcasting.connections.'.ReverbRealtimeConfiguration::CONNECTION;
        $key = (string) $this->config->get($connection.'.key');
        $secret = (string) $this->config->get($connection.'.secret');

        return $key.':'.hash_hmac('sha256', $socketId.':'.$channelName, $secret);
    }
}
