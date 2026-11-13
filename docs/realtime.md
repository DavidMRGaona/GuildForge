# Real time (WebSockets)

Real time is optional per installation (core#493). With it, pages hear at once that something happened: the bell gets a new notice without waiting for its next question, and modules (the forum, for instance) show new posts as they arrive. Without it, nothing breaks: every page keeps its periodic requests, as before.

It uses [Laravel Reverb](https://laravel.com/docs/reverb), a WebSocket server that runs as one more process of the application (same code, same `.env`).

## How it works

```
browser ──wss://site/app/<key>──▶ proxy ──▶ Reverb process (php artisan reverb:start)
   │                                               ▲
   └─POST /broadcasting/auth──▶ application ───────┘ publishes messages (queue worker)
```

1. A signed-in page opens one WebSocket to the site's own host, path `/app/<key>`. The proxy sends that path to the Reverb process.
2. For each private channel it wants (its own notices, a forum topic), the page asks the application to sign the subscription (`POST /broadcasting/auth`). The application decides on the server and fails closed.
3. When something happens, the application queues a message; the queue worker hands it to Reverb, which passes it to the browsers on that channel. Messages carry only ids and numbers: the page then asks for the content through its usual request, which checks permissions again.

Guests never connect: they have no private channel to listen to.

## Environment variables

Real time is on only when `BROADCAST_CONNECTION=reverb` **and** every required setting is present and valid. Anything else is off, without errors or warnings on the site. A `reverb` connection with a missing or invalid setting turns itself off and leaves one warning in the log of the worker or command that starts (never on every web request).

| Variable | Required | Default | What it is |
|---|---|---|---|
| `BROADCAST_CONNECTION` | yes | `null` | `reverb` turns real time on; `log` or `null` keep it off |
| `REVERB_APP_ID` | yes | — | Application id, any value (for example `guildforge`) |
| `REVERB_APP_KEY` | yes | — | Public key, sent to the browser. A random string |
| `REVERB_APP_SECRET` | yes | — | Signs the subscriptions. A long random string; never share it |
| `REVERB_HOST` | yes | — | Where the **application** reaches Reverb to publish (an internal address: `127.0.0.1`, the name of the Reverb container…) |
| `REVERB_PORT` | no | `8080` | Port of that address |
| `REVERB_SCHEME` | no | `http` | `https` only if Reverb itself serves TLS on that address |
| `REVERB_SERVER_HOST` | no | `0.0.0.0` | Interface the Reverb process listens on |
| `REVERB_SERVER_PORT` | no | `8080` | Port the Reverb process listens on |
| `REVERB_PUBLIC_HOST` | no | host of `APP_URL` | Where **browsers** connect, when it is not the site itself |
| `REVERB_PUBLIC_PORT` | no | port of `APP_URL` (443/80) | Port browsers connect to |
| `REVERB_PUBLIC_SCHEME` | no | scheme of `APP_URL` | `https` → `wss://`, `http` → `ws://` |
| `REVERB_ALLOWED_ORIGINS` | no | host of `APP_URL` | Comma-separated hosts whose pages may open a WebSocket |
| `REVERB_PUBLISH_TIMEOUT` | no | `5` | Seconds the worker waits for Reverb when publishing |

Generate the key and the secret once per installation, for example with `openssl rand -hex 16` and `openssl rand -hex 32`. Each installation needs its own; two sites must never share a secret.

The browser settings are read at runtime (shared Inertia props), so turning real time on or off needs no new build of the front end.

The Content Security Policy adds the WebSocket origin (`wss://host[:port]`) to `connect-src` only while real time is on.

## The process to run

Run the Reverb server next to the web server and the queue worker, with the same code and `.env`:

```bash
php artisan reverb:start
```

- It is a long-running process: keep it under the same supervisor as the queue worker, restarting it when it stops.
- Restart it after each deploy (`php artisan reverb:restart` asks a running server to stop; the supervisor starts it again).
- One Reverb process is enough for one site. Several sites can each run their own; they must not share keys.
- The queue worker must be running: messages are queued (tried once, since a late message is worth nothing; the pages catch up on their own).

## The proxy rule

The proxy in front of the site must send the WebSocket path to the Reverb process, with the upgrade headers:

| Path | Goes to |
|---|---|
| `/app/` (WebSocket, `Upgrade: websocket`) | Reverb (`REVERB_SERVER_HOST:REVERB_SERVER_PORT`) |
| everything else | the application, as now |

Reverb's publishing API (`/apps/…`) is only for the application, through `REVERB_HOST`: there is no need to expose it to the internet.

Example for nginx:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 120s;
}
```

Keep the read timeout above Reverb's ping interval (60 s by default), or idle sockets are cut and the browsers reconnect over and over.

## In the production image

The production image (`Dockerfile.prod`) already runs the process and has the proxy rule:

- On every start, after caching the configuration, the entrypoint asks `php artisan realtime:status`, which applies the same rule as the application (exit `0` when on, `1` when off). When on, supervisord starts `php artisan reverb:start --host=127.0.0.1 --port=8080` as `www-data` and starts it again whenever it stops; when off, the program stays `STOPPED` and never restarts in a loop.
- Its nginx sends `/app/` to `127.0.0.1:8080` with the upgrade headers and a 120 s read timeout. While real time is off that path answers 502 and the pages keep polling. `/apps/…` is not exposed.
- Reverb listens only inside the container, so set `REVERB_HOST=127.0.0.1` and keep `REVERB_PORT` at `8080`: the application publishes to the process next to it. `REVERB_SERVER_HOST` and `REVERB_SERVER_PORT` are not used there (the image fixes them).
- A deploy replaces the container, so Reverb starts again with the new code and settings. To restart it by hand: `php artisan reverb:restart` (it stops within a few seconds and supervisord starts it again) or `supervisorctl restart reverb`; `supervisorctl status reverb` says whether it runs.

## Turning it on in a site

1. Add the variables above to the site's environment (`BROADCAST_CONNECTION=reverb`, app id, key, secret and `REVERB_HOST`; `127.0.0.1` with the production image).
2. Without the production image: start `php artisan reverb:start` as a supervised process and add the proxy rule for `/app/`. The image does both on its own.
3. Deploy. With your own processes, also restart the queue worker so it reads the new settings.
4. Check: signed in, the browser's network panel shows one WebSocket to `/app/<key>` and one `POST /broadcasting/auth` answered `200`. A notice sent to that account raises the bell without reloading.

To turn it off again, set `BROADCAST_CONNECTION=log` (or remove it) and deploy (with your own processes, restart the worker; the Reverb process can be stopped). Everything goes back to periodic requests.

## Local development

Without the variables the local site has no real time, as a production site without them. To try it, add to `src/.env`:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=guildforge
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=reverb
REVERB_PORT=8080
```

and start the optional `reverb` service, which runs `php artisan reverb:start`:

```bash
docker compose --profile realtime up -d reverb
```

The local nginx already sends `/app/` to it (it answers 502 while the service is stopped, and the pages poll). Restart the `queue` service so the worker reads the new settings. To stop: `docker compose stop reverb` and set `BROADCAST_CONNECTION=log` again.
