# API contracts

> Verified on 2026-10-13 against core 3.5.1 (`php artisan route:list`, `src/routes`, `src/app/Http`) and the routes and controllers of the modules (`src/modules/<name>/routes`, `src/modules/<name>/src/Http`).

GuildForge is an Inertia application: most public URLs return an Inertia page (HTML on the first visit, JSON page objects on Inertia visits), and forms answer with redirects plus flash messages. Only a few endpoints return plain JSON. URLs are in Spanish and never carry accents.

---

## Table of contents

1. [Conventions](#1-conventions)
2. [Core pages](#2-core-pages)
3. [Authentication routes](#3-authentication-routes)
4. [JSON endpoints and webhooks](#4-json-endpoints-and-webhooks)
5. [Framework and admin routes](#5-framework-and-admin-routes)
6. [Module routes](#6-module-routes)
7. [Request validation](#7-request-validation)
8. [HTTP resources](#8-http-resources)
9. [Middleware](#9-middleware)
10. [Appendices](#10-appendices)

---

## 1. Conventions

- **Middleware**: every route in `routes/web.php` and in the modules' `routes/web.php` runs in the `web` group (session, CSRF, Inertia, security headers, maintenance check); a module's `routes/api.php` is mounted under `/api` with the `api` group (none defines routes today). `routes/api.php` is mounted under `/api` with the `api` group: stateless, no session and no CSRF.
- **`seo` prop**: every Inertia page rendered by a core or module controller receives `seo` (`SeoMetadata::toArray()`, TypeScript `SeoProps`: `scope`, `title`, `fullTitle`, `description`, `canonicalUrl`, `type`, `siteName`, `locale`, `image`, `robots`, `extraMeta`, `structuredData`). It overrides the site-wide `seo` shared prop. The page tables below list the other props only.
- **Flash messages**: form endpoints redirect back (or to a named route) with `success`, `error`, `warning` or `info` in the session, exposed as the shared `flash` prop. Validation errors come back as Inertia `errors`.
- **Authentication**: routes marked "Yes" use the `auth` middleware. A browser request without a session is redirected to `/iniciar-sesion`; a request that expects JSON gets `401 {"message": "Unauthenticated."}`.
- **Dates** are ISO 8601 (`format('c')`, e.g. `2026-03-21T14:30:00+01:00`).
- **Pagination**: paginated props use the shape in [§10.1](#101-paginated-response).

## 2. Core pages

| Method | URL | Controller | Response | Auth | Name |
|--------|-----|------------|----------|------|------|
| `GET` | `/` | `HomeController` (invokable) | Inertia `Home` | No | `home` |
| `GET` | `/nosotros` | `AboutController` (invokable) | Inertia `About` | No | `about` |
| `POST` | `/contacto` | `ContactController` (invokable), `throttle:contact` | Redirect back with flash | No | `contact.store` |
| `GET` | `/calendario` | `CalendarPageController` (invokable) | Inertia `Calendar/Index` | No | `calendar` |
| `GET` | `/eventos` | `EventController@index` | Inertia `Events/Index` | No | `events.index` |
| `GET` | `/eventos/calendario` | `Api\CalendarController@index` | JSON | No | `events.calendar` |
| `GET` | `/eventos/{slug}` | `EventController@show` | Inertia `Events/Show` | No | `events.show` |
| `GET` | `/articulos` | `ArticleController@index` | Inertia `Articles/Index` | No | `articles.index` |
| `GET` | `/articulos/{slug}` | `ArticleController@show` | Inertia `Articles/Show` | No | `articles.show` |
| `GET` | `/galeria` | `GalleryController@index` | Inertia `Gallery/Index` | No | `galleries.index` |
| `GET` | `/galeria/{slug}` | `GalleryController@show` | Inertia `Gallery/Show` | No | `galleries.show` |
| `GET` | `/buscar` | `SearchController` (invokable) | Inertia `Search/Index` | No | `search` |
| `GET` | `/sitemap.xml` | `SitemapController` (invokable) | XML (`application/xml`, `Cache-Control: public, max-age=3600`) | No | `sitemap` |
| `GET` | `/{slug}` | `LegalPageController@show` | Inertia `Legal/Show` | No | `legal.show` |

`/{slug}` only matches `politica-de-privacidad`, `aviso-legal`, `politica-de-cookies` and `terminos-y-condiciones`; it answers 404 when the page has no published content. Event, article and gallery detail pages answer 404 for an unknown or unpublished slug (core pages do not follow old slugs; see `slug_redirects` in the data models).

### Page props

| Page | Props |
|------|-------|
| `Home` | `heroSlides` (`HeroSlideResource[]`, active slides), `events` (`EventResource[]`: the next 3 upcoming events, or the 3 most recent past events when none is upcoming), `eventsArePast` (boolean), `latestArticles` (`ArticleResource[]`, 3), `featuredGallery` (`GalleryWithPhotosResource` with up to 6 photos, or `null`) |
| `About` | the keys of `AboutPageResponseDTO::toArray()`: `guildName`, `aboutHistory`, `contactEmail`, `contactPhone`, `contactAddress`, `aboutHeroImage`, `aboutTagline`, `activities` (`{icon, title, description}[]`), `joinSteps` (`{title, description}[]`), `socialFacebook`, `socialInstagram`, `socialTwitter`, `socialDiscord`, `socialTiktok`, `socialBluesky`, `socialTelegram`, `location` (`{name, address, lat, lng, zoom}`) |
| `Calendar/Index` | only `seo`; the page loads its entries from `/eventos/calendario` |
| `Events/Index` | `events` (paginated `EventResource`, 12 per page), `tags` (`TagResource[]` that apply to events), `currentTags` (`string[]`) |
| `Events/Show` | `event` (`EventResource`) |
| `Articles/Index` | `articles` (paginated `ArticleResource`, 12 per page), `tags`, `currentTags` |
| `Articles/Show` | `article` (`ArticleResource`) |
| `Gallery/Index` | `galleries` (paginated `GalleryResource`, 12 per page), `tags`, `currentTags` |
| `Gallery/Show` | `gallery` (`GalleryWithPhotosResource`) |
| `Search/Index` | `query` (string), `events` (`EventResource[]`, up to 12), `articles` (`ArticleResource[]`, up to 12), `error` (`'minChars'` when the query is shorter than 2 characters, else `null`); an empty query returns empty lists |
| `Legal/Show` | `title`, `content` (HTML), `lastUpdated` (ISO 8601 or `null`) |

Listing query parameters (`/eventos`, `/articulos`, `/galeria`): `page` (integer, default 1) and `tags` (comma-separated tag slugs).

### Inertia shared data (every page)

`HandleInertiaRequests::share()` adds these props to every Inertia response (it is skipped for `admin/*` and Livewire requests). Everything except `appName` and `appDescription` is a closure, resolved only when the response is built; each resolver logs and falls back to safe defaults if a service fails.

```typescript
{
  errors: Record<string, string>;          // from Inertia's base share()
  appName: string;
  appDescription: string;
  siteLogoLight: string;                   // defaults to /images/defaults/logo-light.png
  siteLogoDark: string;                    // defaults to /images/defaults/logo-dark.png
  favicons: { light: string | null; dark: string | null };
  theme: {
    cssVariables: string;
    darkModeDefault: boolean;
    darkModeToggleVisible: boolean;
    fontHeading: string;
    fontBody: string;
  };
  auth: {
    user: {
      id: string;
      name: string;
      displayName: string | null;
      email: string;
      avatarPublicId: string | null;
      role: string;
      emailVerified: boolean;
      createdAt: string;
      roles: string[];
      permissions: string[];
    } | null;
  };
  authSettings: {
    registrationEnabled: boolean;
    loginEnabled: boolean;
    emailVerificationRequired: boolean;
  };
  flash: {
    success: string | null;
    error: string | null;
    warning: string | null;
    info: string | null;
  };
  moduleSlots: Record<string, Array<Record<string, unknown>>>;
  modulePages: Record<string, string>;
  moduleTranslations: Record<string, Record<string, Record<string, unknown>>>;
  cloudinary: { cloudName: string; prefix: string };   // runtime Cloudinary config read by the frontend
  navigation: {
    header: Array<Record<string, unknown>>;
    footer: Array<Record<string, unknown>>;
  };
  socialLinks: {
    facebook: string;
    instagram: string;
    twitter: string;
    discord: string;
    tiktok: string;
    bluesky: string;
    telegram: string;
  };
  seo: SeoProps;                           // site defaults (SeoMetadataFactoryInterface::forSite()); a page's own seo prop replaces it
}
```

---

## 3. Authentication routes

### Guest routes (`guest` middleware: signed-in users are redirected away)

| Method | URL | Controller | Extra middleware | Response | Name |
|--------|-----|------------|------------------|----------|------|
| `GET` | `/registro` | `RegisterController@create` | `registration.enabled` | Inertia `Auth/Register` | `register` |
| `POST` | `/registro` | `RegisterController@store` | `registration.enabled`, `throttle:5,1` | Redirect to the intended URL or `/` with `success` | -- |
| `GET` | `/iniciar-sesion` | `LoginController@create` | `login.enabled` | Inertia `Auth/Login` | `login` |
| `POST` | `/iniciar-sesion` | `LoginController@store` | `login.enabled`, `throttle:login` | Redirect to the intended URL or `/`; wrong credentials return an `email` validation error | -- |
| `GET` | `/olvide-contrasena` | `ForgotPasswordController@create` | -- | Inertia `Auth/ForgotPassword` | `password.request` |
| `POST` | `/olvide-contrasena` | `ForgotPasswordController@store` | `throttle:5,1` | Redirect back with `success` | `password.email` |
| `GET` | `/restablecer-contrasena/{token}` | `ResetPasswordController@create` | -- | Inertia `Auth/ResetPassword` | `password.reset` |
| `POST` | `/restablecer-contrasena` | `ResetPasswordController@store` | -- | Redirect to `/iniciar-sesion` with `success` | `password.update` |

`registration.enabled` and `login.enabled` redirect to `/` when the site settings disable registration or login.

### Authenticated routes (`auth` middleware)

| Method | URL | Controller | Extra middleware | Response | Name |
|--------|-----|------------|------------------|----------|------|
| `POST` | `/cerrar-sesion` | `LoginController@destroy` | -- | Redirect to `/` | `logout` |
| `GET` | `/verificar-email` | `EmailVerificationController@notice` | -- | Inertia `Auth/VerifyEmail` | `verification.notice` |
| `GET` | `/verificar-email/{id}/{hash}` | `EmailVerificationController@verify` | `signed` | Redirect to `/` (`success`) or to `/verificar-email` (`error`) | `verification.verify` |
| `POST` | `/verificar-email/reenviar` | `EmailVerificationController@resend` | `throttle:6,1` | Redirect back with `success` (to `/` when the email is already verified) | `verification.send` |
| `GET` | `/verificar-email-pendiente/{id}/{hash}` | `EmailVerificationController@verifyPendingEmail` | `signed` | Redirect to `/perfil` with `success` or `error` | `verification.pending-email` |
| `GET` | `/perfil` | `ProfileController@show` | -- | Inertia `Profile/Show` | `profile.show` |
| `POST` | `/perfil` | `ProfileController@update` | -- | Redirect back with `success` | `profile.update` |
| `PUT` | `/perfil/contrasena` | `ProfileController@changePassword` | -- | Redirect back with `success`, or `error` when the current password is wrong | `profile.password` |

### Page props

| Page | Props |
|------|-------|
| `Auth/Login`, `Auth/Register`, `Auth/ForgotPassword`, `Auth/VerifyEmail` | only `seo` |
| `Auth/ResetPassword` | `token`, `email` (from the `email` query parameter) |
| `Profile/Show` | `user` (`UserResponseDTO`: `id`, `name`, `displayName`, `email`, `pendingEmail`, `avatarPublicId`, `role`, `emailVerified`, `createdAt`) |

---

## 4. JSON endpoints and webhooks

### `GET /eventos/calendario`

Public JSON endpoint in `routes/web.php` (`web` middleware). `CalendarAggregatorService` merges the entries of every calendar source: core events (`EventCalendarSource`) plus the sources modules register through `registerCalendarSources()` (today, game-tables registers `GameTableCalendarSource`). Entries are sorted by `start`; a source that fails is logged and skipped.

**Query parameters** (`CalendarRequest`): `start` (required, date), `end` (required, date).

**Response**: `200`, a JSON array of `CalendarEntryResource` (see [§8.1](#calendarentryresource)).

### `POST /api/webhooks/ses`

Amazon SES notifications delivered by SNS. Route name `api.webhooks.ses`, `api` middleware, no session or CSRF; the request is authenticated by the SNS signature (`SnsMessageValidatorInterface`).

| SNS `Type` | Behavior | Response |
|------------|----------|----------|
| `SubscriptionConfirmation` | Calls `SubscribeURL` to confirm the subscription | `200 {"status": "confirmed"}` |
| `Notification` with `notificationType: Bounce` | Marks the `email_logs` row with that `messageId` as `bounced`, increments today's `bounces_count`, dispatches `SesBounceReceived` | `200 {"status": "ok"}` |
| `Notification` with `notificationType: Complaint` | Marks the row as `complained`, increments `complaints_count`, dispatches `SesComplaintReceived` | `200 {"status": "ok"}` |
| Any other type or notification | Ignored | `200 {"status": "ok"}` |

Errors: `400 {"error": "Invalid payload"}` when the body has no `Type`; `403 {"error": "Invalid signature"}` when the signature does not validate.

---

## 5. Framework and admin routes

| Method | URL | Source | Purpose |
|--------|-----|--------|---------|
| `GET` | `/up` | Laravel health route (`bootstrap/app.php`, `health: '/up'`) | Health check for the deployment platform; outside the `web` group, so maintenance mode never answers it with 503 |
| `POST` | `/resend/webhook` | `resend/resend-laravel` package (`resend.webhook`) | Resend webhook receiver |
| `GET`, `PUT` | `/storage/{path}` | Laravel local disk serving (`storage.local`, `storage.local.upload`) | Serves and receives files of the local disk |
| `GET` | `/_inertia/devtools/entries`, `/_inertia/devtools/entries/{id}` | `inertiajs/inertia-laravel` devtools | Development tooling; registered when `INERTIA_DEVTOOLS_ENABLED` is on or, when unset, in the `local` environment |
| various | `/admin/*` | Filament panel | Admin panel (pages, resources, module pages) |
| various | `/livewire-{hash}/*`, `/filament/exports/*`, `/filament/imports/*` | Livewire and Filament | Livewire updates, uploads and assets; Filament export and import downloads |

---

## 6. Module routes

Module routes exist only while the module is enabled. All of them are in `routes/web.php` and run in the `web` group. Modules without public routes: **channel-notifications** (its `routes/web.php` and `routes/api.php` define empty groups) and **memberships** (admin panel only).

### 6.1. announcements

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/anuncios` | `AnnouncementController@index` | No | JSON | `announcements.index` |
| `GET` | `/anuncios/{id}` | `AnnouncementController@show` | No | JSON | `announcements.show` |

- `{id}` must be a UUID.
- `index` returns `{"data": Announcement[]}` with the active announcements inside their `starts_at`/`ends_at` window that the visitor may see (`visibility`), highest `priority` first.
- `show` returns `{"data": Announcement}`, or `404 {"message": ...}` when it does not exist or the visitor may not see it.
- `Announcement` (`AnnouncementResponseDTO::toArray()`, snake_case): `id`, `title`, `content`, `visibility`, `position`, `priority`, `background_color`, `text_color`, `starts_at`, `ends_at`, `is_active`, `is_dismissible`, `created_at`, `updated_at`.

### 6.2. cookie-consent

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/consentimiento-cookies/categorias` | `ConsentApiController@categories` | No | JSON | `cookie-consent.categories` |
| `GET` | `/consentimiento-cookies/configuracion` | `ConsentApiController@config` | No | JSON | `cookie-consent.config` |
| `POST` | `/consentimiento-cookies/consentir` | `ConsentApiController@store` | No | JSON (`201`) | `cookie-consent.store` |

- `categories`: `{"data": Category[]}`, `Category` = `id`, `name`, `slug`, `description`, `isRequired`, `sortOrder`, `consentModeKeys`, `cookies` (`{id, name, provider, domain, purpose, type, duration}[]`).
- `config`: `{"data": BannerConfig}`, `BannerConfig` = `position`, `layout`, `theme`, `colors`, `showRejectAll`, `showSettingsButton`, `validityDays`, `reconsentOnChange`, `configVersion`, `texts`, `gtmEnabled`, `gtmContainerId`, `consentModeEnabled`.
- `store`: `201 {"data": Consent, "message": "Consent saved successfully"}`, `Consent` = `id`, `visitorId`, `userId`, `preferences`, `consentedCategories`, `configVersion`, `consentMethod`, `consentedAt`, `expiresAt`, `isValid`. An invalid consent answers `422 {"error": ...}`. The IP is stored hashed; the user id is recorded when signed in.
- The module also appends `InjectCookieScripts` to the `web` group, which injects into HTML responses the scripts of the categories accepted in the visitor's consent cookie.

### 6.3. event-registrations

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/eventos/{eventId}/inscripcion/estado` | `EventRegistrationController@status` | No | JSON | `event-registrations.status` |
| `GET` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@show` | Yes | JSON | `event-registrations.show` |
| `POST` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@store` | Yes | JSON (`201`) | `event-registrations.store` |
| `DELETE` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@destroy` | Yes | JSON | `event-registrations.destroy` |
| `GET` | `/mis-inscripciones` | `EventRegistrationController@myRegistrations` | Yes | JSON | `event-registrations.my-registrations` |

- `{eventId}` must be a UUID. All responses use snake_case keys.
- `status`: `{"data": {...}}` with the event configuration (`event_id`, `registration_enabled`, `max_participants`, `waiting_list_enabled`, `max_waiting_list`, `registration_opens_at`, `registration_closes_at`, `cancellation_deadline`, `requires_confirmation`, `requires_payment`, `members_only`, `custom_fields`, `confirmation_message`) plus `current_participants`, `current_waiting_list`, `available_spots`, `is_open`, `is_full`.
- `show`: `{"data": Registration | null}` for the current user.
- `store`: `201 {"data": Registration, "message": ...}`. Errors: `422 {"message", "error"}` when registration is closed, the event is full or the user is already registered; `403 {"message", "error"}` when the event is for members only and the user is not a member.
- `destroy`: `{"message": ...}`; `404` when there is no registration, `422` when it can no longer be cancelled.
- `my-registrations`: `{"data": Registration[]}`.
- `Registration` (`EventRegistrationResponseDTO`): `id`, `event_id`, `user_id`, `state`, `state_label`, `state_color`, `position`, `form_data`, `notes`, `admin_notes`, `confirmed_at`, `cancelled_at`, `created_at`, `updated_at`.

### 6.4. game-tables

#### Game tables (`/mesas`)

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/mesas` | `GameTableController@index` | No | Inertia `GameTables/Index` | `gametables.index` |
| `GET` | `/mesas/calendario` | `GameTableController@calendar` | No | Inertia `GameTables/Calendar` | `gametables.calendar` |
| `GET` | `/mesas/cancelar/{token}` | `RegistrationController@showCancelConfirmation` | No | Inertia `GameTables/CancelRegistration` | `gametables.cancel-confirmation` |
| `DELETE` | `/mesas/cancelar/{token}` | `RegistrationController@cancelByToken` | No | Redirect to `/mesas` | `gametables.cancel-by-token` |
| `GET` | `/mesas/crear` | `FrontendGameTableController@create` | Yes | Inertia `GameTables/Create` or `GameTables/CreateNotEligible` | `gametables.create` |
| `POST` | `/mesas/crear` | `FrontendGameTableController@store` | Yes | Redirect to `/mesas/mis-mesas` | `gametables.store` |
| `GET` | `/mesas/mis-mesas` | `FrontendGameTableController@myTables` | Yes | Inertia `GameTables/MyTables` | `gametables.my-tables` |
| `GET` | `/mesas/mis-mesas/{gameTable}/editar` | `FrontendGameTableController@edit` | Yes (policy `update`) | Inertia `GameTables/Edit` | `gametables.edit` |
| `PUT` | `/mesas/mis-mesas/{gameTable}` | `FrontendGameTableController@update` | Yes (policy `update`) | Redirect | `gametables.update` |
| `POST` | `/mesas/mis-mesas/{gameTable}/enviar-revision` | `FrontendGameTableController@submitForReview` | Yes (policy `submitForReview`) | Redirect | `gametables.submit-review` |
| `DELETE` | `/mesas/mis-mesas/{gameTable}` | `FrontendGameTableController@destroy` | Yes (policy `delete`) | Redirect | `gametables.destroy` |
| `POST` | `/mesas/{gameTable}/inscripcion-invitado` | `RegistrationController@registerGuest` | No | Redirect back with flash | `gametables.register-guest` |
| `POST` | `/mesas/{gameTable}/inscripcion` | `RegistrationController@register` | Yes | Redirect back with flash | `gametables.register` |
| `DELETE` | `/mesas/{gameTable}/inscripcion` | `RegistrationController@cancel` | Yes | Redirect back with flash | `gametables.cancel` |
| `GET` | `/mesas/{slug}` | `GameTableController@show` | No | Inertia `GameTables/Show` | `gametables.show` |

- `{gameTable}` is the table's UUID (route model binding on `GameTableModel`); a failed policy check answers `403`.
- `/mesas/{slug}` (`[a-z0-9-]+`): an old slug (from `slug_redirects`) or the table's UUID answers `301` to the current slug; otherwise `404`.
- Registration errors (registration closed, table full, already registered, members only, minimum age) come back as an `error` flash, not as HTTP errors.

**Query parameters**: `/mesas` accepts `page`, `systems` (game system ids, comma-separated or array), `format`, `status`, `event` (event slug) and `campaign` (campaign id). `/mesas/calendario` accepts `month` (any date Carbon can parse, e.g. `2026-03`; defaults to the current month). `/mesas/crear` accepts `event` (event slug) to create a table for that event.

| Page | Props |
|------|-------|
| `GameTables/Index` | `tables` (paginated `GameTableListResource`, 12 per page), `gameSystems`, `events`, `currentFilters` (`systems`, `format`, `status`, `event`, `campaign`) |
| `GameTables/Calendar` | `tables` (`GameTableListResource[]` for the month), `gameSystems`, `currentMonth` (`YYYY-MM`) |
| `GameTables/Show` | `table` (`GameTableResource`), `eligibility` (signed-in users, else `null`), `userRegistration` (`id`, `gameTableId`, `userId`, `userName`, `role`, `roleLabel`, `roleColor`, `status`, `statusLabel`, `statusColor`, `waitingListPosition`, `notes`, `confirmedAt`, `createdAt`, or `null`) |
| `GameTables/CancelRegistration` | `participant`, `gameTable` (`id`, `title`, `startsAt`), `token`, `canCancel` |
| `GameTables/Create` | `formData` (options for the form), `eligibility`, `eventContext` (or `null`), `currentUser` (`id`, `name`, `email`) |
| `GameTables/CreateNotEligible` | `reason`, `canCreateAt` (ISO 8601 or `null`) |
| `GameTables/MyTables` | `tables` (the user's frontend-created tables, `GameTableResponseDTO::toArray()`), `canCreate` |
| `GameTables/Edit` | `table`, `formData`, `eventContext`, `currentUser` |

#### Campaigns (`/campanas`)

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/campanas` | `CampaignController@index` | No | Inertia `Campaigns/Index` | `campaigns.index` |
| `GET` | `/campanas/crear` | `FrontendCampaignController@create` | Yes | Inertia `Campaigns/Create` or `Campaigns/CreateNotEligible` | `campaigns.frontend-create` |
| `POST` | `/campanas/crear` | `FrontendCampaignController@store` | Yes | Redirect to `/campanas/mis-campanas` | `campaigns.frontend-store` |
| `GET` | `/campanas/mis-campanas` | `FrontendCampaignController@myCampaigns` | Yes | Inertia `Campaigns/MyCampaigns` | `campaigns.my-campaigns` |
| `GET` | `/campanas/mis-campanas/{id}/editar` | `FrontendCampaignController@edit` | Yes | Inertia `Campaigns/Edit` (`404` unless it is one of the user's drafts) | `campaigns.frontend-edit` |
| `PUT` | `/campanas/mis-campanas/{id}` | `FrontendCampaignController@update` | Yes | Redirect to `/campanas/mis-campanas` | `campaigns.frontend-update` |
| `POST` | `/campanas/mis-campanas/{id}/enviar-revision` | `FrontendCampaignController@submitForReview` | Yes | Redirect to `/campanas/mis-campanas` | `campaigns.submit-review` |
| `DELETE` | `/campanas/mis-campanas/{id}` | `FrontendCampaignController@destroy` | Yes | Redirect to `/campanas/mis-campanas` | `campaigns.frontend-destroy` |
| `GET` | `/campanas/{slug}` | `CampaignController@show` | No | Inertia `Campaigns/Show` | `campaigns.show` |

- `/campanas/{slug}` follows old slugs and UUIDs with a `301`, like `/mesas/{slug}`.
- `/campanas` accepts `page`, `systems` and `status`.

| Page | Props |
|------|-------|
| `Campaigns/Index` | `campaigns` (paginated `CampaignResource`, 12 per page), `gameSystems`, `currentFilters` (`systems`, `status`) |
| `Campaigns/Show` | `campaign` (`CampaignResource`) |
| `Campaigns/Create` | `formData`, `eligibility` |
| `Campaigns/CreateNotEligible` | `reason`, `canCreateAt` |
| `Campaigns/MyCampaigns` | `campaigns`, `canCreate` |
| `Campaigns/Edit` | `campaign`, `formData` |

### 6.5. tournaments

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/torneos` | `TournamentListController@index` | No | Inertia `Tournaments/Index` | `tournaments.index` |
| `GET` | `/torneos/cancelar/{token}` | `GuestCancellationController@show` | No | Inertia `Tournaments/CancelRegistration` | `tournaments.cancel-confirmation` |
| `DELETE` | `/torneos/cancelar/{token}` | `GuestCancellationController@destroy` | No | Redirect to `/torneos` | `tournaments.cancel-by-token` |
| `GET` | `/torneos/mis-torneos` | `MyTournamentsController` (invokable) | Yes | Inertia `Tournaments/MyTournaments` | `tournaments.my-tournaments` |
| `GET` | `/torneos/{slug}` | `TournamentController@show` | No | Inertia `Tournaments/Show` | `tournaments.show` |
| `GET` | `/torneos/{slug}/clasificacion` | `TournamentController@standings` | No | Inertia `Tournaments/Standings` | `tournaments.standings` |
| `GET` | `/torneos/{slug}/rondas` | `TournamentController@rounds` | No | Inertia `Tournaments/Rounds` | `tournaments.rounds` |
| `GET` | `/torneos/{slug}/check-in` | `TournamentCheckInController@show` | No | Inertia `Tournaments/CheckIn` | `tournaments.check-in.show` |
| `POST` | `/torneos/{slug}/check-in` | `TournamentCheckInController@store` | No | Redirect | `tournaments.check-in.store` |
| `GET` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@show` | No | JSON | `tournaments.registration.show` |
| `POST` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@store` | No (guests only when the tournament allows them) | Redirect, or JSON (`201`) | `tournaments.registration.store` |
| `DELETE` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@destroy` | Yes | Redirect, or JSON | `tournaments.registration.destroy` |

- `{slug}` matches `[a-z0-9-]+`; a tournament's UUID is also accepted and answers `301` to the slug. `{tournamentId}` must be a UUID.
- `/torneos` accepts `page` and `status` (`all`, `active` = in progress, `upcoming` = registration open or closed, `past` = finished).
- Registration endpoints answer JSON only when the request expects JSON and is not an Inertia visit; otherwise they redirect back with a flash message. JSON shapes: `show` → `{"data": {"registration": Participant | null, "can_register": boolean}}`; `store` → `201 {"message", "data": Participant}`; `destroy` → `{"message"}`. Errors: `400` (registration not open, tournament full, already registered, cannot withdraw), `403` (guests not allowed, user not allowed by role), `404` (tournament or registration not found), `401` (guest on a tournament without guests, JSON only; browsers are redirected to `/iniciar-sesion`).
- Check-in (`store`): signed-in users check in with their account email; guests send `email`. Failures (self check-in not allowed, window closed, participant not found) come back as validation errors (`check_in` or `email`).

| Page | Props |
|------|-------|
| `Tournaments/Index` | `tournaments` (paginated `TournamentResource`, 12 per page), `currentFilter` |
| `Tournaments/Show` | `tournament`, `standings` (top 10, once started), `participants` (before it starts, when `show_participants` is on), `currentRound`, `userRegistration`, `canRegister` |
| `Tournaments/Standings` | `tournament`, `standings` |
| `Tournaments/Rounds` | `tournament`, `rounds` (`{round, matches}[]`) |
| `Tournaments/CheckIn` | `tournament`, `userRegistration`, `checkInWindow` (`status`: `open`, `not_yet`, `closed` or `not_available`; `opens_at`, `closes_at`), `eventStartDate` |
| `Tournaments/MyTournaments` | `upcoming`, `inProgress`, `past` |
| `Tournaments/CancelRegistration` | `participant`, `tournament` (`id`, `name`, `eventDate`), `token`, `canCancel` |

`tournament`, `standings`, `participants`, rounds and matches are the `toArray()` of the module's response DTOs.

### 6.6. venue-bookings

| Method | URL | Controller | Auth | Response | Name |
|--------|-----|------------|------|----------|------|
| `GET` | `/reservas` | `BookingCalendarController@index` | No | Inertia `VenueBookings/Index` | `bookings.index` |
| `GET` | `/reservas/api/slots` | `BookingApiController@slots` | No | JSON | `bookings.api.slots` |
| `GET` | `/reservas/api/events` | `BookingApiController@calendarEvents` | No | JSON | `bookings.api.events` |
| `GET` | `/reservas/api/bookings/{booking}` | `BookingApiController@show` | No | JSON | `bookings.api.show` |
| `GET` | `/reservas/api/eligibility` | `BookingApiController@eligibility` | Yes | JSON | `bookings.api.eligibility` |
| `POST` | `/reservas` | `BookingController@store` | Yes | Redirect to `/reservas/mis-reservas`, or back with `error` | `bookings.store` |
| `GET` | `/reservas/mis-reservas` | `BookingController@myBookings` | Yes | Inertia `VenueBookings/MyBookings` | `bookings.my-bookings` |
| `DELETE` | `/reservas/{booking}` | `BookingController@cancel` | Yes | Redirect back with flash | `bookings.cancel` |

| Endpoint | Query | JSON response |
|----------|-------|---------------|
| `/reservas/api/slots` | `resource_id` (uuid), `date` (`Y-m-d`), both required | `{start_time, end_time, is_available, label}[]` |
| `/reservas/api/events` | `resource_id` (uuid), `start`, `end` (dates), all required | `{id, title, start, end, color, status, status_label, user_id}[]` |
| `/reservas/api/eligibility` | `resource_id` (uuid), `date` (`Y-m-d`), both required | `{can_book, reasons}` |
| `/reservas/api/bookings/{booking}` | -- | `id`, `resource_name`, `date`, `start_time`, `end_time`, `status`, `status_label`, `status_color`, `field_values` (only fields visible to the viewer), `cancellation_reason`, plus `game_table_name` / `campaign_name` when linked and `user_name` / `admin_notes` for admins; `404 {"message": "Not found"}` for an unknown id |

- `/reservas` accepts `resource_id` to preselect a resource. Props: `resources` (active resources with their scheduling mode, visible form fields and consecutive-slot limits), `selectedResourceId`.
- `VenueBookings/MyBookings` props: `bookings` (`BookingListDTO::toArray()`: `id`, `resource_id`, `resource_name`, `user_id`, `user_name`, `date`, `start_time`, `end_time`, `status`, `status_label`, `status_color`, `is_cancellable`, `field_values`, `cancellation_reason`).
- `DELETE /reservas/{booking}` accepts an optional `reason`.
- Booking errors (slot unavailable, booking limit, too soon, too far, minimum slots not met) come back as an `error` flash.

---

## 7. Request validation

### 7.1. Core FormRequests (`App\Http\Requests`)

| FormRequest | Route | Rules |
|-------------|-------|-------|
| `ContactFormRequest` | `POST /contacto` | `name`: required, string, max:255; `email`: required, email; `message`: required, string, max:5000 |
| `SearchRequest` | `GET /buscar` | `q`: nullable, string, max:100 (queries under 2 characters return `error: 'minChars'`) |
| `TagFilterRequest` | `GET /eventos`, `/articulos`, `/galeria` | `tags`: nullable, string (comma-separated slugs); `page`: nullable, integer, min:1 |
| `Api\CalendarRequest` | `GET /eventos/calendario` | `start`: required, date; `end`: required, date |
| `Auth\RegisterRequest` | `POST /registro` | `name`: required, string, max:255; `email`: required, string, email, max:255, unique:users,email; `password`: required, confirmed, `Password::min(8)` |
| `Auth\LoginRequest` | `POST /iniciar-sesion` | `email`: required, string, email; `password`: required, string; `remember`: boolean |
| `Auth\ForgotPasswordRequest` | `POST /olvide-contrasena` | `email`: required, string, email |
| `Auth\ResetPasswordRequest` | `POST /restablecer-contrasena` | `token`: required, string; `email`: required, string, email; `password`: required, confirmed, `Password::min(8)` |
| `Auth\UpdateProfileRequest` | `POST /perfil` | `name`: required, string, max:255; `display_name`: nullable, string, max:255; `email`: required, string, email, max:255, unique among users except the current one; `avatar`: nullable, image, max:2048 (KB) |
| `Auth\ChangePasswordRequest` | `PUT /perfil/contrasena` | `current_password`: required, string, current_password; `password`: required, confirmed, `Password::min(8)` |

`ContactFormRequest` also has a honeypot: when the hidden `website` field is filled, the controller answers with the usual `success` flash and sends nothing. When the contact email is not configured, it answers with an `error` flash.

### 7.2. Module FormRequests

| FormRequest (module) | Route | Rules |
|----------------------|-------|-------|
| `RegisterToEventRequest` (event-registrations) | `POST /eventos/{eventId}/inscripcion` | `form_data`: sometimes, array; `form_data.*`: nullable, string, max:1000; `notes`: nullable, string, max:500. `authorize()` requires a user |
| `RegisterParticipantRequest` (tournaments) | `POST` and `DELETE /torneos/{tournamentId}/inscripcion` | Guests: `guest_name` required, string, max:255; `guest_email` required, email, max:255; `gdpr_consent` required, accepted. Signed-in users: `guest_name` nullable, string, max:255; `guest_email` nullable, email, max:255, required_with:guest_name |
| `CheckInRequest` (tournaments) | `POST /torneos/{slug}/check-in` | Guests: `email` required, email, max:255; `gdpr_consent` required, accepted. Signed-in users: no fields |
| `RegisterRequest` (game-tables) | `POST /mesas/{gameTable}/inscripcion` | `role`: sometimes, string, in `ParticipantRole` values (default `player`); `notes`: nullable, string, max:500. `authorize()` requires a user |
| `GuestRegisterRequest` (game-tables) | `POST /mesas/{gameTable}/inscripcion-invitado` | `first_name`: required, string, max:100; `email`: required, email, max:255; `phone`: nullable, string, max:20; `role`: sometimes, in `player`, `spectator`; `notes`: nullable, string, max:500; `gdpr_consent`: required, accepted |
| `SaveConsentRequest` (cookie-consent) | `POST /consentimiento-cookies/consentir` | `visitor_id`: required, uuid; `preferences`: required, array; `preferences.*`: required, boolean; `config_version`: required, integer, min:1; `consent_method`: sometimes, string, in `banner`, `settings_page`, `api` (default `banner`) |
| `StoreBookingRequest` (venue-bookings) | `POST /reservas` | `resource_id`: required, string, uuid; `date`: required, `date_format:Y-m-d`; `start_time`, `end_time`: required, `date_format:H:i`; `field_values`: nullable, array; `event_id`: nullable, string, uuid; `game_table_id`, `tournament_id`, `campaign_id`: nullable, string; plus the rules of the resource's form fields from `BookingFieldConfigServiceInterface::getValidationRules()`. `authorize()` requires a user |
| `GetAnnouncementsRequest` (announcements) | `GET /anuncios` | No rules |

The venue-bookings query endpoints (`/reservas/api/*`) validate inline in the controller (see §6.6).

#### `FrontendCreateGameTableRequest` (game-tables)

Routes: `POST /mesas/crear`, `PUT /mesas/mis-mesas/{gameTable}`. `authorize()` requires a user.

| Field | Rules |
|-------|-------|
| `game_system_id` | required, uuid, exists:gametables_game_systems,id |
| `title` | required, string, min:5, max:200 |
| `starts_at` | required, date, after:now |
| `duration_minutes` | required, integer, min:30, max:720 |
| `table_type` | required, string, in `TableType` values |
| `table_format` | required, string, in `TableFormat` values |
| `min_players` | required, integer, min:1, max:20 |
| `max_players` | required, integer, min:1, max:20, gte:min_players |
| `max_spectators` | sometimes, integer, min:0, max:50 |
| `event_id` | nullable, uuid, exists:events,id |
| `campaign_id` | nullable, uuid, exists:gametables_campaigns,id |
| `synopsis` | nullable, string, max:5000 |
| `location` | nullable, string, max:500 |
| `online_url` | nullable, url, max:500 |
| `minimum_age` | nullable, integer, min:0, max:21 |
| `language` | required, string, size:2 |
| `genres` / `genres.*` | nullable, array / string in `Genre` values |
| `tone` | nullable, string, in `Tone` values |
| `experience_level` | required, string, in `ExperienceLevel` values |
| `character_creation` | required, string, in `CharacterCreation` values |
| `safety_tools` / `safety_tools.*` | nullable, array / string in `SafetyTool` values |
| `content_warning_ids` / `.*` | nullable, array / uuid, exists:gametables_content_warnings,id |
| `custom_warnings` / `.*` | nullable, array / string, max:200 |
| `game_masters` | nullable, array, min:1 |
| `game_masters.*.user_id` | nullable, uuid |
| `game_masters.*.first_name` | required_without:game_masters.*.user_id, nullable, string, max:100 |
| `game_masters.*.last_name` | nullable, string, max:100 |
| `game_masters.*.email` | required_without:game_masters.*.user_id, nullable, email, max:255 |
| `game_masters.*.custom_title` | nullable, string, max:100 |
| `game_masters.*.is_name_public` | boolean |
| `game_masters.*.role` | required, string, in `GameMasterRole` values |
| `notes` | nullable, string, max:2000 |

#### `FrontendCreateCampaignRequest` (game-tables)

Routes: `POST /campanas/crear`, `PUT /campanas/mis-campanas/{id}`. `authorize()` requires a user.

| Field | Rules |
|-------|-------|
| `game_system_id` | required, uuid, exists:gametables_game_systems,id |
| `title` | required, string, min:5, max:200 |
| `synopsis` | nullable, string, max:5000 |
| `frequency` | required, string, in `CampaignFrequency` values |
| `schedule_notes` | nullable, string, max:500 |
| `min_players` | required, integer, min:1, max:12 |
| `max_players` | required, integer, min:1, max:12, gte:min_players |
| `table_format` | required, string, in `TableFormat` values |
| `location` | nullable, string, max:500 |
| `online_url` | nullable, url, max:500 |
| `language` | sometimes, string, size:2 |
| `genres` / `genres.*` | nullable, array / string in `Genre` values |
| `tone` | nullable, string, in `Tone` values |
| `experience_level` | nullable, string, in `ExperienceLevel` values |
| `character_creation` | nullable, string, in `CharacterCreation` values |
| `safety_tools` / `safety_tools.*` | nullable, array / string in `SafetyTool` values |
| `content_warning_ids` / `.*` | nullable, array / uuid, exists:gametables_content_warnings,id |
| `custom_warnings` / `.*` | nullable, array / string, max:200 |

---

## 8. HTTP resources

### 8.1. Core resources (`App\Http\Resources`)

#### `EventResource`

Built from `EventResponseDTO`.

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string | null;
  startDate: string;              // ISO 8601
  endDate: string;                // ISO 8601
  location: string | null;
  memberPrice: number | null;
  nonMemberPrice: number | null;
  imagePublicId: string | null;
  isPublished: boolean;
  createdAt: string | null;
  updatedAt: string | null;
  downloadLinks: Array<Record<string, string>>;   // the events.download_links JSON as stored (label, url, description)
  tags: TagResource[];
}
```

#### `CalendarEntryResource`

Built from `App\Application\Calendar\DTOs\CalendarEntryDTO`; the source's `details` are merged into the top level.

```typescript
{
  id: string;
  title: string;
  start: string;                  // ISO 8601
  end: string | null;             // ISO 8601
  url: string;
  sourceType: string;             // 'event', 'game-table', ...
  sourceLabel: string;            // translated source name
  color: 'primary' | 'accent' | 'info' | 'success' | 'warning' | 'neutral';
  // details of an 'event' entry (EventCalendarSource):
  slug: string;
  description: string | null;
  location: string | null;
  imagePublicId: string | null;
  memberPrice: number | null;
  nonMemberPrice: number | null;
  tags: Array<{ id: string; name: string; slug: string; color: string; parentId: string | null }>;
}
```

A `game-table` entry (game-tables module, color `accent`, `url` `/mesas/{slug}`, `end` = start plus the table's duration) carries `gameSystemName`, `currentPlayers`, `maxPlayers`, `isFull`, `status`, `tags` (`[]`) and `description` (`''`) instead.

#### `ArticleResource`

Built from `ArticleResponseDTO`.

```typescript
{
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  featuredImagePublicId: string | null;
  isPublished: boolean;
  publishedAt: string | null;
  author: AuthorResource | null;
  createdAt: string | null;
  updatedAt: string | null;
  tags: TagResource[];
}
```

#### `AuthorResource`

```typescript
{ id: string; name: string; displayName: string | null; avatarPublicId: string | null }
```

#### `GalleryResource`

Built from `GalleryResponseDTO`.

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string | null;
  coverImagePublicId: string | null;
  isPublished: boolean;
  isFeatured: boolean;
  photoCount: number;
  createdAt: string | null;
  updatedAt: string | null;
  tags: TagResource[];
}
```

#### `GalleryWithPhotosResource`

Built from `GalleryDetailResponseDTO`.

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string | null;
  isPublished: boolean;
  isFeatured: boolean;
  photoCount: number;
  createdAt: string | null;
  updatedAt: string | null;
  photos: PhotoResource[];
  tags: TagResource[];
}
```

#### `PhotoResource`

```typescript
{ id: string; imagePublicId: string; caption: string | null; sortOrder: number }
```

#### `HeroSlideResource`

```typescript
{
  id: string;
  title: string;
  subtitle: string | null;
  buttonText: string | null;
  buttonUrl: string | null;
  imagePublicId: string;
  isActive: boolean;
  sortOrder: number;
}
```

#### `TagResource`

```typescript
{
  id: string;
  name: string;
  slug: string;
  parentId: string | null;
  parentName: string | null;
  appliesTo: string[];
  color: string;
  sortOrder: number;
}
```

### 8.2. Module resources

#### `TournamentResource` (tournaments)

`Modules\Tournaments\Http\Resources\TournamentResource`, built from `TournamentResponseDTO`. Used by the `/torneos` listing.

```typescript
{
  id: string;
  name: string;
  slug: string;
  description: string | null;
  imagePublicId: string | null;
  status: string;
  statusLabel: string;
  statusColor: string;
  currentRound: number;
  maxRounds: number | null;
  participantCount: number;
  maxParticipants: number | null;
  minParticipants: number;
  registrationOpensAt: string | null;
  registrationClosesAt: string | null;
  startedAt: string | null;
  completedAt: string | null;
  isRegistrationOpen: boolean;
  isInProgress: boolean;
  isFinished: boolean;
  hasCapacity: boolean;
}
```

#### `GameTableResource` (game-tables)

`Modules\GameTables\Http\Resources\GameTableResource`, built from `GameTableResponseDTO`. Used by `/mesas/{slug}`.

```typescript
{
  id: string;
  title: string;
  slug: string | null;
  synopsis: string | null;
  gameSystemId: string;
  gameSystemName: string;
  campaignId: string | null;
  campaignTitle: string | null;
  eventId: string | null;
  eventTitle: string | null;
  createdBy: string;
  creatorName: string;
  tableType: string;
  tableTypeLabel: string;
  tableFormat: string;
  tableFormatLabel: string;
  tableFormatColor: string;
  status: string;
  statusLabel: string;
  statusColor: string;
  startsAt: string | null;
  durationMinutes: number;
  location: string | null;
  onlineUrl: string | null;
  minPlayers: number;
  maxPlayers: number;
  maxSpectators: number;
  minimumAge: number | null;
  language: string;
  languageLabel: string | null;
  experienceLevel: string | null;
  experienceLevelLabel: string | null;
  characterCreation: string | null;
  characterCreationLabel: string | null;
  genres: string[];               // translated labels
  tone: string | null;
  toneLabel: string | null;
  safetyTools: string[];          // translated labels
  contentWarnings: string[];      // content warning ids
  customWarnings: string[];
  registrationType: string;
  registrationTypeLabel: string;
  membersEarlyAccessDays: number;
  registrationOpensAt: string | null;
  registrationClosesAt: string | null;
  autoConfirm: boolean;
  acceptsRegistrationsInProgress: boolean;
  isPublished: boolean;
  publishedAt: string | null;
  notes: string | null;
  imagePublicId: string | null;
  gameMasters: GameMasterResource[];
  mainGameMasterName: string;     // main game master, or the creator
  currentPlayers: number;
  currentSpectators: number;
  spotsAvailable: number;
  spectatorSpotsAvailable: number;
  waitingListCount: number;
  createdAt: string | null;
  updatedAt: string | null;
}
```

#### `GameTableListResource` (game-tables)

Built from `GameTableListDTO`. Used by `/mesas`, `/mesas/calendario` and inside `CampaignResource`.

```typescript
{
  id: string;
  title: string;
  slug: string | null;
  gameSystemName: string;
  startsAt: string | null;
  durationMinutes: number;
  tableFormat: { value: string; label: string; color: string };
  tableType: { value: string; label: string };
  status: { value: string; label: string; color: string };
  location: string | null;
  onlineUrl: string | null;
  minPlayers: number;
  maxPlayers: number;
  currentPlayers: number;
  spotsAvailable: number;
  isFull: boolean;
  isPublished: boolean;
  creatorName: string;
  mainGameMasterName: string;
  eventId: string | null;
  eventTitle: string | null;
  imagePublicId: string | null;
}
```

#### `GameMasterResource` and `CampaignGameMasterResource` (game-tables)

```typescript
// GameMasterResource
{ id: string; gameTableId: string; userId: string | null; displayName: string; role: string; roleLabel: string; customTitle: string | null; isMain: boolean; isNamePublic: boolean }
// CampaignGameMasterResource (no parent id)
{ id: string; userId: string | null; displayName: string; role: string; roleLabel: string; customTitle: string | null; isMain: boolean; isNamePublic: boolean }
```

#### `CampaignResource` (game-tables)

Built from `CampaignResponseDTO`. Used by `/campanas` and `/campanas/{slug}`.

```typescript
{
  id: string;
  title: string;
  slug: string | null;
  description: string | null;
  gameSystemId: string;
  gameSystemName: string;
  createdBy: string;
  creatorName: string;
  status: string;
  statusLabel: string;
  statusColor: string;
  frequency: string | null;
  frequencyLabel: string | null;
  maxPlayers: number | null;
  currentPlayers: number;
  spotsAvailable: number | null;
  isPublished: boolean;
  isRecruiting: boolean;
  acceptsNewPlayers: boolean;
  sessionCount: number | null;
  currentSession: number;
  totalSessions: number;
  imagePublicId: string | null;
  gameMasters: CampaignGameMasterResource[];
  gameTables: GameTableListResource[];
  hasActiveOrUpcomingTables: boolean;
  mainGameMasterName: string;
  createdAt: string | null;
  updatedAt: string | null;
  // fixed placeholder values, not backed by data:
  expectedDurationMonths: null; startDate: null; endDate: null; recruitmentMessage: null; settings: null;
  themes: []; safetyTools: []; contentWarnings: []; minimumAge: null;
  language: 'es'; experienceLevel: 'none'; experienceLevelLabel: string;
}
```

---

## 9. Middleware

Configured in `src/bootstrap/app.php`.

### Custom middleware (`App\Http\Middleware`)

| Middleware | Registration | Purpose |
|------------|--------------|---------|
| `HandleInertiaRequests` | appended to the `web` group | Inertia root view, asset version and shared props (§2). Skipped for `admin/*` and Livewire requests |
| `SecurityHeadersMiddleware` | appended to the `web` group | Sets `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Content-Security-Policy`, and `Strict-Transport-Security` on HTTPS requests |
| `EnsureSiteIsNotInMaintenance` | appended to the `web` group | While the `maintenance_enabled` setting is on, renders the Inertia page `Maintenance` (`message`, `seo`) with status `503` and `Retry-After: 600`. Lets through `admin`, `admin/*`, `up`, `iniciar-sesion`, Livewire requests and users who can access the admin panel |
| `BlockBotsMiddleware` | global (`$middleware->append`), alias `bot-protection` | Answers `403` (`Access denied`, or `{"message": "Access denied"}` for JSON) to user agents in `config('bot-protection.blocked_bots')`, unless they match `allowed_bots`; disabled with `bot-protection.enabled` |
| `EnsureRegistrationIsEnabled` | alias `registration.enabled` | Redirects to `/` when registration is disabled in the site settings |
| `EnsureLoginIsEnabled` | alias `login.enabled` | Redirects to `/` when login is disabled in the site settings |

Because the first three are in the `web` group, `/api/*` responses get no Inertia data, no security headers and no maintenance page. Modules can push their own middleware into the group (cookie-consent adds `InjectCookieScripts`, §6.2).

### Laravel middleware used by the routes

| Middleware | Purpose |
|------------|---------|
| `web` | Session, cookies, CSRF and the custom middleware above |
| `api` | Stateless group for `/api/*` |
| `auth` | Requires a signed-in user |
| `guest` | Only for visitors without a session |
| `signed` | Valid signed URL (email verification links) |
| `throttle:X,Y` | At most X requests every Y minutes |
| `throttle:contact`, `throttle:login` | Named rate limiters (below) |

### Rate limiting

| Limiter | Limit | Key | Routes |
|---------|-------|-----|--------|
| `login` | 5 per minute | email + IP | `POST /iniciar-sesion` |
| `contact` | 3 per minute | IP | `POST /contacto` |
| `throttle:5,1` | 5 per minute | IP | `POST /registro`, `POST /olvide-contrasena` |
| `throttle:6,1` | 6 per minute | user | `POST /verificar-email/reenviar` |

The `login` and `contact` limiters are defined in `AppServiceProvider` (`RateLimiter::for()`). Exceeding a limit answers `429`.

---

## 10. Appendices

### 10.1. Paginated response

Listings built with the `BuildsPaginatedResponse` trait (events, articles, galleries, tournaments, game tables, campaigns) return `PaginatedResponseDTO::toArray()` (TypeScript `PaginatedResponse<T>` in `resources/js/types/index.ts`):

```typescript
{
  data: T[];
  meta: {
    currentPage: number;
    lastPage: number;              // 1 when there are no items
    perPage: number;
    total: number;
  };
  links: {
    first: string;                 // {current URL}?page=1
    last: string;
    prev: string | null;
    next: string | null;
  };
}
```

### 10.2. Status codes

| Code | Where |
|------|-------|
| `200` | JSON endpoints and Inertia pages |
| `201` | `POST /eventos/{eventId}/inscripcion`, `POST /consentimiento-cookies/consentir`, JSON `POST /torneos/{tournamentId}/inscripcion` |
| `301` | Old slugs or UUIDs on `/mesas/{slug}`, `/campanas/{slug}` and the `/torneos/{slug}` pages |
| `302` | Form submissions (redirect with flash or validation errors) and `auth` routes visited without a session |
| `400` | SES webhook without `Type`; tournament registration not open, full, already registered or cannot withdraw (JSON) |
| `401` | `auth` routes and guest-only tournament registration requested as JSON |
| `403` | Invalid SNS signature, blocked bots, failed game-table policies, tournament registration not allowed (JSON), members-only event registration |
| `404` | Unknown slug or id |
| `422` | Validation errors on JSON requests; event registration closed, full, already registered or not cancellable; invalid cookie consent |
| `429` | Rate limit exceeded |
| `503` | Any `web` route while maintenance mode is on (except the routes listed in §9) |
