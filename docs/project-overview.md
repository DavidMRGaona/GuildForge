# Project overview

> Generated: 2026-03-21 | Project: GuildForge v1.0.0

## 1. Executive summary

**GuildForge** is a web platform for managing board game, wargame, and role-playing game guilds. It combines a public frontend for visitors (events, articles, photo galleries, calendar) with a full admin panel for content management by guild members.

The project follows a Clean Architecture with an extensible module system that allows adding features without modifying the core.

**Repository**: https://github.com/DavidMRGaona/GuildForge.git

---

## 2. Tech stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Backend** | PHP / Laravel | ^8.4 / ^12.0 | MVC framework with IoC |
| **Admin panel** | Filament | ^3.2 | CRUD and admin management |
| **Frontend bridge** | Inertia.js | ^2.0 | SSR without a separate REST API |
| **Frontend** | Vue 3 / TypeScript | ^3.5 / ^5.9 | Reactive UI (Composition API, strict) |
| **Build and styling** | Vite / Tailwind CSS | ^7.0 / ^4.0 | Bundler, HMR, utility-first CSS |
| **State and i18n** | Pinia / vue-i18n | ^3.0 / ^11.2 | State management, internationalization |
| **Database** | PostgreSQL / SQLite | 16 / in-memory | Production / testing |
| **Cache and queues** | Redis | Alpine | Cache, sessions, job queues |
| **Images** | Cloudinary | ^3.0 | Storage, CDN, optimization |
| **Email** | Resend / AWS SES | ^1.1 / ^3.369 | Transactional email |
| **Observability** | Elasticsearch / Kibana | 9.2.4 | Centralized logging and visualization |
| **Containers** | Docker + Compose | - | Development and production |

For the full list of dependencies (linters, testing, UI libraries), see `src/composer.json` and `src/package.json`.

---

## 3. Architecture type

### Clean Architecture

```
Domain (Entities, Value Objects, Enums, Events, Repository Interfaces)
    ↑
Application (DTOs, Service Interfaces, Factories, Query Services)
    ↑
Infrastructure (Eloquent Models, Service Implementations, External Adapters)
    ↑
Presentation (Controllers, Filament Resources, Vue Components, Middleware)
```

**Fundamental rule**: inner layers NEVER import from outer layers.

### Key patterns

- **CQRS-lite**: Query Services (reads) separated from Repositories (writes)
- **DTO pattern**: Transformation via ResponseDTOFactory
- **Repository pattern**: Interfaces in Domain, implementations in Infrastructure
- **Service pattern**: Interfaces in Application, implementations in Infrastructure
- **Policy-based authorization**: AuthorizesWithPermissions trait
- **Module system**: Optional extensions with independent service providers

---

## 4. Repository structure

**Type**: Monolith with modular extensions

For the full annotated directory tree, see [Source tree analysis](./source-tree-analysis.md).

---

## 5. Available modules

| Module | Description | DB tables | Web routes |
|--------|-------------|-----------|-----------|
| **announcements** | Guild announcements system | 1 | 2 |
| **channel-notifications** | Channel notifications (Discord, Telegram, Slack) | 0 | 0 |
| **cookie-consent** | Cookie consent management (GDPR) | 4 | 3 |
| **event-registrations** | Event registrations with waitlist | 2 | 5 |
| **game-tables** | RPG/wargame table and campaign management | 11 | 25+ |
| **memberships** | Membership system with dues and types | 4 | 0 |
| **security-test** | Development module to validate core table protection against module migrations | 1 | 0 |
| **tournaments** | Tournaments with Swiss pairing and rankings | 7 | 12 |
| **venue-bookings** | Venue and room bookings | 3 | 8 |

Each module is an independent package with its own Clean Architecture structure, migrations, routes, tests, Filament resources, and Vue frontend.

---

## 6. External services

| Service | Purpose | Configuration |
|---------|---------|---------------|
| **Cloudinary** | Image storage and CDN | `CLOUDINARY_URL` in .env |
| **Resend** | Transactional email delivery | `RESEND_API_KEY` in .env |
| **AWS SES** | Alternative for email + bounce webhooks | `AWS_*` in .env |
| **Elasticsearch** | Centralized logging and search | `ELASTICSEARCH_HOST` in .env |
| **Redis** | Application cache and job queues | `REDIS_HOST` in .env |
| **GitHub API** | Module update checking | Via public API |

---

## 7. Main features

### Public frontend
- **Home**: Hero slider, upcoming events, recent articles, featured gallery
- **Events**: Listing with tag filters, detail view, interactive calendar
- **Articles**: Blog with author, tags, rich text
- **Gallery**: Photo grid with lightbox
- **Search**: Global search across events and articles
- **Calendar**: Interactive calendar view with detail panel
- **Contact**: Form with honeypot protection
- **Authentication**: Registration, login, email verification, password recovery
- **Profile**: Account management with module-extensible tabs
- **Legal pages**: Privacy, terms, cookies, legal notice
- **Dark theme**: Light/dark/system mode with persistence
- **SEO**: Meta tags, OG tags, XML sitemap

### Admin panel (Filament)
- **Dashboard**: Configurable widgets (events, articles, galleries, mail, modules)
- **Content**: CRUD for events, articles, galleries, hero slides, tags
- **Users**: User and role management with granular permissions
- **Navigation**: Menu editor (header/footer) with hierarchy
- **Settings**: Theme, branding, contact, legal pages, email, dashboard
- **Modules**: Module management, updates, per-module configuration
