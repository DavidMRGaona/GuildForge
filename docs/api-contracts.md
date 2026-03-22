# API contracts

> Generated: 2026-03-21 | Project: GuildForge

---

## Table of contents

1. [Public web routes](#1-public-web-routes)
2. [Authentication routes](#2-authentication-routes)
3. [API routes](#3-api-routes)
4. [Module routes](#4-module-routes)
5. [Request validation](#5-request-validation)
6. [HTTP resources](#6-http-resources)
7. [Middleware](#7-middleware)

---

## 1. Public web routes

### Content pages

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `GET` | `/` | `HomeController` (invokable) | `web` | Inertia (`Home`) | No |
| `GET` | `/nosotros` | `AboutController` (invokable) | `web` | Inertia (`About`) | No |
| `POST` | `/contacto` | `ContactController` (invokable) | `web`, `throttle:contact` | Redirect with flash | No |
| `GET` | `/calendario` | `CalendarPageController` (invokable) | `web` | Inertia (`Calendar/Index`) | No |
| `GET` | `/buscar` | `SearchController` (invokable) | `web` | Inertia (`Search/Index`) | No |
| `GET` | `/sitemap.xml` | `SitemapController` (invokable) | `web` | XML (`application/xml`) | No |

### Events

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `GET` | `/eventos` | `EventController@index` | `web` | Inertia (`Events/Index`) | No |
| `GET` | `/eventos/{slug}` | `EventController@show` | `web` | Inertia (`Events/Show`) | No |
| `GET` | `/eventos/calendario` | `CalendarController@index` | `web` | JSON | No |

**Props for `/eventos` (Inertia):**
- `events` -- paginated response with `EventResource`
- `tags` -- `TagResource[]`
- `currentTags` -- `string[]`

**Query params for `/eventos`:**
- `page` (int, optional) -- page number
- `tags` (string, optional) -- comma-separated tag slugs

**Props for `/eventos/{slug}` (Inertia):**
- `event` -- `EventResource`

**JSON response for `/eventos/calendario`:**
- `CalendarEventResource[]`
- Required query params: `start` (date), `end` (date)

### Articles

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `GET` | `/articulos` | `ArticleController@index` | `web` | Inertia (`Articles/Index`) | No |
| `GET` | `/articulos/{slug}` | `ArticleController@show` | `web` | Inertia (`Articles/Show`) | No |

**Props for `/articulos` (Inertia):**
- `articles` -- paginated response with `ArticleResource`
- `tags` -- `TagResource[]`
- `currentTags` -- `string[]`

**Query params for `/articulos`:**
- `page` (int, optional) -- page number
- `tags` (string, optional) -- comma-separated tag slugs

**Props for `/articulos/{slug}` (Inertia):**
- `article` -- `ArticleResource`

### Gallery

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `GET` | `/galeria` | `GalleryController@index` | `web` | Inertia (`Gallery/Index`) | No |
| `GET` | `/galeria/{slug}` | `GalleryController@show` | `web` | Inertia (`Gallery/Show`) | No |

**Props for `/galeria` (Inertia):**
- `galleries` -- paginated response with `GalleryResource`
- `tags` -- `TagResource[]`
- `currentTags` -- `string[]`

**Props for `/galeria/{slug}` (Inertia):**
- `gallery` -- `GalleryWithPhotosResource`

### Legal pages

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `GET` | `/{slug}` | `LegalPageController@show` | `web` | Inertia (`Legal/Show`) | No |

**Slug constraint:** only accepts `politica-de-privacidad`, `aviso-legal`, `politica-de-cookies`, `terminos-y-condiciones`.

**Props for `/{slug}` (Inertia):**
- `title` -- `string`
- `content` -- `string`
- `lastUpdated` -- `string|null` (ISO 8601 format)

### Inertia shared data (all pages)

The `HandleInertiaRequests` middleware shares this data in every Inertia response:

```typescript
{
  appName: string;
  appDescription: string;
  siteLogoLight: string;
  siteLogoDark: string;
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
}
```

> **Note on lazy loading:** all shared fields except `appName` and `appDescription` are resolved via closures (`fn () => ...`), meaning Inertia only evaluates each field when the page needs it. Module fields (`moduleSlots`, `modulePages`, `moduleTranslations`) include an internal try/catch to prevent errors if a module fails to load.

---

## 2. Authentication routes

### Guest routes (middleware `guest`)

| Method | Route | Controller | Additional middleware | Response | Name |
|--------|-------|------------|---------------------|----------|------|
| `GET` | `/registro` | `RegisterController@create` | `guest`, `registration.enabled` | Inertia (`Auth/Register`) | `register` |
| `POST` | `/registro` | `RegisterController@store` | `guest`, `registration.enabled`, `throttle:5,1` | Redirect | -- |
| `GET` | `/iniciar-sesion` | `LoginController@create` | `guest`, `login.enabled` | Inertia (`Auth/Login`) | `login` |
| `POST` | `/iniciar-sesion` | `LoginController@store` | `guest`, `login.enabled`, `throttle:login` | Redirect | -- |
| `GET` | `/olvide-contrasena` | `ForgotPasswordController@create` | `guest` | Inertia (`Auth/ForgotPassword`) | `password.request` |
| `POST` | `/olvide-contrasena` | `ForgotPasswordController@store` | `guest`, `throttle:5,1` | Redirect | `password.email` |
| `GET` | `/restablecer-contrasena/{token}` | `ResetPasswordController@create` | `guest` | Inertia (`Auth/ResetPassword`) | `password.reset` |
| `POST` | `/restablecer-contrasena` | `ResetPasswordController@store` | `guest` | Redirect | `password.update` |

### Authenticated routes (middleware `auth`)

| Method | Route | Controller | Additional middleware | Response | Name |
|--------|-------|------------|---------------------|----------|------|
| `POST` | `/cerrar-sesion` | `LoginController@destroy` | `auth` | Redirect to `/` | `logout` |
| `GET` | `/verificar-email` | `EmailVerificationController@notice` | `auth` | Inertia (`Auth/VerifyEmail`) | `verification.notice` |
| `GET` | `/verificar-email/{id}/{hash}` | `EmailVerificationController@verify` | `auth`, `signed` | Redirect | `verification.verify` |
| `POST` | `/verificar-email/reenviar` | `EmailVerificationController@resend` | `auth`, `throttle:6,1` | Redirect | `verification.send` |
| `GET` | `/verificar-email-pendiente/{id}/{hash}` | `EmailVerificationController@verifyPendingEmail` | `auth`, `signed` | Redirect | `verification.pending-email` |
| `GET` | `/perfil` | `ProfileController@show` | `auth` | Inertia (`Profile/Show`) | `profile.show` |
| `POST` | `/perfil` | `ProfileController@update` | `auth` | Redirect | `profile.update` |
| `PUT` | `/perfil/contrasena` | `ProfileController@changePassword` | `auth` | Redirect | `profile.password` |

**Props for `/perfil` (Inertia):**
- `user` -- user DTO (id, name, displayName, email, avatarPublicId, role, emailVerified, createdAt)

---

## 3. API routes

All API routes use the `/api` prefix and `api` middleware.

| Method | Route | Controller | Middleware | Response | Auth |
|--------|-------|------------|-----------|----------|------|
| `POST` | `/api/webhooks/ses` | `SesWebhookController` (invokable) | `api` | JSON | No (SNS validation) |

### `POST /api/webhooks/ses`

Webhook for Amazon SES notifications via SNS. Processes three message types:

**Message types:**
- `SubscriptionConfirmation` -- automatically confirms the SNS subscription
- `Notification` -- processes bounces and complaints
- Default -- responds `{"status": "ok"}`

**Validation:** SNS signature verified via `SnsMessageValidatorInterface`.

**Responses:**
- `200` -- `{"status": "ok"}` or `{"status": "confirmed"}`
- `400` -- `{"error": "Invalid payload"}`
- `403` -- `{"error": "Invalid signature"}`

---

## 4. Module routes

### 4.1. event-registrations

**Prefix:** `/eventos/{eventId}/inscripcion` (eventId must be UUID)

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/eventos/{eventId}/inscripcion/estado` | `EventRegistrationController@status` | No | JSON | `event-registrations.status` |
| `GET` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@show` | Yes | JSON | `event-registrations.show` |
| `POST` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@store` | Yes | JSON (201) | `event-registrations.store` |
| `DELETE` | `/eventos/{eventId}/inscripcion` | `EventRegistrationController@destroy` | Yes | JSON | `event-registrations.destroy` |
| `GET` | `/mis-inscripciones` | `EventRegistrationController@myRegistrations` | Yes | JSON | `event-registrations.my-registrations` |

**Response for `status`:**
```json
{ "data": { /* event status */ } }
```

**Response for `store` (success):**
```json
{ "data": { /* registration */ }, "message": "..." }
```

**Possible errors in `store`:** 422 (`RegistrationClosedException`, `EventFullException`, `AlreadyRegisteredException`)

### 4.2. tournaments

**Prefix:** `/torneos`

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/torneos` | `TournamentListController@index` | No | Inertia (`Tournaments/Index`) | `tournaments.index` |
| `GET` | `/torneos/cancelar/{token}` | `GuestCancellationController@show` | No | Inertia (`Tournaments/CancelRegistration`) | `tournaments.cancel-confirmation` |
| `DELETE` | `/torneos/cancelar/{token}` | `GuestCancellationController@destroy` | No | Redirect | `tournaments.cancel-by-token` |
| `GET` | `/torneos/mis-torneos` | `MyTournamentsController` (invokable) | Yes | Inertia (`Tournaments/MyTournaments`) | `tournaments.my-tournaments` |
| `GET` | `/torneos/{slug}` | `TournamentController@show` | No | Inertia (`Tournaments/Show`) | `tournaments.show` |
| `GET` | `/torneos/{slug}/clasificación` | `TournamentController@standings` | No | Inertia (`Tournaments/Standings`) | `tournaments.standings` |
| `GET` | `/torneos/{slug}/rondas` | `TournamentController@rounds` | No | Inertia (`Tournaments/Rounds`) | `tournaments.rounds` |
| `GET` | `/torneos/{slug}/check-in` | `TournamentCheckInController@show` | No | Inertia (`Tournaments/CheckIn`) | `tournaments.check-in.show` |
| `POST` | `/torneos/{slug}/check-in` | `TournamentCheckInController@store` | No | Redirect | `tournaments.check-in.store` |

**Tournament registrations** (prefix `/torneos/{tournamentId}`, tournamentId UUID):

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@show` | No | JSON | `tournaments.registration.show` |
| `POST` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@store` | No* | JSON/Redirect | `tournaments.registration.store` |
| `DELETE` | `/torneos/{tournamentId}/inscripcion` | `TournamentRegistrationController@destroy` | Yes | JSON/Redirect | `tournaments.registration.destroy` |

*Registration can be from an authenticated user or a guest (if the tournament allows it).

**Props for `/torneos` (Inertia):**
- `tournaments` -- paginated response with `TournamentResource`
- `currentFilter` -- `string` (`all`, `active`, `upcoming`, `past`)

**Query params:** `page` (int), `status` (string: `all`|`active`|`upcoming`|`past`)

**Props for `/torneos/{slug}` (Inertia):**
- `tournament` -- tournament data
- `standings` -- standings (top 10)
- `participants` -- participant list (if the tournament allows it)
- `currentRound` -- current round
- `userRegistration` -- current user's registration (if authenticated)
- `canRegister` -- `boolean`

### 4.3. game-tables

**Prefix:** `/mesas`

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/mesas` | `GameTableController@index` | No | Inertia (`GameTables/Index`) | `gametables.index` |
| `GET` | `/mesas/calendario` | `GameTableController@calendar` | No | Inertia (`GameTables/Calendar`) | `gametables.calendar` |
| `GET` | `/mesas/cancelar/{token}` | `RegistrationController@showCancelConfirmation` | No | Inertia (`GameTables/CancelRegistration`) | `gametables.cancel-confirmation` |
| `DELETE` | `/mesas/cancelar/{token}` | `RegistrationController@cancelByToken` | No | Redirect | `gametables.cancel-by-token` |
| `GET` | `/mesas/crear` | `FrontendGameTableController@create` | Yes | Inertia (`GameTables/Create`) | `gametables.create` |
| `POST` | `/mesas/crear` | `FrontendGameTableController@store` | Yes | Redirect | `gametables.store` |
| `GET` | `/mesas/mis-mesas` | `FrontendGameTableController@myTables` | Yes | Inertia (`GameTables/MyTables`) | `gametables.my-tables` |
| `GET` | `/mesas/mis-mesas/{gameTable}/editar` | `FrontendGameTableController@edit` | Yes | Inertia (`GameTables/Edit`) | `gametables.edit` |
| `PUT` | `/mesas/mis-mesas/{gameTable}` | `FrontendGameTableController@update` | Yes | Redirect | `gametables.update` |
| `POST` | `/mesas/mis-mesas/{gameTable}/enviar-revision` | `FrontendGameTableController@submitForReview` | Yes | Redirect | `gametables.submit-review` |
| `DELETE` | `/mesas/mis-mesas/{gameTable}` | `FrontendGameTableController@destroy` | Yes | Redirect | `gametables.destroy` |
| `POST` | `/mesas/{gameTable}/inscripcion-invitado` | `RegistrationController@registerGuest` | No | Redirect | `gametables.register-guest` |
| `POST` | `/mesas/{gameTable}/inscripcion` | `RegistrationController@register` | Yes | Redirect | `gametables.register` |
| `DELETE` | `/mesas/{gameTable}/inscripcion` | `RegistrationController@cancel` | Yes | Redirect | `gametables.cancel` |
| `GET` | `/mesas/{slug}` | `GameTableController@show` | No | Inertia (`GameTables/Show`) | `gametables.show` |

**Query params for `/mesas`:** `page` (int), `systems` (string, comma-separated IDs), `format` (string), `status` (string), `event` (string, event slug), `campaign` (string, UUID)

**Query params for `/mesas/calendario`:** `month` (string, format `YYYY-MM`)

**Props for `/mesas/{slug}` (Inertia):**
- `table` -- `GameTableResource`
- `eligibility` -- user eligibility data
- `userRegistration` -- current user's registration

### Campaigns (prefix `/campanas`)

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/campanas` | `CampaignController@index` | No | Inertia (`Campaigns/Index`) | `campaigns.index` |
| `GET` | `/campanas/crear` | `FrontendCampaignController@create` | Yes | Inertia (`Campaigns/Create`) | `campaigns.frontend-create` |
| `POST` | `/campanas/crear` | `FrontendCampaignController@store` | Yes | Redirect | `campaigns.frontend-store` |
| `GET` | `/campanas/mis-campanas` | `FrontendCampaignController@myCampaigns` | Yes | Inertia (`Campaigns/MyCampaigns`) | `campaigns.my-campaigns` |
| `GET` | `/campanas/mis-campanas/{id}/editar` | `FrontendCampaignController@edit` | Yes | Inertia (`Campaigns/Edit`) | `campaigns.frontend-edit` |
| `PUT` | `/campanas/mis-campanas/{id}` | `FrontendCampaignController@update` | Yes | Redirect | `campaigns.frontend-update` |
| `POST` | `/campanas/mis-campanas/{id}/enviar-revision` | `FrontendCampaignController@submitForReview` | Yes | Redirect | `campaigns.submit-review` |
| `DELETE` | `/campanas/mis-campanas/{id}` | `FrontendCampaignController@destroy` | Yes | Redirect | `campaigns.frontend-destroy` |
| `GET` | `/campanas/{slug}` | `CampaignController@show` | No | Inertia (`Campaigns/Show`) | `campaigns.show` |

### 4.4. venue-bookings

**Prefix:** `/reservas`

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/reservas` | `BookingCalendarController@index` | No | Inertia (`VenueBookings/Index`) | `bookings.index` |
| `GET` | `/reservas/api/slots` | `BookingApiController@slots` | No | JSON | `bookings.api.slots` |
| `GET` | `/reservas/api/events` | `BookingApiController@calendarEvents` | No | JSON | `bookings.api.events` |
| `GET` | `/reservas/api/bookings/{booking}` | `BookingApiController@show` | No | JSON | `bookings.api.show` |
| `GET` | `/reservas/api/eligibility` | `BookingApiController@eligibility` | Yes | JSON | `bookings.api.eligibility` |
| `POST` | `/reservas` | `BookingController@store` | Yes | Redirect | `bookings.store` |
| `GET` | `/reservas/mis-reservas` | `BookingController@myBookings` | Yes | Inertia (`VenueBookings/MyBookings`) | `bookings.my-bookings` |
| `DELETE` | `/reservas/{booking}` | `BookingController@cancel` | Yes | Redirect | `bookings.cancel` |

**Query params for `/reservas/api/slots`:** `resource_id` (UUID, required), `date` (YYYY-MM-DD, required)

**Query params for `/reservas/api/events`:** `resource_id` (UUID, required), `start` (date, required), `end` (date, required)

**Query params for `/reservas/api/eligibility`:** `resource_id` (UUID, required), `date` (YYYY-MM-DD, required)

### 4.5. announcements

**Prefix:** `/anuncios`

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/anuncios` | `AnnouncementController@index` | No | JSON | `announcements.index` |
| `GET` | `/anuncios/{id}` | `AnnouncementController@show` | No | JSON | `announcements.show` |

**Response for `index`:**
```json
{ "data": [ { /* announcement */ }, ... ] }
```

**Constraint:** `{id}` must be UUID.

### 4.6. cookie-consent

**Prefix:** `/consentimiento-cookies`

| Method | Route | Controller | Auth | Response | Name |
|--------|-------|------------|------|----------|------|
| `GET` | `/consentimiento-cookies/categorias` | `ConsentApiController@categories` | No | JSON | `cookie-consent.categories` |
| `GET` | `/consentimiento-cookies/configuración` | `ConsentApiController@config` | No | JSON | `cookie-consent.config` |
| `POST` | `/consentimiento-cookies/consentir` | `ConsentApiController@store` | No | JSON (201) | `cookie-consent.store` |

**Response for `categories`:**
```json
{ "data": [ { /* category with cookies */ }, ... ] }
```

**Response for `config`:**
```json
{ "data": { /* banner configuration */ } }
```

### 4.7. channel-notifications

Module with defined routes but no currently implemented endpoints.

### 4.8. memberships

Module with no web or API routes currently implemented (Filament admin functionality only).

---

## 5. Request validation

### 5.1. Core FormRequests

#### `ContactFormRequest`

**Route:** `POST /contacto`

| Field | Rules |
|-------|-------|
| `name` | `required`, `string`, `max:255` |
| `email` | `required`, `email` |
| `message` | `required`, `string`, `max:5000` |

Includes honeypot field `website` (if it has a value, the request is silently accepted without sending an email).

#### `SearchRequest`

**Route:** `GET /buscar`

| Field | Rules |
|-------|-------|
| `q` | `nullable`, `string`, `max:100` |

Minimum query length: 2 characters. If shorter, returns `minChars` error.

#### `TagFilterRequest`

**Routes:** `GET /eventos`, `GET /articulos`, `GET /galeria`

| Field | Rules |
|-------|-------|
| `tags` | `nullable`, `string` (comma-separated slugs) |
| `page` | `nullable`, `integer`, `min:1` |

#### `CalendarRequest`

**Route:** `GET /eventos/calendario`

| Field | Rules |
|-------|-------|
| `start` | `required`, `date` |
| `end` | `required`, `date` |

### 5.2. Authentication FormRequests

#### `RegisterRequest`

**Route:** `POST /registro`

| Field | Rules |
|-------|-------|
| `name` | `required`, `string`, `max:255` |
| `email` | `required`, `string`, `email`, `max:255`, `unique:users,email` |
| `password` | `required`, `confirmed`, `Password::min(8)` |

#### `LoginRequest`

**Route:** `POST /iniciar-sesion`

| Field | Rules |
|-------|-------|
| `email` | `required`, `string`, `email` |
| `password` | `required`, `string` |
| `remember` | `boolean` |

#### `ForgotPasswordRequest`

**Route:** `POST /olvide-contrasena`

| Field | Rules |
|-------|-------|
| `email` | `required`, `string`, `email` |

#### `ResetPasswordRequest`

**Route:** `POST /restablecer-contrasena`

| Field | Rules |
|-------|-------|
| `token` | `required`, `string` |
| `email` | `required`, `string`, `email` |
| `password` | `required`, `confirmed`, `Password::min(8)` |

#### `UpdateProfileRequest`

**Route:** `POST /perfil`

| Field | Rules |
|-------|-------|
| `name` | `required`, `string`, `max:255` |
| `display_name` | `nullable`, `string`, `max:255` |
| `email` | `required`, `string`, `email`, `max:255`, `unique:users,email` (except current user) |
| `avatar` | `nullable`, `image`, `max:2048` (2 MB) |

#### `ChangePasswordRequest`

**Route:** `PUT /perfil/contrasena`

| Field | Rules |
|-------|-------|
| `current_password` | `required`, `string`, `current_password` |
| `password` | `required`, `confirmed`, `Password::min(8)` |

### 5.3. Module FormRequests

#### `RegisterToEventRequest` (event-registrations)

**Route:** `POST /eventos/{eventId}/inscripcion`

| Field | Rules |
|-------|-------|
| `form_data` | `sometimes`, `array` |
| `form_data.*` | `nullable`, `string`, `max:1000` |
| `notes` | `nullable`, `string`, `max:500` |

Requires authentication (authorize returns `$this->user() !== null`).

#### `RegisterParticipantRequest` (tournaments)

**Route:** `POST /torneos/{tournamentId}/inscripcion`

Conditional rules based on authentication:

**If not authenticated (guest):**

| Field | Rules |
|-------|-------|
| `guest_name` | `required`, `string`, `max:255` |
| `guest_email` | `required`, `email`, `max:255` |
| `gdpr_consent` | `required`, `accepted` |

**If authenticated:**

| Field | Rules |
|-------|-------|
| `guest_name` | `nullable`, `string`, `max:255` |
| `guest_email` | `nullable`, `email`, `max:255`, `required_with:guest_name` |

#### `CheckInRequest` (tournaments)

**Route:** `POST /torneos/{slug}/check-in`

**If authenticated:** no required fields.

**If not authenticated:**

| Field | Rules |
|-------|-------|
| `email` | `required`, `email`, `max:255` |
| `gdpr_consent` | `required`, `accepted` |

#### `RegisterRequest` (game-tables)

**Route:** `POST /mesas/{gameTable}/inscripcion`

| Field | Rules |
|-------|-------|
| `role` | `sometimes`, `string`, `in:` values of `ParticipantRole` |
| `notes` | `nullable`, `string`, `max:500` |

Requires authentication.

#### `GuestRegisterRequest` (game-tables)

**Route:** `POST /mesas/{gameTable}/inscripcion-invitado`

| Field | Rules |
|-------|-------|
| `first_name` | `required`, `string`, `max:100` |
| `email` | `required`, `email`, `max:255` |
| `phone` | `nullable`, `string`, `max:20` |
| `role` | `sometimes`, `string`, `in: player, spectator` |
| `notes` | `nullable`, `string`, `max:500` |
| `gdpr_consent` | `required`, `accepted` |

Does not require authentication.

#### `FrontendCreateGameTableRequest` (game-tables)

**Routes:** `POST /mesas/crear`, `PUT /mesas/mis-mesas/{gameTable}`

| Field | Rules |
|-------|-------|
| `game_system_id` | `required`, `uuid`, `exists:gametables_game_systems,id` |
| `title` | `required`, `string`, `min:5`, `max:200` |
| `starts_at` | `required`, `date`, `after:now` |
| `duration_minutes` | `required`, `integer`, `min:30`, `max:720` |
| `table_type` | `required`, `string`, `in:` values of `TableType` |
| `table_format` | `required`, `string`, `in:` values of `TableFormat` |
| `min_players` | `required`, `integer`, `min:1`, `max:20` |
| `max_players` | `required`, `integer`, `min:1`, `max:20`, `gte:min_players` |
| `max_spectators` | `sometimes`, `integer`, `min:0`, `max:50` |
| `event_id` | `nullable`, `uuid`, `exists:events,id` |
| `campaign_id` | `nullable`, `uuid`, `exists:gametables_campaigns,id` |
| `synopsis` | `nullable`, `string`, `max:5000` |
| `location` | `nullable`, `string`, `max:500` |
| `online_url` | `nullable`, `url`, `max:500` |
| `minimum_age` | `nullable`, `integer`, `min:0`, `max:21` |
| `language` | `required`, `string`, `size:2` |
| `genres` | `nullable`, `array` of `Genre` values |
| `tone` | `nullable`, `string`, `in:` values of `Tone` |
| `experience_level` | `required`, `string`, `in:` values of `ExperienceLevel` |
| `character_creation` | `required`, `string`, `in:` values of `CharacterCreation` |
| `safety_tools` | `nullable`, `array` of `SafetyTool` values |
| `content_warning_ids` | `nullable`, `array` of UUIDs (`exists:gametables_content_warnings,id`) |
| `custom_warnings` | `nullable`, `array` of `string` (`max:200`) |
| `game_masters` | `nullable`, `array`, `min:1` |
| `game_masters.*.user_id` | `nullable`, `uuid` |
| `game_masters.*.first_name` | `required_without:game_masters.*.user_id`, `nullable`, `string`, `max:100` |
| `game_masters.*.last_name` | `nullable`, `string`, `max:100` |
| `game_masters.*.email` | `required_without:game_masters.*.user_id`, `nullable`, `email`, `max:255` |
| `game_masters.*.custom_title` | `nullable`, `string`, `max:100` |
| `game_masters.*.is_name_public` | `boolean` |
| `game_masters.*.role` | `required`, `string`, `in:` values of `GameMasterRole` |
| `notes` | `nullable`, `string`, `max:2000` |

Requires authentication.

#### `FrontendCreateCampaignRequest` (game-tables)

**Routes:** `POST /campanas/crear`, `PUT /campanas/mis-campanas/{id}`

| Field | Rules |
|-------|-------|
| `game_system_id` | `required`, `uuid`, `exists:gametables_game_systems,id` |
| `title` | `required`, `string`, `min:5`, `max:200` |
| `synopsis` | `nullable`, `string`, `max:5000` |
| `frequency` | `required`, `string`, `in:` values of `CampaignFrequency` |
| `schedule_notes` | `nullable`, `string`, `max:500` |
| `min_players` | `required`, `integer`, `min:1`, `max:12` |
| `max_players` | `required`, `integer`, `min:1`, `max:12`, `gte:min_players` |
| `table_format` | `required`, `string`, `in:` values of `TableFormat` |
| `location` | `nullable`, `string`, `max:500` |
| `online_url` | `nullable`, `url`, `max:500` |
| `language` | `sometimes`, `string`, `size:2` |
| `genres` | `nullable`, `array` of `Genre` values |
| `tone` | `nullable`, `string`, `in:` values of `Tone` |
| `experience_level` | `nullable`, `string`, `in:` values of `ExperienceLevel` |
| `character_creation` | `nullable`, `string`, `in:` values of `CharacterCreation` |
| `safety_tools` | `nullable`, `array` of `SafetyTool` values |
| `content_warning_ids` | `nullable`, `array` of UUIDs (`exists:gametables_content_warnings,id`) |
| `custom_warnings` | `nullable`, `array` of `string` (`max:200`) |

Requires authentication.

#### `SaveConsentRequest` (cookie-consent)

**Route:** `POST /consentimiento-cookies/consentir`

| Field | Rules |
|-------|-------|
| `visitor_id` | `required`, `uuid` |
| `preferences` | `required`, `array` |
| `preferences.*` | `required`, `boolean` |
| `config_version` | `required`, `integer`, `min:1` |
| `consent_method` | `sometimes`, `string`, `in: banner, settings_page, api` |

Does not require authentication.

#### `StoreBookingRequest` (venue-bookings)

**Route:** `POST /reservas`

| Field | Rules |
|-------|-------|
| `resource_id` | `required`, `string`, `uuid` |
| `date` | `required`, `date_format:Y-m-d` |
| `start_time` | `required`, `date_format:H:i` |
| `end_time` | `required`, `date_format:H:i` |
| `field_values` | `nullable`, `array` |
| `event_id` | `nullable`, `string`, `uuid` |
| `game_table_id` | `nullable`, `string` |
| `tournament_id` | `nullable`, `string` |
| `campaign_id` | `nullable`, `string` |

Additional dynamic field rules generated by `BookingFieldConfigServiceInterface` based on the selected resource.

Requires authentication.

#### `GetAnnouncementsRequest` (announcements)

**Route:** `GET /anuncios`

No validation fields (public endpoint without parameters).

---

## 6. HTTP resources

### 6.1. Core resources

#### `EventResource`

**Source:** `App\Http\Resources\EventResource`
**DTO:** `EventResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string;
  startDate: string;        // ISO 8601
  endDate: string;          // ISO 8601
  location: string | null;
  memberPrice: number | null;
  nonMemberPrice: number | null;
  imagePublicId: string | null;
  isPublished: boolean;
  createdAt: string | null;  // ISO 8601
  updatedAt: string | null;  // ISO 8601
  downloadLinks: unknown;
  tags: TagResource[];
}
```

#### `CalendarEventResource`

**Source:** `App\Http\Resources\CalendarEventResource`
**DTO:** `EventResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string;
  start: string;            // ISO 8601
  end: string;              // ISO 8601
  location: string | null;
  imagePublicId: string | null;
  memberPrice: number | null;
  nonMemberPrice: number | null;
  url: string;              // "/eventos/{slug}"
  tags: Array<{
    id: string;
    name: string;
    slug: string;
    color: string | null;
    parentId: string | null;
  }>;
}
```

#### `ArticleResource`

**Source:** `App\Http\Resources\ArticleResource`
**DTO:** `ArticleResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  featuredImagePublicId: string | null;
  isPublished: boolean;
  publishedAt: string | null;  // ISO 8601
  author: AuthorResource | null;
  createdAt: string | null;    // ISO 8601
  updatedAt: string | null;    // ISO 8601
  tags: TagResource[];
}
```

#### `AuthorResource`

**Source:** `App\Http\Resources\AuthorResource`
**DTO:** `AuthorResponseDTO`

```typescript
{
  id: string;
  name: string;
  displayName: string | null;
  avatarPublicId: string | null;
}
```

#### `GalleryResource`

**Source:** `App\Http\Resources\GalleryResource`
**DTO:** `GalleryResponseDTO`

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
  createdAt: string | null;  // ISO 8601
  updatedAt: string | null;  // ISO 8601
  tags: TagResource[];
}
```

#### `GalleryWithPhotosResource`

**Source:** `App\Http\Resources\GalleryWithPhotosResource`
**DTO:** `GalleryDetailResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
  description: string | null;
  isPublished: boolean;
  isFeatured: boolean;
  photoCount: number;
  createdAt: string | null;  // ISO 8601
  updatedAt: string | null;  // ISO 8601
  photos: PhotoResource[];
  tags: TagResource[];
}
```

#### `PhotoResource`

**Source:** `App\Http\Resources\PhotoResource`
**DTO:** `PhotoResponseDTO`

```typescript
{
  id: string;
  imagePublicId: string;
  caption: string | null;
  sortOrder: number;
}
```

#### `HeroSlideResource`

**Source:** `App\Http\Resources\HeroSlideResource`
**DTO:** `HeroSlideResponseDTO`

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

**Source:** `App\Http\Resources\TagResource`
**DTO:** `TagResponseDTO`

```typescript
{
  id: string;
  name: string;
  slug: string;
  parentId: string | null;
  parentName: string | null;
  appliesTo: string;
  color: string | null;
  sortOrder: number;
}
```

### 6.2. Module resources

#### `TournamentResource` (tournaments)

**Source:** `Modules\Tournaments\Http\Resources\TournamentResource`
**DTO:** `TournamentResponseDTO`

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
  registrationOpensAt: string | null;   // ISO 8601
  registrationClosesAt: string | null;  // ISO 8601
  startedAt: string | null;            // ISO 8601
  completedAt: string | null;          // ISO 8601
  isRegistrationOpen: boolean;
  isInProgress: boolean;
  isFinished: boolean;
  hasCapacity: boolean;
}
```

#### `GameTableResource` (game-tables)

**Source:** `Modules\GameTables\Http\Resources\GameTableResource`
**DTO:** `GameTableResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
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
  startsAt: string | null;             // ISO 8601
  durationMinutes: number;
  location: string | null;
  onlineUrl: string | null;
  minPlayers: number;
  maxPlayers: number;
  maxSpectators: number;
  minimumAge: number | null;
  language: string | null;
  languageLabel: string | null;
  experienceLevel: string | null;
  experienceLevelLabel: string | null;
  characterCreation: string | null;
  characterCreationLabel: string | null;
  genres: string[];
  tone: string | null;
  toneLabel: string | null;
  safetyTools: string[];
  contentWarnings: string[];
  customWarnings: string[];
  registrationType: string;
  registrationTypeLabel: string;
  membersEarlyAccessDays: number | null;
  registrationOpensAt: string | null;   // ISO 8601
  registrationClosesAt: string | null;  // ISO 8601
  autoConfirm: boolean;
  acceptsRegistrationsInProgress: boolean;
  isPublished: boolean;
  publishedAt: string | null;           // ISO 8601
  notes: string | null;
  imagePublicId: string | null;
  gameMasters: GameMasterResource[];
  mainGameMasterName: string;
  currentPlayers: number;
  currentSpectators: number;
  spotsAvailable: number;
  spectatorSpotsAvailable: number;
  waitingListCount: number;
  createdAt: string | null;             // ISO 8601
  updatedAt: string | null;             // ISO 8601
}
```

#### `GameTableListResource` (game-tables)

**Source:** `Modules\GameTables\Http\Resources\GameTableListResource`
**DTO:** `GameTableListDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
  gameSystemName: string;
  startsAt: string | null;    // ISO 8601
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
  mainGameMasterName: string | null;
  eventId: string | null;
  eventTitle: string | null;
  imagePublicId: string | null;
}
```

#### `GameMasterResource` (game-tables)

**Source:** `Modules\GameTables\Http\Resources\GameMasterResource`
**DTO:** `GameMasterResponseDTO`

```typescript
{
  id: string;
  gameTableId: string;
  userId: string | null;
  displayName: string;
  role: string;
  roleLabel: string;
  customTitle: string | null;
  isMain: boolean;
  isNamePublic: boolean;
}
```

#### `CampaignResource` (game-tables)

**Source:** `Modules\GameTables\Http\Resources\CampaignResource`
**DTO:** `CampaignResponseDTO`

```typescript
{
  id: string;
  title: string;
  slug: string;
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
  sessionCount: number;
  currentSession: number | null;
  totalSessions: number | null;
  imagePublicId: string | null;
  gameMasters: CampaignGameMasterResource[];
  gameTables: GameTableListResource[];
  hasActiveOrUpcomingTables: boolean;
  mainGameMasterName: string;
  createdAt: string | null;   // ISO 8601
  updatedAt: string | null;   // ISO 8601
}
```

#### `CampaignGameMasterResource` (game-tables)

**Source:** `Modules\GameTables\Http\Resources\CampaignGameMasterResource`
**DTO:** `CampaignGameMasterResponseDTO`

```typescript
{
  id: string;
  campaignId: string;
  userId: string | null;
  displayName: string;
  role: string;
  roleLabel: string;
  customTitle: string | null;
  isMain: boolean;
  isNamePublic: boolean;
}
```

---

## 7. Middleware

### Custom middleware

| Middleware | Alias | File | Purpose |
|-----------|-------|------|---------|
| `HandleInertiaRequests` | -- (global) | `App\Http\Middleware\HandleInertiaRequests` | Injects Inertia shared data into every response (auth, theme, navigation, modules, flash messages, etc.). Excludes admin and Livewire routes. |
| `SecurityHeadersMiddleware` | -- (global) | `App\Http\Middleware\SecurityHeadersMiddleware` | Sets HTTP security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy`, `Strict-Transport-Security` (HTTPS). |
| `BlockBotsMiddleware` | -- (global) | `App\Http\Middleware\BlockBotsMiddleware` | Blocks unwanted bots based on User-Agent. Configurable via `config('bot-protection')`. Allows legitimate search engine bots. |
| `EnsureRegistrationIsEnabled` | `registration.enabled` | `App\Http\Middleware\EnsureRegistrationIsEnabled` | Redirects to `/` if user registration is disabled in site settings. Protects `GET /registro` and `POST /registro` routes. |
| `EnsureLoginIsEnabled` | `login.enabled` | `App\Http\Middleware\EnsureLoginIsEnabled` | Redirects to `/` if login is disabled in site settings. Protects `GET /iniciar-sesion` and `POST /iniciar-sesion` routes. |

### Laravel middleware

| Middleware | Purpose |
|-----------|---------|
| `web` | Middleware group for web routes (session, CSRF, cookies, etc.) |
| `api` | Middleware group for API routes (stateless, throttle) |
| `auth` | Verifies the user is authenticated |
| `guest` | Verifies the user is not authenticated (redirects to home if they are) |
| `signed` | Verifies the URL has a valid signature |
| `throttle:X,Y` | Limits requests to X attempts every Y minutes |
| `throttle:contact` | Specific rate limiter for the contact form |
| `throttle:login` | Specific rate limiter for login attempts |

### Rate limiting

| Name | Limit | Scope | Routes |
|------|-------|-------|--------|
| `login` | 5 attempts/min | email + IP | `POST /iniciar-sesion` |
| `contact` | 3 submissions/min | IP | `POST /contacto` |
| `throttle:5,1` | 5 requests/min | IP | `POST /registro`, `POST /olvide-contrasena` |
| `throttle:6,1` | 6 requests/min | IP | `POST /verificar-email/reenviar` |

Source: `AppServiceProvider::boot()` (rate limiters `login` and `contact`).

---

## Appendix: paginated response structure

Paginated listings (events, articles, galleries, tournaments, game tables, campaigns) follow this common structure generated by the `BuildsPaginatedResponse` trait:

```typescript
{
  data: ResourceType[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}
```

## Appendix: date convention

All dates are returned in ISO 8601 format (`"c"` in PHP), for example: `2026-03-21T14:30:00+01:00`.

## Appendix: common error codes

| Code | Meaning | Typical usage |
|------|---------|---------------|
| `200` | Success | Successful JSON responses |
| `201` | Created | Successful registration (registrations, consent) |
| `301` | Permanent redirect | Old slugs redirected to current one |
| `400` | Bad request | Tournament full, tournament closed, cannot withdraw |
| `401` | Not authenticated | Access without session to protected routes |
| `403` | Forbidden | Invalid SNS signature, user without permission |
| `404` | Not found | Non-existent resource |
| `422` | Unprocessable entity | Validation errors, registration closed, already registered |

## Appendix: error responses by endpoint

### Core routes

| Endpoint | 422 | 429 | Other |
|----------|-----|-----|-------|
| `POST /contacto` | Field validation (name, email, message) | `throttle:contact` (3/min) | -- |
| `POST /registro` | Validation (name, unique email, password) | `throttle:5,1` | -- |
| `POST /iniciar-sesion` | Validation (email, password) | `throttle:login` (5/min) | -- |
| `POST /olvide-contrasena` | Validation (email) | `throttle:5,1` | -- |
| `POST /restablecer-contrasena` | Validation (token, email, password) | -- | -- |
| `POST /perfil` | Validation (name, unique email, avatar) | -- | 401 (not authenticated) |
| `PUT /perfil/contrasena` | Validation (current_password, password) | -- | 401 (not authenticated) |
| `POST /verificar-email/reenviar` | -- | `throttle:6,1` | 401 (not authenticated) |

### Module routes

| Endpoint | 422 | 400 | Other |
|----------|-----|-----|-------|
| `POST /eventos/{id}/inscripcion` | `RegistrationClosedException`, `EventFullException`, `AlreadyRegisteredException` | -- | 401 (not authenticated) |
| `DELETE /eventos/{id}/inscripcion` | -- | -- | 401 (not authenticated) |
| `POST /torneos/{id}/inscripcion` | Field validation (guest_name, guest_email, gdpr_consent) | Tournament full, tournament closed | -- |
| `DELETE /torneos/{id}/inscripcion` | -- | Cannot withdraw | 401 (not authenticated) |
| `POST /mesas/{id}/inscripcion` | Validation (role, notes) | -- | 401 (not authenticated) |
| `POST /mesas/{id}/inscripcion-invitado` | Validation (first_name, email, gdpr_consent) | -- | -- |
| `POST /mesas/crear` | Extensive validation (see section 5.3) | -- | 401 (not authenticated) |
| `PUT /mesas/mis-mesas/{id}` | Extensive validation (see section 5.3) | -- | 401, 403 |
| `POST /reservas` | Validation (resource_id, date, times) + dynamic fields | -- | 401 (not authenticated) |
| `DELETE /reservas/{booking}` | -- | -- | 401, 403 |
| `POST /consentimiento-cookies/consentir` | Validation (visitor_id, preferences, config_version) | -- | -- |
