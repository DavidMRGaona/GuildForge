# Data models

> Verified on 2026-10-13 against core 3.5.1 and the module repositories (announcements 2.1.0, channel-notifications 2.1.0, cookie-consent 2.1.1, event-registrations 2.1.0, game-tables 2.3.0, memberships 2.1.0, tournaments 2.2.2, venue-bookings 2.2.0). The source of truth is the migrations: core in `src/database/migrations`, modules in `src/modules/<name>/database/migrations`. Columns below are the final state after every migration.

## 1. Conventions

- **Primary keys**: every GuildForge table uses a UUID primary key (`$table->uuid('id')->primary()`) and the Eloquent models of those tables use `HasUuids`. Exceptions: pivot tables (composite primary key), per-event configuration tables keyed by `event_id` (`event_registrations_configs`, `game_tables_event_configs`), `password_reset_tokens` (keyed by `email`), `sessions` (string id) and the Laravel framework tables (`cache`, `cache_locks` and `job_batches` use string keys; `jobs` and `failed_jobs` use auto-increment ids).
- **Module tables** carry the module prefix (`gametables_`, `tournaments_`, `venuebookings_`...). Modules never change core tables; they add relations to core models at runtime with `EventModel::resolveRelationUsing()` (see §2).
- **Enum columns** are plain `string` columns holding the backed value of a PHP enum (§6).
- `created_at` / `updated_at` (`timestamps()`, nullable) exist on every table unless the table says otherwise.

## 2. Relationship diagram

Lines follow foreign keys; `(no FK)` marks a column that references another table without a database constraint.

```
users ──┬── articles.author_id (CASCADE)
        ├── user_role ── roles ── role_permission ── permissions
        ├── sessions.user_id (no FK)
        ├── event_registrations_registrations.user_id (CASCADE)
        ├── memberships_members.user_id (SET NULL)
        ├── gametables_campaigns.created_by / gametables_tables.created_by (CASCADE)
        ├── gametables_participants.user_id (CASCADE)
        ├── gametables_game_masters.user_id (SET NULL)
        ├── gametables_campaign_players.user_id (CASCADE)
        ├── tournaments_participants.user_id (CASCADE)
        ├── tournaments_match_history.changed_by_id (CASCADE)
        └── venuebookings_bookings.user_id (CASCADE)

events ──┬── event_tag ── tags (self-referencing parent_id)
         ├── event_registrations_registrations.event_id (CASCADE)
         ├── event_registrations_configs.event_id (PK, CASCADE)
         ├── game_tables_event_configs.event_id (PK, CASCADE)
         ├── gametables_tables.event_id (SET NULL)
         ├── tournaments_tournaments.event_id (UNIQUE, CASCADE)
         └── venuebookings_bookings.event_id (SET NULL)

articles ── article_tag ── tags
galleries ──┬── photos.gallery_id (CASCADE)
            └── gallery_tag ── tags
menu_items.parent_id ── menu_items (CASCADE)
module_update_history ── module_update_logs.update_history_id (CASCADE)

gametables_publishers ── gametables_game_systems.publisher_id (SET NULL)
gametables_game_systems ──┬── gametables_campaigns.game_system_id (CASCADE)
                          └── gametables_tables.game_system_id (CASCADE)
gametables_campaigns ──┬── gametables_tables.campaign_id (SET NULL)
                       ├── gametables_campaign_gm ── gametables_game_masters
                       └── gametables_campaign_players ── users
gametables_tables ──┬── gametables_participants.game_table_id (CASCADE)
                    ├── gametables_table_gm ── gametables_game_masters
                    └── gametables_table_content_warnings ── gametables_content_warnings

tournaments_game_profiles ── tournaments_tournaments.game_profile_id (SET NULL)
tournaments_tournaments ──┬── tournaments_participants (CASCADE)
                          ├── tournaments_rounds (CASCADE) ── tournaments_matches (CASCADE) ── tournaments_match_history (CASCADE)
                          └── tournaments_standings (CASCADE)
tournaments_participants ──┬── tournaments_matches.player_1_id / player_2_id (CASCADE)
                           └── tournaments_standings.participant_id (CASCADE)

memberships_members ── memberships_memberships (CASCADE) ── memberships_fees (CASCADE)

venuebookings_resources ──┬── venuebookings_operating_schedules.resource_id (UNIQUE, CASCADE)
                          └── venuebookings_bookings.resource_id (CASCADE)

cookie_consent_categories ──┬── cookie_consent_cookies.category_id (CASCADE)
                            └── cookie_consent_scripts.category_id (CASCADE)
cookie_consent_consents (visitor_id, user_id: no FK)
```

Relations added at runtime to `EventModel` by modules (`resolveRelationUsing()` in each service provider):

| Relation | Module |
|----------|--------|
| `registrations`, `registrationConfig` | event-registrations |
| `gameTables`, `gameTableConfig` | game-tables |
| `tournament` | tournaments |

---

## 3. Core tables

Core migrations create 27 application tables plus the Laravel framework tables `cache`, `cache_locks`, `jobs`, `job_batches` and `failed_jobs`. Models live in `App\Infrastructure\Persistence\Eloquent\Models` unless noted.

### Users and authorization

#### users

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| display_name | string | nullable |
| email | string | unique |
| pending_email | string | nullable (new address waiting for verification) |
| email_verified_at | timestamp | nullable |
| password | string | |
| avatar_public_id | string | nullable |
| role | string | default `member` (`UserRole`) |
| remember_token | string(100) | nullable |
| created_at / updated_at | timestamp | nullable |
| deleted_at | timestamp | nullable (soft delete) |
| anonymized_at | timestamp | nullable |

- Model: `UserModel` (`SoftDeletes`, `DeletesCloudinaryImages` on `avatar_public_id`). Entity: `App\Domain\Entities\User`.
- Relations: `roles()` BelongsToMany `RoleModel` through `user_role`. Permissions are resolved from the roles, not from the `role` column.

#### password_reset_tokens

| Column | Type | Constraints |
|--------|------|-------------|
| email | string | PK |
| token | string | |
| created_at | timestamp | nullable |

#### sessions

| Column | Type | Constraints |
|--------|------|-------------|
| id | string | PK |
| user_id | uuid | nullable, index (no FK constraint) |
| ip_address | string(45) | nullable |
| user_agent | text | nullable |
| payload | longText | |
| last_activity | integer | index |

#### permissions

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| key | string | unique (`resource.action`) |
| label | string | |
| resource | string | index |
| action | string | |
| module | string | nullable, index (module that registered it) |
| created_at / updated_at | timestamp | nullable |

- Model: `PermissionModel` (`roles()` BelongsToMany through `role_permission`). Entity: `App\Domain\Authorization\Entities\Permission`.

#### roles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | unique, index |
| display_name | string | |
| description | text | nullable |
| is_protected | boolean | default false |
| created_at / updated_at | timestamp | nullable |

- Model: `RoleModel` (`permissions()` and `users()` BelongsToMany). Entity: `App\Domain\Authorization\Entities\Role`.

#### role_permission, user_role

| Table | Columns | Keys |
|-------|---------|------|
| role_permission | `role_id`, `permission_id`, timestamps | composite PK; both FK CASCADE |
| user_role | `user_id`, `role_id`, timestamps | composite PK; both FK CASCADE |

### Content

#### events

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| title | string | |
| slug | string | unique |
| description | text | |
| start_date | dateTime | index |
| end_date | dateTime | |
| location | string | nullable |
| member_price | decimal(8,2) | nullable |
| non_member_price | decimal(8,2) | nullable |
| image_public_id | string | nullable |
| download_links | json | nullable (added by `2026_02_27_115706`) |
| is_published | boolean | default false, index |
| created_at / updated_at | timestamp | nullable |

- Model: `EventModel` (`HasSlug`, `DeletesCloudinaryImages` on `image_public_id`). Entity: `App\Domain\Entities\Event`.
- Relations: `tags()` BelongsToMany `TagModel` through `event_tag`; module relations in §2.

#### articles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| title | string | |
| slug | string | unique |
| content | text | |
| excerpt | string | nullable |
| featured_image_public_id | string | nullable |
| is_published | boolean | default false, index |
| published_at | timestamp | nullable, index |
| author_id | uuid | FK → users CASCADE, index |
| created_at / updated_at | timestamp | nullable |

- Model: `ArticleModel` (`DeletesCloudinaryImages` on `featured_image_public_id`). Entity: `App\Domain\Entities\Article`.
- Relations: `author()` BelongsTo `UserModel`; `tags()` BelongsToMany through `article_tag`.

#### galleries

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| title | string | |
| slug | string | unique |
| description | text | nullable |
| cover_image_public_id | string | nullable |
| is_published | boolean | default false, index |
| is_featured | boolean | default false |
| created_at / updated_at | timestamp | nullable |

- Model: `GalleryModel`. Entity: `App\Domain\Entities\Gallery`.
- Relations: `photos()` HasMany `PhotoModel` (ordered by `sort_order`); `tags()` BelongsToMany through `gallery_tag`.

#### photos

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| gallery_id | uuid | FK → galleries CASCADE |
| image_public_id | string | |
| caption | string | nullable |
| sort_order | integer | default 0 |
| created_at / updated_at | timestamp | nullable |

- Index `(gallery_id, sort_order)`.
- Model: `PhotoModel` (`DeletesCloudinaryImages` on `image_public_id`; `gallery()` BelongsTo). Entity: `App\Domain\Entities\Photo`.

#### tags

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| slug | string | unique |
| parent_id | uuid | nullable, index, FK → tags SET NULL |
| applies_to | json | array of content types the tag can be used on |
| color | string(7) | default `#6b7280` |
| description | text | nullable |
| sort_order | integer | default 0, index |
| created_at / updated_at | timestamp | nullable |

- Model: `TagModel` (`parent()`, `children()`, `events()`, `articles()`, `galleries()`).

#### event_tag, article_tag, gallery_tag

Same structure: `{resource}_id` uuid + `tag_id` uuid (composite PK, both FK CASCADE) and timestamps.

#### hero_slides

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| title | string | |
| subtitle | string | nullable |
| button_text | string | nullable |
| button_url | string | nullable |
| image_public_id | string | nullable |
| is_active | boolean | default false, index |
| sort_order | integer | default 0, index |
| created_at / updated_at | timestamp | nullable |

- Model: `HeroSlideModel` (`DeletesCloudinaryImages` on `image_public_id`).

#### slug_redirects

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| old_slug | string | index |
| new_slug | string | index |
| entity_type | string | |
| entity_id | uuid | index |
| created_at / updated_at | timestamp | nullable |

- Unique `(old_slug, entity_type)`. Written by `HasSlug` when a slug changes. Model: `SlugRedirectModel`. Entity: `App\Domain\Entities\SlugRedirect`.

### Site configuration

#### settings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| key | string | unique |
| value | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Key/value store behind `SettingsServiceInterface` (site, theme, mail, maintenance, dashboard and module settings). Model: `SettingModel`.

#### menu_items

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| location | string | `MenuLocation` |
| parent_id | uuid | nullable, index, FK → menu_items CASCADE |
| label | string | |
| url | string | nullable |
| route | string | nullable |
| route_params | json | nullable |
| icon | string | nullable |
| target | string | default `_self` (`LinkTarget`) |
| visibility | string | default `public` (`MenuVisibility`) |
| permissions | json | nullable |
| sort_order | integer | default 0 |
| is_active | boolean | default true |
| module | string | nullable, index (module that contributed the item) |
| created_at / updated_at | timestamp | nullable |

- Index `(location, is_active, sort_order)`.
- Model: `App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel` (`parent()`, `children()`). Entity: `App\Domain\Navigation\Entities\MenuItem`.

### Modules and updates

#### modules

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | unique |
| display_name | string | nullable |
| version | string | |
| description | text | nullable |
| author | string | nullable |
| status | string | default `disabled`, index (`ModuleStatus`) |
| path | string | nullable |
| namespace | string | nullable |
| provider | string | nullable |
| requires | json | nullable |
| dependencies | json | nullable |
| source_owner | string | nullable (GitHub owner the module is updated from) |
| source_repo | string | nullable |
| latest_available_version | string | nullable |
| last_update_check_at | timestamp | nullable |
| discovered_at | timestamp | nullable |
| enabled_at | timestamp | nullable |
| installed_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |
| latest_blocked_version | string | nullable: newest release this core cannot run (added by `2026_10_05_000001`) |
| latest_blocked_reason | json | nullable: why that release is blocked, a list of `CompatibilityIssue::toArray()` |

- Model: `ModuleModel`. Entity: `App\Domain\Modules\Entities\Module`.

#### module_update_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| module_name | string | |
| from_version | string | |
| to_version | string | |
| status | string | index (`UpdateStatus`) |
| error_message | text | nullable |
| backup_path | string | nullable |
| started_at | timestamp | |
| completed_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |

- Index `(module_name, started_at)`. Model: `App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel` (`logs()` HasMany).

#### module_update_logs

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| update_history_id | uuid | FK → module_update_history CASCADE |
| step | string | |
| status | string | |
| message | text | nullable |
| context | json | nullable |
| created_at | timestamp | (no `updated_at`) |

- Index `(update_history_id, created_at)`. Model: `ModuleUpdateLogModel` (`history()` BelongsTo).

#### module_seeder_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| module_name | string | index |
| seeder_class | string | |
| executed_at | timestamp | (no timestamps) |

- Unique `(module_name, seeder_class)`. Migration `2026_10_04_000001` backfills it with the seeders of modules already installed. Model: `ModuleSeederHistoryModel`.

#### core_update_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| from_version | string | |
| to_version | string | |
| git_commit_before | string | |
| git_commit_after | string | nullable |
| status | string | index (`UpdateStatus`) |
| error_message | text | nullable |
| created_at / updated_at | timestamp | `created_at` indexed |

- Model: `CoreUpdateHistoryModel`.

#### core_seeder_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| seeder_class | string | unique |
| executed_at | timestamp | (no timestamps) |

- Model: `CoreSeederHistoryModel`.

### Mail

#### email_logs

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| recipient | string | index |
| sender | string | nullable |
| subject | string | nullable |
| mailer | string | nullable |
| status | string | `EmailStatus` |
| error_message | text | nullable |
| message_id | string | nullable (not unique) |
| metadata | json | nullable |
| sent_at | timestamp | nullable |
| created_at / updated_at | timestamp | `created_at` indexed |

- Index `(status, created_at)`. Model: `EmailLogModel`. Entity: `App\Domain\Mail\Entities\EmailLog`.

#### ses_usage_records

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| date | date | unique |
| emails_sent | integer | default 0 |
| estimated_cost | decimal(10,4) | default 0 |
| bounces_count | integer | default 0 |
| complaints_count | integer | default 0 |
| created_at / updated_at | timestamp | nullable |

- Model: `SesUsageRecordModel`. Bounce and complaint counters are incremented by `POST /api/webhooks/ses`.

### Data migrations

`2026_10_08_000001_convert_trix_rich_text` converts rich text written with the old Trix editor into TipTap HTML (`TrixContentMigratorInterface`; also available as `php artisan content:convert-trix`). It changes no schema and writes a JSON backup file, not a table.

---

## 4. Module tables

Each module owns its migrations (`src/modules/<name>/database/migrations`) and models (`Modules\<Name>\Infrastructure\Persistence\Eloquent\Models`). Enum values for the module columns are in §6.

| Module | Tables |
|--------|--------|
| announcements | 1 (+1 backup table) |
| channel-notifications | none (stores its configuration in `settings`) |
| cookie-consent | 4 |
| event-registrations | 2 |
| game-tables | 12 |
| memberships | 4 |
| tournaments | 7 |
| venue-bookings | 3 |

### announcements

#### announcements_announcements

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| title | string | |
| content | text | rich text (TipTap HTML) |
| visibility | string | default `public` (`AnnouncementVisibility`) |
| position | string | default `before-header` (`AnnouncementPosition`) |
| background_color | string | nullable |
| text_color | string | nullable |
| priority | unsignedTinyInteger | default 5 |
| starts_at | timestamp | nullable |
| ends_at | timestamp | nullable |
| is_active | boolean | default true |
| is_dismissible | boolean | default true |
| created_at / updated_at | timestamp | nullable |

- Index `(is_active, starts_at, ends_at)`. Model: `AnnouncementModel`.

#### announcements_trix_content_backup

Created by `2026_10_08_000001_convert_announcement_content_to_tiptap`, which converts Trix content to TipTap: `announcement_id` uuid (PK), `content` text (original Trix HTML), `created_at` timestamp nullable. No foreign key.

### channel-notifications

No tables. Channel credentials, enabled content types and message templates are stored in the core `settings` table through `SettingsServiceInterface` (secrets with `getEncrypted()`), keyed by each channel's settings prefix.

### cookie-consent

#### cookie_consent_categories

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| slug | string | unique |
| description | text | |
| is_required | boolean | default false |
| is_system | boolean | default false |
| sort_order | unsignedSmallInteger | default 0, index |
| consent_mode_keys | json | nullable (`ConsentModeKey` values) |
| created_at / updated_at | timestamp | nullable |

- Model: `CookieCategoryModel`.

#### cookie_consent_cookies

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| category_id | uuid | FK → cookie_consent_categories CASCADE |
| name | string | |
| provider | string | |
| domain | string | nullable |
| purpose | text | |
| type | string | default `first_party` (`CookieType`) |
| duration | string | nullable |
| is_active | boolean | default true |
| created_at / updated_at | timestamp | nullable |

- Index `(category_id, is_active)`. Model: `CookieModel`.

#### cookie_consent_scripts

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| category_id | uuid | FK → cookie_consent_categories CASCADE |
| name | string | |
| description | text | nullable |
| script_head | text | nullable |
| script_body_start | text | nullable |
| script_body_end | text | nullable |
| noscript_content | text | nullable |
| sort_order | unsignedSmallInteger | default 0 |
| is_active | boolean | default true |
| created_at / updated_at | timestamp | nullable |

- Index `(category_id, is_active)`. Model: `CookieScriptModel`.

#### cookie_consent_consents

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| visitor_id | uuid | |
| user_id | uuid | nullable, index (no FK constraint) |
| ip_hash | string(64) | |
| user_agent | text | |
| preferences | json | |
| config_version | unsignedInteger | |
| consent_method | string | `ConsentMethod` |
| consented_at | timestamp | |
| expires_at | timestamp | |
| created_at / updated_at | timestamp | nullable |

- Index `(visitor_id, consented_at)`. Model: `CookieConsentModel`.

### event-registrations

#### event_registrations_registrations

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| event_id | uuid | FK → events CASCADE |
| user_id | uuid | FK → users CASCADE, index |
| state | string | default `pending` (`RegistrationState`) |
| position | integer | nullable |
| form_data | json | nullable |
| notes | text | nullable |
| admin_notes | text | nullable |
| confirmed_at | timestamp | nullable |
| cancelled_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |

- Unique `(event_id, user_id)`; indexes `(event_id, state)`, `(event_id, position)`. Model: `EventRegistrationModel`.

#### event_registrations_configs

One row per event; `event_id` is the primary key (no `id` column).

| Column | Type | Constraints |
|--------|------|-------------|
| event_id | uuid | PK, FK → events CASCADE |
| registration_enabled | boolean | default true |
| max_participants | integer | nullable |
| waiting_list_enabled | boolean | default true |
| max_waiting_list | integer | nullable |
| registration_opens_at | timestamp | nullable |
| registration_closes_at | timestamp | nullable |
| cancellation_deadline | timestamp | nullable |
| requires_confirmation | boolean | default false |
| requires_payment | boolean | default false |
| members_only | boolean | default false |
| custom_fields | json | nullable |
| confirmation_message | text | nullable |
| notification_email | string | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `EventRegistrationConfigModel`.

### game-tables

#### gametables_publishers

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | index |
| slug | string | unique |
| description | text | nullable |
| website_url | string | nullable |
| logo_url | string | nullable |
| country | string | nullable |
| is_active | boolean | default true, index |
| created_at / updated_at | timestamp | nullable |

- Model: `PublisherModel`.

#### gametables_game_systems

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | index |
| slug | string | unique |
| description | text | nullable |
| publisher_id | uuid | nullable, FK → gametables_publishers SET NULL |
| edition | string | nullable |
| year | integer | nullable |
| logo_url | string | nullable |
| website_url | string | nullable |
| game_master_title | string | default `Director de juego` |
| is_active | boolean | default true, index |
| created_at / updated_at | timestamp | nullable |

- Model: `GameSystemModel`.

#### gametables_content_warnings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| slug | string | unique |
| label | string | |
| description | text | nullable |
| severity | string | index (`WarningSeverity`) |
| icon | string | nullable |
| is_active | boolean | default true, index |
| created_at / updated_at | timestamp | nullable |

- Model: `ContentWarningModel`.

#### gametables_campaigns

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| game_system_id | uuid | FK → gametables_game_systems CASCADE |
| created_by | uuid | FK → users CASCADE, index |
| title | string | |
| slug | string | nullable, unique |
| description | text | nullable |
| frequency | string | nullable (`CampaignFrequency`) |
| status | string | index (`CampaignStatus`) |
| session_count | integer | nullable |
| current_session | integer | default 0 |
| accepts_new_players | boolean | default true |
| max_players | integer | nullable |
| image_public_id | string | nullable |
| is_published | boolean | default false, index |
| frontend_creation_status | string | nullable, index (`FrontendCreationStatus`) |
| moderation_notes | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `CampaignModel` (`HasSlug`, `DeletesCloudinaryImages`).

#### gametables_campaign_players

Pivot created in the campaigns migration: `campaign_id` (FK → gametables_campaigns CASCADE) + `user_id` (FK → users CASCADE), composite PK, timestamps.

#### gametables_tables

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| game_system_id | uuid | FK → gametables_game_systems CASCADE |
| campaign_id | uuid | nullable, FK → gametables_campaigns SET NULL |
| event_id | uuid | nullable, FK → events SET NULL |
| created_by | uuid | FK → users CASCADE, index |
| title | string | |
| slug | string | nullable, unique |
| starts_at | dateTime | index |
| duration_minutes | integer | |
| table_type | string | `TableType` |
| table_format | string | `TableFormat` |
| status | string | index (`TableStatus`) |
| min_players | integer | |
| max_players | integer | |
| max_spectators | integer | default 0 |
| synopsis | text | nullable |
| location | string | nullable |
| online_url | string | nullable |
| minimum_age | integer | nullable |
| language | string(10) | default `es` (language code) |
| genres | json | nullable (`Genre` values) |
| tone | string | nullable (`Tone`) |
| experience_level | string | nullable (`ExperienceLevel`) |
| character_creation | string | nullable (`CharacterCreation`) |
| safety_tools | json | nullable (`SafetyTool` values) |
| custom_warnings | json | nullable |
| registration_type | string | default `everyone` (`RegistrationType`) |
| members_early_access_days | integer | default 0 |
| registration_opens_at | dateTime | nullable |
| registration_closes_at | dateTime | nullable |
| auto_confirm | boolean | default true |
| accepts_registrations_in_progress | boolean | default false |
| is_published | boolean | default false, index |
| published_at | dateTime | nullable |
| notes | text | nullable |
| notification_email | string | nullable |
| image_public_id | string | nullable |
| frontend_creation_status | string | nullable, index (`FrontendCreationStatus`) |
| moderation_notes | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Index `(is_published, starts_at)`. Model: `GameTableModel` (`HasSlug`, `DeletesCloudinaryImages`).

#### gametables_participants

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| game_table_id | uuid | FK → gametables_tables CASCADE |
| user_id | uuid | nullable, index, FK → users CASCADE (null for guests) |
| first_name | string | nullable |
| last_name | string | nullable |
| email | string | nullable |
| phone | string | nullable |
| cancellation_token | string(64) | nullable, unique |
| role | string | index (`ParticipantRole`) |
| status | string | index (`ParticipantStatus`) |
| waiting_list_position | integer | nullable |
| notes | text | nullable |
| confirmed_at | dateTime | nullable |
| cancelled_at | dateTime | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `ParticipantModel`.

#### gametables_game_masters

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| user_id | uuid | nullable, index, FK → users SET NULL |
| first_name | string | nullable |
| last_name | string | nullable |
| email | string | nullable |
| phone | string | nullable |
| role | string | index (`GameMasterRole`) |
| custom_title | string | nullable |
| notify_by_email | boolean | default true |
| is_name_public | boolean | default true |
| notes | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `GameMasterModel`.

#### Pivot tables

| Table | Columns | Keys |
|-------|---------|------|
| gametables_table_content_warnings | `game_table_id`, `content_warning_id` (no timestamps) | composite PK; both FK CASCADE |
| gametables_campaign_gm | `campaign_id`, `game_master_id`, `sort_order` integer default 0, timestamps | composite PK; both FK CASCADE |
| gametables_table_gm | `game_table_id`, `game_master_id`, `source` string (`inherited` or `local`, index), `excluded` boolean default false (index), `sort_order` integer default 0, timestamps | composite PK; both FK CASCADE |

#### game_tables_event_configs

One row per event; `event_id` is the primary key (no `id` column).

| Column | Type | Constraints |
|--------|------|-------------|
| event_id | uuid | PK, FK → events CASCADE |
| tables_enabled | boolean | default false |
| scheduling_mode | string | default `free` (`SchedulingMode`) |
| time_slots | json | nullable |
| location_mode | string | default `free` (`LocationMode`) |
| fixed_location | string | nullable |
| eligibility_override | json | nullable |
| early_access_enabled | boolean | default false |
| creation_opens_at | timestamp | nullable |
| early_access_tier | json | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `EventGameTableConfigModel`.

### memberships

#### memberships_members

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| member_number | string | unique |
| first_name | string | |
| last_name | string | |
| email | string | nullable, index |
| phone | string | nullable |
| birth_date | date | nullable |
| address | text | nullable |
| member_type | string | index (`MemberType`) |
| status | string | index (`MemberStatus`) |
| user_id | uuid | nullable, index, FK → users SET NULL |
| notes | text | nullable |
| joined_at | timestamp | |
| created_at / updated_at | timestamp | nullable |
| deleted_at | timestamp | nullable (soft delete) |

- Model: `MemberModel` (`SoftDeletes`).

#### memberships_memberships

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| member_id | uuid | FK → memberships_members CASCADE, index |
| period_type | string | `MembershipPeriodType` |
| start_date | date | |
| end_date | date | index |
| status | string | index (`MembershipStatus`) |
| activated_at | timestamp | nullable |
| cancelled_at | timestamp | nullable |
| notes | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Index `(member_id, status)`. Model: `MembershipModel`.

#### memberships_fees

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| membership_id | uuid | FK → memberships_memberships CASCADE, index |
| amount | decimal(10,2) | |
| currency | string | default `EUR` |
| due_date | date | index |
| paid_at | timestamp | nullable, index |
| payment_method | string | nullable (`PaymentMethod`) |
| transaction_reference | string | nullable |
| notes | text | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `MembershipFeeModel`.

#### memberships_fee_structures

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| member_type | string | `MemberType` |
| period_type | string | `MembershipPeriodType` |
| amount | decimal(10,2) | |
| currency | string | default `EUR` |
| proration_rules | json | nullable |
| valid_from | date | index |
| valid_until | date | nullable |
| description | text | nullable |
| is_default | boolean | default false, index |
| created_at / updated_at | timestamp | nullable |

- Index `(member_type, period_type)`. Model: `FeeStructureModel`.

### tournaments

#### tournaments_game_profiles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| slug | string | unique |
| description | text | nullable |
| stat_definitions | json | |
| scoring_rules | json | |
| tiebreaker_config | json | |
| pairing_config | json | |
| is_system | boolean | default false, index |
| created_at / updated_at | timestamp | nullable |

- Model: `GameProfileModel` (`HasSlug`). Migration `2026_10_12_000001_reseed_system_game_profiles` runs `GameProfilesSeeder` again to refresh the system profiles.

#### tournaments_tournaments

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| event_id | uuid | unique, FK → events CASCADE |
| name | string | |
| slug | string | unique |
| description | text | nullable |
| image_public_id | string | nullable |
| status | string | default `draft`, index (`TournamentStatus`) |
| max_rounds | integer | nullable |
| current_round | integer | default 0 |
| max_participants | integer | nullable |
| min_participants | integer | default 2 |
| allow_guests | boolean | default false |
| requires_manual_confirmation | boolean | default false |
| game_profile_id | uuid | nullable, FK → tournaments_game_profiles SET NULL |
| stat_definitions | json | nullable |
| scoring_rules | json | nullable |
| tiebreaker_config | json | nullable |
| pairing_config | json | nullable |
| show_participants | boolean | default true |
| notification_email | string | default `''` |
| self_check_in_allowed | boolean | default false |
| allowed_roles | json | nullable |
| requires_check_in | boolean | default false |
| check_in_starts_before | integer | nullable (minutes) |
| result_reporting | string | default `admin_only` (`ResultReporting`) |
| registration_opens_at | timestamp | nullable |
| registration_closes_at | timestamp | nullable |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |

- Model: `TournamentModel` (`HasSlug`, `DeletesCloudinaryImages`).

#### tournaments_participants

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| tournament_id | uuid | FK → tournaments_tournaments CASCADE |
| user_id | uuid | nullable, FK → users CASCADE (null for guests) |
| guest_name | string | nullable |
| guest_email | string | nullable |
| cancellation_token | string(64) | nullable, unique |
| status | string | default `registered` (`ParticipantStatus`) |
| seed | integer | nullable |
| has_received_bye | boolean | default false |
| registered_at | timestamp | nullable |
| checked_in_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |

- Unique `(tournament_id, user_id)`; index `(tournament_id, status)`. Model: `ParticipantModel`.

#### tournaments_rounds

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| tournament_id | uuid | FK → tournaments_tournaments CASCADE |
| round_number | integer | |
| status | string | default `pending` (`RoundStatus`) |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| created_at / updated_at | timestamp | nullable |

- Unique `(tournament_id, round_number)`. Model: `RoundModel`.

#### tournaments_matches

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| round_id | uuid | FK → tournaments_rounds CASCADE |
| player_1_id | uuid | FK → tournaments_participants CASCADE |
| player_2_id | uuid | nullable (null for a bye), FK → tournaments_participants CASCADE |
| table_number | integer | nullable |
| result | string | default `not_played` (`MatchResult`) |
| player_1_score | integer | nullable |
| player_1_stats | json | nullable |
| player_2_score | integer | nullable |
| player_2_stats | json | nullable |
| reported_by_id | uuid | nullable (no FK constraint) |
| reported_at | timestamp | nullable |
| confirmed_by_id | uuid | nullable (no FK constraint) |
| confirmed_at | timestamp | nullable |
| is_disputed | boolean | default false |
| created_at / updated_at | timestamp | nullable |

- Index `(round_id, result)`. Model: `MatchModel`.

#### tournaments_match_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| match_id | uuid | FK → tournaments_matches CASCADE |
| previous_result | string | nullable |
| new_result | string | |
| previous_player_1_score | integer | nullable |
| new_player_1_score | integer | nullable |
| previous_player_2_score | integer | nullable |
| new_player_2_score | integer | nullable |
| changed_by_id | uuid | FK → users CASCADE |
| reason | text | nullable |
| changed_at | timestamp | |
| created_at / updated_at | timestamp | nullable |

- Index `(match_id, changed_at)`. Model: `MatchHistoryModel`.

#### tournaments_standings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| tournament_id | uuid | FK → tournaments_tournaments CASCADE |
| participant_id | uuid | FK → tournaments_participants CASCADE |
| rank | integer | default 0 |
| matches_played | integer | default 0 |
| wins | integer | default 0 |
| draws | integer | default 0 |
| losses | integer | default 0 |
| byes | integer | default 0 |
| points | decimal(8,2) | default 0 |
| buchholz | decimal(8,2) | default 0 |
| median_buchholz | decimal(8,2) | default 0 |
| progressive | decimal(8,2) | default 0 |
| opponent_win_percentage | decimal(5,4) | default 0 |
| accumulated_stats | json | nullable |
| calculated_tiebreakers | json | nullable |
| created_at / updated_at | timestamp | nullable |

- Unique `(tournament_id, participant_id)`; index `(tournament_id, rank)`. Model: `StandingModel`.

### venue-bookings

#### venuebookings_resources

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| name | string | |
| slug | string | unique |
| description | text | nullable |
| capacity | unsignedInteger | nullable |
| status | string | default `active` (`BookableResourceStatus`) |
| sort_order | unsignedInteger | default 0 |
| field_config | json | nullable (booking form fields) |
| created_at / updated_at | timestamp | nullable |

- Model: `BookableResourceModel` (`HasSlug`).

#### venuebookings_operating_schedules

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| resource_id | uuid | unique, FK → venuebookings_resources CASCADE |
| scheduling_mode | string(20) | default `time_slots` (`SchedulingMode`) |
| slot_duration_minutes | unsignedInteger | default 60 |
| min_consecutive_slots | unsignedInteger | default 1 |
| max_consecutive_slots | unsignedInteger | default 4 |
| day_schedules | json | list of `DaySchedule` entries (day of week 0-6, Sunday first) |
| created_at / updated_at | timestamp | nullable |

- One schedule per resource. Model: `OperatingScheduleModel`.

#### venuebookings_bookings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PK |
| resource_id | uuid | FK → venuebookings_resources CASCADE |
| user_id | uuid | FK → users CASCADE, index |
| date | date | |
| start_time | time | |
| end_time | time | |
| status | string | default `pending`, index (`BookingStatus`) |
| event_id | uuid | nullable, FK → events SET NULL |
| game_table_id | string | nullable (no FK constraint) |
| tournament_id | string | nullable (no FK constraint) |
| campaign_id | string | nullable (no FK constraint) |
| field_values | json | nullable |
| cancellation_reason | text | nullable |
| admin_notes | text | nullable |
| confirmed_at | dateTime | nullable |
| cancelled_at | dateTime | nullable |
| created_at / updated_at | timestamp | nullable |

- Indexes `(resource_id, date)`, `(date, start_time, end_time)`. Model: `BookingModel`.

---

## 5. Core value objects

Identifier VOs (`ArticleId`, `EventId`, `GalleryId`, `PhotoId`, `UserId`, `RoleId`, `PermissionId`, `MenuItemId`, `ModuleId`, `EmailLogId`) extend `App\Domain\ValueObjects\EntityId` (`value`: UUID string).

| Value object | Properties | Location (`App\Domain\...`) |
|--------------|------------|-----------------------------|
| Slug | value | ValueObjects |
| Price | value (float) | ValueObjects |
| DownloadLink | label, url, description | ValueObjects |
| HexColor | value (normalized to uppercase `#RRGGBB`; 3-digit input is expanded) | ValueObjects |
| ColorPalette | name, shades | ValueObjects |
| RoleName | value | Authorization\ValueObjects |
| PermissionKey | value (`resource.action`) | Authorization\ValueObjects |
| ModuleName | value | Modules\ValueObjects |
| ModuleVersion | major, minor, patch, preRelease | Modules\ValueObjects |
| ModuleRequirements | phpVersion, laravelVersion, requiredModules, requiredExtensions, coreVersion, filamentVersion (core constraint defaults to `^2.0` when the manifest has none) | Modules\ValueObjects |
| CompatibilityIssue | requirement (`RequirementType`), required, found, reasonKey | Modules\ValueObjects |
| CompatibilityResult | issues | Modules\ValueObjects |
| HostEnvironment | core, php, laravel, filament, extensions, moduleMinimums | Modules\ValueObjects |
| CoreTableRegistry | list of core table names (`all()`, `isCore()`) | Modules\ValueObjects |
| GitHubReleaseInfo | tagName, version, downloadUrl, checksumUrl, releaseNotes, publishedAt, isPrerelease, manifestUrl, manifestSize | Updates\ValueObjects |
| ReleaseSelection | compatible, compatibleCoreConstraint, blocked, blockedCoreConstraint, blockedIssues | Updates\ValueObjects |
| GitHubCommitComparison | aheadBy, behindBy, headSha, commits | Updates\ValueObjects |
| EmailQuota | dailyLimit, monthlyLimit, warningThreshold | Mail\ValueObjects |
| SmtpPort | value (any port 1-65535; `default()` 587, `ssl()` 465, `plain()` 25) | Mail\ValueObjects |

## 6. Enums

All enums are string-backed; the values below are the stored values.

### Core

| Enum | Location (`App\Domain\...`) | Values |
|------|-----------------------------|--------|
| UserRole | Enums | `admin`, `editor`, `member` |
| PublicationStatus | Enums | `draft`, `published` |
| CalendarSourceColor | Calendar\Enums | `primary`, `accent`, `info`, `success`, `warning`, `neutral` |
| EmailStatus | Mail\Enums | `sent`, `failed`, `bounced`, `complained` |
| MailDriver | Mail\Enums | `smtp`, `mail`, `ses`, `resend`, `log`, `array` |
| SmtpEncryption | Mail\Enums | `tls`, `ssl`, `''` (none) |
| ModuleStatus | Modules\Enums | `disabled`, `enabled` |
| RequirementType | Modules\Enums | `core`, `php`, `laravel`, `filament`, `extension`, `manifest` |
| LinkTarget | Navigation\Enums | `_self`, `_blank` |
| MenuLocation | Navigation\Enums | `header`, `footer` |
| MenuVisibility | Navigation\Enums | `public`, `authenticated`, `guests`, `permission` |
| UpdateStatus | Updates\Enums | `pending`, `downloading`, `verifying`, `backing_up`, `applying`, `migrating`, `seeding`, `health_checking`, `completed`, `failed`, `rolled_back` |

### Modules

Module enums live in `Modules\<Name>\Domain\Enums`.

| Module | Enum | Values |
|--------|------|--------|
| announcements | AnnouncementVisibility | `public`, `authenticated`, `members` |
| announcements | AnnouncementPosition | `before-header`, `after-header`, `before-content`, `after-content`, `before-footer`, `after-footer` |
| channel-notifications | NotificationChannel | `telegram`, `discord`, `slack`, `whatsapp` |
| channel-notifications | ContentType | `event`, `article`, `gallery` |
| cookie-consent | BannerLayout | `bar`, `box`, `modal` |
| cookie-consent | BannerPosition | `bottom`, `top`, `bottom_left`, `bottom_right`, `center` |
| cookie-consent | BannerTheme | `light`, `dark`, `custom` |
| cookie-consent | ConsentMethod | `banner`, `settings_page`, `api` |
| cookie-consent | ConsentModeKey | `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, `functionality_storage`, `personalization_storage`, `security_storage` |
| cookie-consent | CookieType | `first_party`, `third_party` |
| event-registrations | RegistrationState | `pending`, `confirmed`, `waiting_list`, `cancelled`, `rejected` |
| event-registrations | ConfirmationSource | `manual`, `registration`, `waiting_list_promotion` |
| game-tables | CampaignFrequency | `weekly`, `biweekly`, `monthly`, `irregular` |
| game-tables | CampaignStatus | `recruiting`, `active`, `on_hold`, `completed`, `cancelled` |
| game-tables | CharacterCreation | `pre_generated`, `bring_own`, `create_at_table`, `any` |
| game-tables | CreationAccessLevel | `everyone`, `registered`, `role`, `permission` |
| game-tables | ExperienceLevel | `none`, `beginner`, `intermediate`, `advanced` |
| game-tables | FrontendCreationStatus | `draft`, `pending_review`, `approved`, `rejected` |
| game-tables | GameMasterRole | `main`, `co_gm` |
| game-tables | Genre | `fantasy`, `horror`, `sci_fi`, `post_apocalyptic`, `cyberpunk`, `steampunk`, `historical`, `modern`, `superhero`, `mystery`, `western`, `comedy`, `other` |
| game-tables | Language | `es`, `en`, `ca`, `eu`, `gl` |
| game-tables | LocationMode | `free`, `fixed`, `event` |
| game-tables | ParticipantRole | `game_master`, `co_gm`, `player`, `spectator` |
| game-tables | ParticipantStatus | `pending`, `confirmed`, `waiting_list`, `cancelled`, `rejected`, `no_show` |
| game-tables | RegistrationType | `everyone`, `members_only`, `invite` |
| game-tables | SafetyTool | `x_card`, `lines_and_veils`, `open_door`, `stars`, `support_flower`, `script`, `roses`, `other` |
| game-tables | SchedulingMode | `free`, `slots` |
| game-tables | TableFormat | `in_person`, `online`, `hybrid` |
| game-tables | TableStatus | `draft`, `scheduled`, `full`, `in_progress`, `completed`, `cancelled` |
| game-tables | TableType | `one_shot`, `adventure`, `campaign_session`, `demo`, `tutorial` |
| game-tables | Tone | `serious`, `light`, `mixed`, `dark` |
| game-tables | WarningSeverity | `mild`, `moderate`, `severe` |
| memberships | MemberType | `regular`, `student`, `senior`, `honorary`, `founder` |
| memberships | MemberStatus | `active`, `inactive`, `suspended`, `expelled` |
| memberships | MembershipPeriodType | `calendar_year`, `academic_year`, `rolling` |
| memberships | MembershipStatus | `pending`, `active`, `expired`, `cancelled` |
| memberships | PaymentMethod | `cash`, `bank_transfer`, `card`, `other` |
| tournaments | TournamentStatus | `draft`, `registration_open`, `registration_closed`, `in_progress`, `finished`, `cancelled` |
| tournaments | ParticipantStatus | `registered`, `confirmed`, `checked_in`, `withdrawn`, `disqualified` |
| tournaments | RoundStatus | `pending`, `in_progress`, `finished` |
| tournaments | MatchResult | `player_one_win`, `player_two_win`, `draw`, `double_loss`, `bye`, `not_played` |
| tournaments | ResultReporting | `admin_only`, `players_with_confirmation`, `players_trusted` |
| tournaments | PairingMethod | `swiss`, `random`, `accelerated` |
| tournaments | PairingSortCriteria | `points`, `stat`, `random` |
| tournaments | SortDirection | `asc`, `desc` |
| tournaments | ByeAssignment | `lowest_ranked`, `random`, `highest_ranked` |
| tournaments | ConditionType | `result`, `stat_comparison`, `stat_threshold`, `margin_diff` |
| tournaments | StatType | `integer`, `float`, `boolean` |
| tournaments | Tiebreaker | `buchholz`, `median_buchholz`, `progressive`, `head_to_head`, `opponent_win_percentage` |
| tournaments | TiebreakerType | `buchholz`, `median_buchholz`, `progressive`, `owp`, `oowp`, `gwp`, `ogwp`, `head_to_head`, `sonneborn_berger`, `stat_sum`, `stat_diff`, `stat_average`, `stat_max`, `sos`, `mov`, `random` |
| venue-bookings | ApprovalMode | `auto_confirm`, `require_approval` |
| venue-bookings | BookableResourceStatus | `active`, `inactive`, `maintenance` |
| venue-bookings | BookingFieldType | `text`, `textarea`, `number`, `select`, `toggle` |
| venue-bookings | BookingStatus | `pending`, `confirmed`, `completed`, `cancelled`, `no_show`, `rejected` |
| venue-bookings | FieldVisibility | `public`, `authenticated`, `permission`, `admin_only` |
| venue-bookings | SchedulingMode | `time_slots`, `time_blocks` |

## 7. Main core DTOs

### Input DTOs (`App\Application\DTOs`)

| DTO | Properties |
|-----|------------|
| ContactMessageDTO | senderName, senderEmail, messageBody |
| CreateUserDTO | name, email, password |
| UpdateUserDTO | name, displayName, email, avatarPublicId |
| AnonymizeUserDTO | userId, contentAction, transferToUserId |
| CreateEventDTO | title, description, startDate, endDate, location, imagePublicId, memberPrice, nonMemberPrice |
| CreateArticleDTO | title, content, authorId, excerpt, featuredImagePublicId |
| CreateGalleryDTO | title, description, coverImagePublicId |
| CreatePhotoDTO | galleryId, imagePublicId, caption, sortOrder |
| ThemeSettingsDTO | fontHeading, fontBody, fontSizeBase, borderRadius, shadowIntensity, buttonStyle, darkModeDefault, darkModeToggleVisible |
| ImageOptimizationSettingsDTO | maxWidth, maxHeight, quality, format, skipAnimatedGif, minSizeBytes |

### Response DTOs (`App\Application\DTOs\Response` unless noted)

| DTO | Properties |
|-----|------------|
| EventResponseDTO | id, title, slug, description, startDate, endDate, location, memberPrice, nonMemberPrice, imagePublicId, isPublished, createdAt, updatedAt, downloadLinks, tags |
| ArticleResponseDTO | id, title, slug, content, excerpt, featuredImage, isPublished, publishedAt, author (`AuthorResponseDTO`), createdAt, updatedAt, tags |
| AuthorResponseDTO | id, name, displayName, avatarPublicId |
| GalleryResponseDTO | id, title, slug, description, coverImagePublicId, isPublished, isFeatured, photoCount, createdAt, updatedAt, tags |
| GalleryDetailResponseDTO | id, title, slug, description, isPublished, isFeatured, photos, createdAt, updatedAt, tags |
| PhotoResponseDTO | id, imagePublicId, caption, sortOrder |
| HeroSlideResponseDTO | id, title, subtitle, buttonText, buttonUrl, imagePublicId, isActive, sortOrder |
| TagResponseDTO | id, name, slug, parentId, parentName, appliesTo, color, sortOrder |
| UserResponseDTO | id, name, displayName, email, pendingEmail, avatarPublicId, role, emailVerified, createdAt |
| LegalPageResponseDTO | title, content, lastUpdated |
| AboutPageResponseDTO | guildName, aboutHistory, contactEmail, contactPhone, contactAddress, aboutHeroImage, aboutTagline, activities, joinSteps, socialLinks, location |
| PaginatedResponseDTO | data, meta (`currentPage`, `lastPage`, `perPage`, `total`), links (`first`, `last`, `prev`, `next`) |
| CalendarEntryDTO (`App\Application\Calendar\DTOs`) | id, sourceType, sourceLabel, color (`CalendarSourceColor`), title, start, end, url, details |

## 8. Model traits

| Trait | Purpose | Used by |
|-------|---------|---------|
| `HasUuids` (Laravel) | UUID primary keys | Every core and module model with an `id` column (not the per-event config models) |
| `DeletesCloudinaryImages` (`App\Infrastructure\Persistence\Eloquent\Concerns`) | Deletes the old Cloudinary image when a field listed in `$cloudinaryImageFields` changes (`updating`) or the model is deleted (`deleting`) | UserModel, EventModel, ArticleModel, PhotoModel, HeroSlideModel; GameTableModel, CampaignModel (game-tables); TournamentModel (tournaments) |
| `HasSlug` (same namespace) | Generates unique slugs and records a `slug_redirects` row when a slug changes | EventModel; GameTableModel, CampaignModel (game-tables); TournamentModel, GameProfileModel (tournaments); BookableResourceModel (venue-bookings) |
| `SoftDeletes` (Laravel) | Soft delete with `deleted_at` | UserModel; MemberModel (memberships) |
| `HasFactory` (Laravel) | Model factories for tests | Core models except PermissionModel, RoleModel, SettingModel, SlugRedirectModel, SesUsageRecordModel and the update history models |
| `SanitizesHtml` (`App\Infrastructure\Support`) | Sanitizes rich-text HTML with HTMLPurifier | EloquentResponseDTOFactory, LegalPageService, AboutPageService |
| `BuildsPaginatedResponse` (`App\Http\Concerns`) | Builds `PaginatedResponseDTO` arrays for listings | EventController, ArticleController, GalleryController and module listing controllers |
| `AuthorizesWithPermissions` (`App\Infrastructure\Authorization\Policies`) | Permission checks in policies (`$this->authorize($user, 'permission.key')`) | Core policies |
