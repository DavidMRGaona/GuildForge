# Browser push notifications

Push notifications are optional per installation (core#803). With them, the notices of the bell can also reach the browsers and phones where people turned them on, even with the site closed. Without them, nothing changes: the bell and the mail work as before, and no option shows.

They use the standard Web Push protocol with VAPID keys: no third-party service, no external script. Each browser uses its own push service (Google's for Chrome and Edge on Android, Mozilla's for Firefox, Apple's for Safari, Microsoft's for Edge on Windows); the site only posts encrypted messages to it.

## How it works

```
notice stored in the bell ──▶ queue worker ──POST (encrypted, signed)──▶ browser's push service
                                                                                  │
browser ◀──────────────────────────────────────────── message: {"id": "<notice>"} ┘
   │
   └─ service worker ──GET /avisos/<id>/push (session)──▶ application ──▶ shows the notification
```

1. When the bell stores a notice and the person wants that category pushed, the core queues one message per browser of the account, after the commit.
2. The message carries the notice id only, encrypted for that browser (aes128gcm) and signed with the site's keys.
3. The service worker (`/sw.js`) asks the site for the notice with the person's session. The site checks again that it is theirs and still visible. Without a session, or on any error, it shows a generic «Tienes un aviso nuevo» that opens the notices page.
4. If a tab of the site is visible and focused, nothing is shown: the bell already says it.
5. Clicking opens `/avisos/<id>`, which marks the notice read and goes where it leads, as the bell does.

People turn push on per category and per browser. Nothing is pushed until they do.

## What people see

In **Ajustes de la cuenta**, tab **Avisos** (core#804), only while push is on:

- **Avisos en tus dispositivos**: the button **Recibir avisos en este dispositivo**, which is the only thing that asks the browser for permission. When the browser cannot, it says why instead: no support, notifications blocked for the site (and how to allow them), or, on iPhone and iPad, the step to add the site to the home screen first.
- The account's devices (browser, system, last use: subscribed, refreshed by a page of the site or sent a notice), the current one marked, each with **Quitar**.
- **Mandar un aviso de prueba**, to the current device only, sent at once (3 a minute, 10 an hour per account). It shows a fixed text, even with the site's tab in front, and creates no notice in the bell.
- One more switch per optional category with site notices, **En tus dispositivos**, off for everyone until the person turns it on, and for all their devices at once. It follows the web switch: a notice the bell does not get is never pushed, and neither is a muted subject. Mandatory categories are never pushed. A category may be marked **Sugerido** (`pushSuggested`); **Activar las sugeridas** turns those on in the form, to be saved with the rest.

The guide `core.site.push-notifications` explains it to members.

## Checking it in a real browser

Automated tests cannot reach a push service. After turning push on in a site (or on a development machine, where Chrome accepts `http://localhost`), check by hand:

1. Turn it on with the button, accept the permission and send a test notice: it arrives within seconds.
2. Turn **En tus dispositivos** on for a category, close every tab of the site and make a notice of that category arrive: the notification shows its title; clicking it opens the notice and marks it read.
3. With a tab of the site in front, the same notice shows only in the bell.
4. Remove the device from the list: nothing more arrives, and the button is offered again.

Do it in Chrome (desktop and Android), Firefox and Safari: on macOS, and on an iPhone or iPad with the site on the home screen. Safari expects every push message to show a notification; watch whether a message that shows none (a tab of the site in front) ends the subscription. If it does, the worker has to show the notification every time, with the notice's tag, and close it at once while a tab is in front.

## Environment variables

Push is on only when the three keys are set and valid. Anything else is off, without errors on the site. A missing or invalid key leaves a warning in the log each time a console process starts: the queue worker, every command, and so every `schedule:run`, once a minute. Web requests never log it.

| Variable | Required | Default | What it is |
|---|---|---|---|
| `WEB_PUSH_PUBLIC_KEY` | yes | — | VAPID public key (base64url, 87 characters), sent to the browsers |
| `WEB_PUSH_PRIVATE_KEY` | yes | — | VAPID private key (base64url, 43 characters). Signs every message; keep it secret |
| `WEB_PUSH_SUBJECT` | yes | — | Contact for the push services: `mailto:address` or an `https://` address |
| `WEB_PUSH_EXTRA_HOSTS` | no | — | Push services accepted besides the browsers' own, comma separated (`push.example.org`, `*.example.org`) |

Generate the keys once per installation:

```bash
php artisan web-push:keys
```

It prints the three lines to copy into the environment; it never writes the `.env` itself. **Never share the keys between installations**, and keep them: browsers subscribed with one pair stop receiving messages if it changes, and people have to turn push on again.

## What the server needs

- The queue worker: messages are sent from queued jobs.
- Outgoing HTTPS to the push services: `fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.push.apple.com` and `*.notify.windows.com`. The site only posts to those hosts (and the ones in `WEB_PUSH_EXTRA_HOSTS`), so a forged subscription cannot make it call anything else.
- The scheduler: `web-push:prune` runs daily and forgets the browsers nothing used for 90 days.
- HTTPS for the site itself: browsers only allow service workers and push on secure origins.

## iPhone and iPad

Safari delivers web push only to a site added to the home screen (iOS and iPadOS 16.4 or later). The site's manifest and service worker make that possible (core#802); the account settings explain the step.

## Privacy

- Push services only see an encrypted notice id, never its text.
- The browser's keys are stored encrypted; endpoints and keys are never logged (only the push service's host and the status).
- Only the person's own click subscribes a browser, or moves it to their account. When a page loads it only refreshes the account's own subscription: on a shared computer, another account signing in takes nothing over, and the browser drops a subscription the site does not know for that account.
- An account keeps its 20 most recently used browsers.
- A browser is forgotten when it signs out (also on this device alone), when its push service answers 404 or 410, when its keys cannot encrypt a message, when the account is deactivated or anonymized, and after 90 days without use.
- Nothing is subscribed or sent while an administrator impersonates an account, and no device is removed or tested.
- What each person chose is kept in `user_web_push_preferences` (one row per category turned on). Deactivating an account keeps it, for when it is restored; anonymizing deletes it.

## Turning it on in a site

1. Run `php artisan web-push:keys` and put the three variables in the environment, with `WEB_PUSH_SUBJECT` completed.
2. Restart the application, the queue worker and the scheduler so they read them.
3. Check the log of the worker or of `php artisan about`: no warning about a `WEB_PUSH_*` setting.

To turn it off, remove the variables and restart: the options disappear and nothing is sent. The stored subscriptions stay until they are pruned, so turning it back on with the same keys keeps them.
