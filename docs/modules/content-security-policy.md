# Content Security Policy

The core sends one Content Security Policy for every page (`SecurityHeadersMiddleware`). Modules cannot change it, with one exception: a module that shows content of another service in a frame (a video player, a post) can add the origins of those frames to `frame-src`. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces the registry (the one with core#538). On an older core there is no `frame-src` directive, frames fall back to `default-src 'self'` and a third-party player stays blank.

## Without modules

`frame-src 'self'`. Nothing from another site can be framed until a module asks for it. The site itself can never be framed by others (`frame-ancestors 'none'`, `X-Frame-Options: DENY`); that does not change.

## Adding frame origins

Resolve `App\Application\Security\Services\ContentSecurityPolicySourcesInterface` in `boot()` and register a function that returns the origins:

```php
use App\Application\Security\Services\ContentSecurityPolicySourcesInterface;

// In boot()
$this->callAfterResolving(ContentSecurityPolicySourcesInterface::class, function (ContentSecurityPolicySourcesInterface $sources): void {
    $sources->allowFrames(fn (): array => $this->app->make(MyModuleSettings::class)->enabledPlayerOrigins());
});
```

- The function runs every time the headers are built, so it can follow the module's settings: when an administrator turns a service off, its origin leaves the policy on the next request. Keep it cheap (settings already cached, no HTTP calls).
- Return origins written exactly: `https://`, a host name with a top-level domain, an optional port, lowercase, no trailing slash. For example `https://www.youtube-nocookie.com` or `https://player.example.com:8443`.
- Anything else is left out with a warning in the log, never widened: `http://`, wildcards (`*`, `https://*.example.com`), bare schemes (`https:`, `data:`, `blob:`), keywords (`'self'`, `'unsafe-inline'`), IP addresses, `localhost`, paths, query strings, credentials, spaces, `;` and line breaks. Nothing a module returns can add or change another directive.
- A function that throws adds none of its origins (even the ones it produced before failing); the other modules' origins and the page stay as they are.
- Origins from every module are merged, each once, sorted.

## Frames that respect the reader

Opening `frame-src` lets the browser load the frame; it does not decide when. A module that embeds content of another service should:

- Show its own card first (title, link, an image copied to the site's storage) and load the frame only after a click or with consent to the service's cookie category (`hasCookieConsent()` from `@/contracts/cookieConsent`). The page then makes no request to the service until the reader chooses it.
- Give every `<iframe>` a `sandbox` with only the tokens that service needs, `referrerpolicy="no-referrer"` unless the player refuses to load without a referrer, and a `title`.
- Register only the origins of services that are enabled, and only the player's origin (the host in the iframe's `src`), not the service's main site.
