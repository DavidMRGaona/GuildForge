<?php

declare(strict_types=1);

namespace App\Application\Realtime\Contracts;

/**
 * A private real-time channel (core#493): who may listen to it is decided here, on the server,
 * each time a browser subscribes. Modules list theirs in registerRealtimeChannels(); the core's
 * are registered in routes/channels.php.
 *
 * Messages on it carry only ids and numbers (RealtimeMessageRule): a page that hears one asks
 * for the content through its usual request, which checks permissions again.
 */
interface RealtimeChannelInterface
{
    /**
     * The channel name without `private-`, e.g. `forum.discussion.{discussion}`. A module's starts
     * with the module name and a dot, the core's with `core.`; any other is never registered.
     * Placeholders are whole segments and take one id each.
     */
    public function name(): string;

    /**
     * Whether the person may listen. Only true grants; when this throws, the subscription is
     * refused and the error logged, so the check can stay simple and fail closed.
     *
     * @param  object  $user  the signed-in account (guests never reach a private channel)
     * @param  array<string, string>  $parameters  the values of the placeholders, by name
     */
    public function authorize(object $user, array $parameters): bool;
}
