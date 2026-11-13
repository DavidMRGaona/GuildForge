<?php

declare(strict_types=1);

namespace App\Application\Realtime\Services;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;

/**
 * The private real-time channels the core and the enabled modules offer, and the one place that
 * decides whether someone may listen to one.
 */
interface RealtimeChannelRegistryInterface
{
    /**
     * Registers a channel class, built from the container the first time it is needed. A class
     * that cannot be built, is not a RealtimeChannelInterface, or whose name is invalid or does not
     * start with its module (`core.` for the core) is left out with a warning.
     *
     * @param  class-string<RealtimeChannelInterface>|class-string  $channelClass
     * @param  string|null  $module  the module that owns it; null for the core
     */
    public function register(string $channelClass, ?string $module = null): void;

    /**
     * @param  array<class-string<RealtimeChannelInterface>>  $channelClasses
     */
    public function registerMany(array $channelClasses, ?string $module = null): void;

    /**
     * Forgets the channels of a module (disabled or uninstalled).
     */
    public function unregisterModule(string $module): void;

    /**
     * Whether the account may listen to a concrete channel (without `private-`). It fails closed:
     * no matching channel, more than one, or a check that throws or says anything but true is a no.
     */
    public function authorize(object $user, string $channel): bool;
}
