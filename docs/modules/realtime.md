# Real time

Modules can tell open pages at once that something changed (a new post, a like, a seat taken), over private WebSocket channels the core offers (core#493). Real time is optional per installation ([installation guide](../realtime.md)): a module always keeps its periodic requests and uses the channel only while it is live.

## Rules

- **Only ids and numbers travel.** A message says that something happened («post 42 in discussion X»), never what: no titles, names, bodies or URLs. The page then asks for the content through its usual request, which checks permissions again. A channel granted a moment too long, or a message heard by someone who just lost access, never shows anything. The core enforces it: a payload with anything else is not sent (warning in the log).
- **Private channels only, granted on the server.** Every subscription is checked by the channel's `authorize()`, once per subscription and again after each reconnection. It fails closed: no matching channel, a channel that matches twice, a check that throws or answers anything but `true` refuses it.
- **Names start with the module.** `<module>.<thing>.{id}`; the core's start with `core.`. A channel whose name does not start with its module is never registered.
- **Polling stays.** Without real time, or while the connection is down, the page polls as before. Real time only lets it stop polling while it is live.

## Server: offering a channel

Implement `App\Application\Realtime\Contracts\RealtimeChannelInterface`:

```php
use App\Application\Realtime\Contracts\RealtimeChannelInterface;

final class DiscussionRealtimeChannel implements RealtimeChannelInterface
{
    public function __construct(private readonly ForumAccessServiceInterface $access) {}

    public function name(): string
    {
        return 'forum.discussion.{discussion}';
    }

    public function authorize(object $user, array $parameters): bool
    {
        // The same rule as the page: whoever cannot read the discussion cannot listen
        return $this->access->canRead($user, $parameters['discussion']);
    }
}
```

- `name()`: dot-separated segments; the first is the module name, the others literals (`[a-z0-9-]`) or `{placeholders}` (whole segments). A placeholder takes one id (`[A-Za-z0-9_-]`, up to 64 characters). Up to 150 characters.
- `authorize()`: `$user` is the signed-in account (guests, deactivated and anonymized accounts never get here); `$parameters` the placeholder values by name. Return `true` to grant. Throwing refuses and logs.
- The class is built from the container (constructor injection works), once per request.

Register it in the module's provider:

```php
public function registerRealtimeChannels(): array
{
    return [DiscussionRealtimeChannel::class];
}
```

## Server: sending a message

```php
use App\Application\Realtime\Services\RealtimePublisherInterface;

$this->realtime->publish(
    'forum.discussion.'.$discussion->id,   // concrete channel, no "private-"
    'post.created',                        // exact event name
    ['number' => $post->number],           // ids and numbers only
);
```

- Without real time configured it does nothing: never check first.
- It sends after the current transaction commits (at once outside one; nothing if it rolls back), through the queue, tried once.
- It never throws. An invalid channel or event name, or a payload with content, is dropped with a warning; so is a failure to queue it.
- Event names: lowercase words joined by `.` or `-` (`post.created`, `seat-taken`), up to 64 characters, never `client-…`.
- Payload: an object of up to 20 keys (`camelCase`), each an integer, a finite number, a boolean, `null`, a UUID or ULID, or a list of up to 100 of those.

## Browser: listening

`useRealtimeChannel` is part of the public frontend API (`@/composables/useRealtimeChannel`):

```ts
import { watch } from 'vue';
import { useRealtimeChannel } from '@/composables/useRealtimeChannel';

const { live, status } = useRealtimeChannel(
    () => `forum.discussion.${props.discussionId}`,
    {
        'post.created': ({ number }) => fetchNewPosts(Number(number)),
    }
);

// Poll only while it is not live; catch up once each time it comes back
watch(live, (isLive, wasLive) => {
    if (isLive) {
        stopPolling();
        if (wasLive === false) {
            void fetchNewPosts();
        }
    } else {
        startPolling();
    }
}, { immediate: true });
```

- One WebSocket per page, shared by every subscription; two components on the same channel subscribe once. The channel is left when the last one stops (component unmounted or effect scope stopped). A `null` channel name listens to nothing.
- `status`: `off` (no real time on this page, or no channel), `connecting`, `live` (messages arrive), `offline` (connection lost or channel refused). `live` is `status === 'live'`.
- It reconnects on its own after a drop, and tries again as soon as the browser is back online or the tab visible. Messages sent while it was not live are lost: when `live` turns true again, ask once for what is new.
- Without settings (real time off, nobody signed in) it never connects and loads nothing: the WebSocket client is only downloaded by pages that have real time.
- Logging in as someone else starts a new connection, so channels granted to the previous account are never kept.

### Client events («typing…»)

`whisper(event, data)` sends a client event to the others on the channel; `onWhisper(event, listener)` listens to theirs and returns what stops it. They go from browser to browser without the server checking them: send only the sender's own id, never trust what arrives (show a «someone is typing» hint, nothing more), and limit how often you send. `whisper()` returns `false` when the channel is not live.

## Tests

- Server: `Tests\Support\Realtime\EnablesRealtime` turns real time on with example settings (`enableRealtime()`); `expectedRealtimeAuth()` gives the signature a granted subscription returns. Post to `route('realtime.auth')` with `socket_id` and `channel_name` (`private-…`) to test a channel's check, and fake `App\Infrastructure\Realtime\RealtimeBroadcast` to assert what was published.
- Browser: `setRealtimeClientFactory()` and `resetRealtimeConnection()` from `@/utils/realtime/connection` swap the WebSocket client for a fake one (see the core's `useRealtimeChannel.spec.ts`). They are not part of the public API: a module's specs mock `@/composables/useRealtimeChannel` instead.
