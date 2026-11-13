<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNotifications;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Each account's own channel for the bell (core#491, core#493): a message without content when a
 * notice is delivered, after which the bell asks for its counter. Only the account itself listens.
 */
final class SiteNotificationsRealtimeChannel implements RealtimeChannelInterface
{
    public const string NAME = 'core.site-notifications.{user}';

    public const string DELIVERED = 'site-notification.delivered';

    public static function for(string $userId): string
    {
        return 'core.site-notifications.'.$userId;
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function authorize(object $user, array $parameters): bool
    {
        return $user instanceof UserModel
            && isset($parameters['user'])
            && hash_equals((string) $user->id, $parameters['user']);
    }
}
