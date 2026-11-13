<?php

declare(strict_types=1);

use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Infrastructure\SiteNotifications\SiteNotificationsRealtimeChannel;

/*
|--------------------------------------------------------------------------
| Private real-time channels of the core (core#493)
|--------------------------------------------------------------------------
|
| Each channel is a RealtimeChannelInterface class whose name starts with `core.`. Modules register
| theirs from their provider (registerRealtimeChannels()), prefixed with the module name. The
| registry checks every subscription on the server and fails closed; messages carry only ids and
| numbers. Loaded by RealtimeServiceProvider.
|
*/

$channels = app(RealtimeChannelRegistryInterface::class);

// The bell: each account listens to its own notices
$channels->register(SiteNotificationsRealtimeChannel::class);
