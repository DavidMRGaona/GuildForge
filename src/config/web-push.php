<?php

declare(strict_types=1);

/*
 * Browser push notifications (core#803), optional per installation: with every key set and valid,
 * notices of the bell can also reach the browsers people turned them on in. Each installation has
 * its own keys (`php artisan web-push:keys`), never shared with another site. See docs/web-push.md.
 */
return [
    // The VAPID key pair, base64url: the public one (87 characters) goes to the browsers, the
    // private one (43 characters) signs every message and never leaves the server
    'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
    'private_key' => env('WEB_PUSH_PRIVATE_KEY'),

    // Who push services contact about this site's messages: `mailto:` or an `https:` address
    'subject' => env('WEB_PUSH_SUBJECT'),

    // Push services accepted besides the browsers' own (WebPushEndpointRule::DEFAULT_HOSTS),
    // comma separated; `*.example.org` for its subdomains
    'extra_hosts' => env('WEB_PUSH_EXTRA_HOSTS', ''),

    // How long a push service keeps a message for a browser that is offline, in seconds
    'ttl' => 86400,
];
