# Data models

> Generated: 2026-03-21 | Project: GuildForge

## 1. Relationship diagram

```
users ──┬── articles (author_id)
        ├── user_role ── roles ── role_permission ── permissions
        ├── event_registrations_registrations
        ├── memberships_members (user_id)
        ├── gametables_participants (user_id)
        ├── gametables_game_masters (user_id)
        ├── tournaments_participants (user_id)
        └── venuebookings_bookings (user_id)

events ──┬── event_tag ── tags (hierarchical: parent_id)
         ├── event_registrations_registrations
         ├── event_registration_configs
         ├── game_tables_event_configs
         ├── gametables_tables (event_id)
         ├── tournaments_tournaments (event_id)
         └── venuebookings_bookings (event_id)

articles ── article_tag ── tags
galleries ── photos (gallery_id)
galleries ── gallery_tag ── tags

gametables_tables ──┬── gametables_participants
                    ├── gametables_table_gm ── gametables_game_masters
                    ├── gametables_table_content_warnings ── gametables_content_warnings
                    └── gametables_campaigns ── gametables_campaign_gm

tournaments_tournaments ──┬── tournaments_participants
                          ├── tournaments_rounds ── tournaments_matches
                          ├── tournaments_standings
                          └── tournaments_game_profiles

venuebookings_resources ── venuebookings_operating_schedules
venuebookings_resources ── venuebookings_bookings

cookie_consent_categories ── cookie_consent_cookies ── cookie_consent_scripts
cookie_consent_consents (independent, by visitor_id)
```

---

## 2. Core tables

### users

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | NOT NULL |
| display_name | string | NULLABLE |
| email | string | UNIQUE, NOT NULL |
| pending_email | string | NULLABLE |
| email_verified_at | timestamp | NULLABLE |
| password | string | NOT NULL |
| avatar_public_id | string | NULLABLE |
| role | string | DEFAULT 'member' |
| remember_token | string | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |
| deleted_at | timestamp | NULLABLE (soft delete) |
| anonymized_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Entities\User`
- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\UserModel`
- **Relations**: BelongsToMany → RoleModel (user_role), HasMany → ArticleModel

### password_reset_tokens

| Column | Type | Constraints |
|--------|------|-------------|
| email | string | PRIMARY KEY |
| token | string | NOT NULL |
| created_at | timestamp | NULLABLE |

### sessions

| Column | Type | Constraints |
|--------|------|-------------|
| id | string | PRIMARY KEY |
| user_id | uuid | NULLABLE, FK → users, INDEX |
| ip_address | string(45) | NULLABLE |
| user_agent | text | NULLABLE |
| payload | longText | NOT NULL |
| last_activity | integer | INDEX |

### events

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | NOT NULL |
| slug | string | UNIQUE |
| description | text | NOT NULL |
| start_date | dateTime | NOT NULL, INDEX |
| end_date | dateTime | NOT NULL |
| location | string | NULLABLE |
| member_price | decimal(8,2) | NULLABLE |
| non_member_price | decimal(8,2) | NULLABLE |
| image_public_id | string | NULLABLE |
| download_links | json | NULLABLE |
| is_published | boolean | DEFAULT false, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Entities\Event`
- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\EventModel`
- **Relations**: BelongsToMany → TagModel (event_tag), HasMany → GameTableModel

### articles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | NOT NULL |
| slug | string | UNIQUE |
| content | text | NOT NULL |
| excerpt | string | NULLABLE |
| featured_image_public_id | string | NULLABLE |
| author_id | uuid | FK → users CASCADE |
| is_published | boolean | DEFAULT false, INDEX |
| published_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Entities\Article`
- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\ArticleModel`
- **Relations**: BelongsTo → UserModel (author), BelongsToMany → TagModel

### galleries

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | NOT NULL |
| slug | string | UNIQUE |
| description | text | NULLABLE |
| cover_image_public_id | string | NULLABLE |
| is_published | boolean | DEFAULT false, INDEX |
| is_featured | boolean | DEFAULT false |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Entities\Gallery`
- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\GalleryModel`
- **Relations**: HasMany → PhotoModel, BelongsToMany → TagModel

### photos

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| gallery_id | uuid | FK → galleries CASCADE, INDEX |
| image_public_id | string | NOT NULL |
| caption | string | NULLABLE |
| sort_order | integer | DEFAULT 0, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Entities\Photo`
- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\PhotoModel`

### tags

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | NOT NULL |
| slug | string | UNIQUE |
| parent_id | uuid | NULLABLE, FK → tags(id) NULL ON DELETE, INDEX |
| applies_to | json | NOT NULL (array: 'event', 'article', 'gallery') |
| color | string(7) | DEFAULT '#6b7280' |
| description | text | NULLABLE |
| sort_order | integer | DEFAULT 0, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\TagModel`
- **Relations**: BelongsTo → TagModel (parent), HasMany → TagModel (children), BelongsToMany → EventModel, ArticleModel, GalleryModel

### Tag pivot tables

**event_tag**, **article_tag**, **gallery_tag** -- identical structure:

| Column | Type | Constraints |
|--------|------|-------------|
| {resource}_id | uuid | Composite PK, FK CASCADE |
| tag_id | uuid | Composite PK, FK CASCADE |
| created_at / updated_at | timestamp | NULLABLE |

### permissions

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| key | string | UNIQUE |
| label | string | NOT NULL |
| resource | string | NOT NULL, INDEX |
| action | string | NOT NULL |
| module | string | NULLABLE, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Authorization\Entities\Permission`
- **VOs**: PermissionId, PermissionKey

### roles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | UNIQUE, INDEX |
| display_name | string | NOT NULL |
| description | text | NULLABLE |
| is_protected | boolean | DEFAULT false |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Authorization\Entities\Role`
- **VOs**: RoleId, RoleName

### role_permission / user_role

Pivot tables with structure: `{entity1}_id` + `{entity2}_id` (composite PK, FK CASCADE).

### hero_slides

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | NOT NULL |
| subtitle | string | NULLABLE |
| button_text | string | NULLABLE |
| button_url | string | NULLABLE |
| image_public_id | string | NULLABLE |
| is_active | boolean | DEFAULT false, INDEX |
| sort_order | integer | DEFAULT 0, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Model**: `App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel`

### menu_items

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| location | string | NOT NULL ('header', 'footer') |
| parent_id | uuid | NULLABLE, FK → menu_items CASCADE, INDEX |
| label | string | NOT NULL |
| url | string | NULLABLE |
| route | string | NULLABLE |
| route_params | json | NULLABLE |
| icon | string | NULLABLE |
| target | string | DEFAULT '_self' |
| visibility | string | DEFAULT 'public' |
| permissions | json | NULLABLE |
| sort_order | integer | DEFAULT 0 |
| is_active | boolean | DEFAULT true |
| module | string | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `App\Domain\Navigation\Entities\MenuItem`
- **Enums**: MenuLocation, MenuVisibility, LinkTarget

### settings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| key | string | UNIQUE |
| value | text | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

### slug_redirects

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| old_slug | string | INDEX |
| new_slug | string | INDEX |
| entity_type | string | NOT NULL |
| entity_id | uuid | INDEX |
| created_at / updated_at | timestamp | NULLABLE |
| **Unique** | (old_slug, entity_type) | |

- **Entity**: `App\Domain\Entities\SlugRedirect`

### modules

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | UNIQUE |
| display_name | string | NULLABLE |
| version | string | NOT NULL |
| description | text | NULLABLE |
| author | string | NULLABLE |
| status | string | DEFAULT 'disabled', INDEX |
| path / namespace / provider | string | NULLABLE |
| requires | json | NULLABLE |
| dependencies | json | NULLABLE |
| source_owner / source_repo | string | NULLABLE |
| latest_available_version | string | NULLABLE |
| last_update_check_at | timestamp | NULLABLE |
| discovered_at / enabled_at / installed_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

### email_logs

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| message_id | string | UNIQUE, NULLABLE |
| to_email | string | INDEX |
| subject | string | NOT NULL |
| status | string | DEFAULT 'sent', INDEX |
| driver | string | NOT NULL |
| error_message | text | NULLABLE |
| metadata | json | NULLABLE |
| sent_at / delivered_at / bounced_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

### ses_usage_records

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| date | date | NOT NULL |
| sends | integer | DEFAULT 0 |
| bounces / complaints / rejects | integer | DEFAULT 0 |
| created_at / updated_at | timestamp | NULLABLE |
| **Unique** | date | |

### Update system tables

- **module_update_history**: id, module_name, from_version, to_version, status, error_message, timestamps
- **module_update_logs**: id, update_history_id (FK), level, message, context (json), timestamps
- **module_seeder_history**: id, module_name, seeder_class, ran_at
- **core_update_history**: id, from_version, to_version, status, error_message, timestamps
- **core_seeder_history**: id, seeder_class, ran_at

---

## 3. Module tables

### event-registrations

#### event_registrations_registrations

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| event_id | uuid | FK → events CASCADE, INDEX |
| user_id | uuid | FK → users CASCADE, INDEX |
| state | string | DEFAULT 'pending' |
| position | integer | NULLABLE |
| form_data | json | NULLABLE |
| notes | text | NULLABLE |
| admin_notes | text | NULLABLE |
| confirmed_at / cancelled_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |
| **Unique** | (event_id, user_id) | |

- **Entity**: `Modules\EventRegistrations\Domain\Entities\EventRegistration`
- **Enum**: RegistrationState (pending, confirmed, waiting_list, cancelled, rejected)

#### event_registration_configs

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| event_id | uuid | UNIQUE, FK → events CASCADE |
| registration_enabled | boolean | DEFAULT true |
| max_participants | integer | NULLABLE |
| waiting_list_enabled | boolean | DEFAULT true |
| max_waiting_list | integer | NULLABLE |
| registration_opens_at / registration_closes_at / cancellation_deadline | timestamp | NULLABLE |
| requires_confirmation / requires_payment / members_only | boolean | DEFAULT false |
| custom_fields | json | NULLABLE |
| confirmation_message | text | NULLABLE |
| notification_email | string | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

### memberships

#### memberships_members

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| member_number | string | UNIQUE |
| first_name / last_name | string | NOT NULL |
| email | string | NULLABLE, INDEX |
| phone | string | NULLABLE |
| birth_date | date | NULLABLE |
| address | text | NULLABLE |
| member_type | string | DEFAULT 'regular' |
| status | string | DEFAULT 'active', INDEX |
| user_id | uuid | NULLABLE, FK → users SET NULL, INDEX |
| notes | text | NULLABLE |
| joined_at | timestamp | NOT NULL |
| deleted_at | timestamp | NULLABLE (soft delete) |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `Modules\Memberships\Domain\Entities\Member`
- **Enums**: MemberType (regular, student, senior, honorary, founder), MemberStatus (active, inactive, suspended, expelled)

#### memberships_memberships

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| member_id | uuid | FK → memberships_members CASCADE |
| period_type | string | NOT NULL |
| start_date / end_date | date | NOT NULL |
| status | string | DEFAULT 'active' |
| fee_amount | decimal | NOT NULL |
| payment_method | string | NOT NULL |
| paid_at / renewed_at / cancelled_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enums**: MembershipPeriodType (monthly, quarterly, annual), PaymentMethod (cash, bank_transfer, online, in_kind), MembershipStatus (active, expired, cancelled)

#### memberships_fees

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| membership_id | uuid | FK → memberships_memberships CASCADE |
| description | string | NOT NULL |
| amount | decimal(10,2) | NOT NULL |
| paid_on | date | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### memberships_fee_structures

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| period_type | string | UNIQUE |
| regular_fee | decimal(10,2) | NOT NULL |
| student_fee / senior_fee | decimal(10,2) | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

### game-tables

#### gametables_game_systems

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | UNIQUE |
| slug | string | UNIQUE |
| description | text | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### gametables_publishers

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | UNIQUE |
| created_at / updated_at | timestamp | NULLABLE |

#### gametables_content_warnings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | UNIQUE |
| slug | string | UNIQUE |
| description | text | NULLABLE |
| severity | string | NOT NULL |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: WarningSeverity (low, medium, high)

#### gametables_campaigns

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | NOT NULL |
| slug | string | UNIQUE, NULLABLE |
| description | text | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### gametables_tables

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| game_system_id | uuid | FK → gametables_game_systems CASCADE |
| campaign_id | uuid | NULLABLE, FK → gametables_campaigns SET NULL |
| event_id | uuid | NULLABLE, FK → events SET NULL |
| created_by | uuid | FK → users CASCADE, INDEX |
| title | string | NOT NULL |
| slug | string | UNIQUE, NULLABLE |
| starts_at | dateTime | NOT NULL, INDEX |
| duration_minutes | integer | NOT NULL |
| table_type | string | NOT NULL |
| table_format | string | NOT NULL |
| status | string | NOT NULL, INDEX |
| min_players / max_players | integer | NOT NULL |
| max_spectators | integer | DEFAULT 0 |
| synopsis | text | NULLABLE |
| location | string | NULLABLE |
| online_url | string | NULLABLE |
| minimum_age | integer | NULLABLE |
| language | string(10) | DEFAULT 'es' |
| genres / safety_tools / custom_warnings | json | NULLABLE |
| tone / experience_level / character_creation | string | NULLABLE |
| registration_type | string | DEFAULT 'everyone' |
| members_early_access_days | integer | DEFAULT 0 |
| registration_opens_at / registration_closes_at | dateTime | NULLABLE |
| auto_confirm | boolean | DEFAULT true |
| accepts_registrations_in_progress | boolean | DEFAULT false |
| is_published | boolean | DEFAULT false, INDEX |
| published_at | dateTime | NULLABLE |
| notes / moderation_notes | text | NULLABLE |
| notification_email | string | NULLABLE |
| image_public_id | string | NULLABLE |
| frontend_creation_status | string | NULLABLE, INDEX |
| created_at / updated_at | timestamp | NULLABLE |

- **Entity**: `Modules\GameTables\Domain\Entities\GameTable`
- **Enums**: TableType (one_shot, campaign, side_quest), TableFormat (in_person, online, hybrid), TableStatus (draft, scheduled, full, in_progress, completed, cancelled)
- **Composite index**: (is_published, starts_at)

#### gametables_participants

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| table_id | uuid | FK → gametables_tables CASCADE |
| user_id | uuid | NULLABLE, FK → users CASCADE |
| email / name | string | NULLABLE / NOT NULL |
| status | string | NOT NULL |
| role | string | NOT NULL |
| registered_at | timestamp | DEFAULT now |
| created_at / updated_at | timestamp | NULLABLE |

- **Enums**: ParticipantStatus (registered, waiting_list, promoted, rejected, cancelled), ParticipantRole (participant, spectator)

#### gametables_game_masters

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| user_id | uuid | FK → users CASCADE |
| name | string | NOT NULL |
| email | string | NULLABLE |
| role | string | NOT NULL |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: GameMasterRole (primary, assistant, host)

#### Pivot tables: gametables_table_content_warnings, gametables_campaign_gm, gametables_table_gm

Structure: `{entity1}_id` + `{entity2}_id` (composite PK, FK CASCADE).

#### game_tables_event_configs

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| event_id | uuid | UNIQUE, FK → events CASCADE |
| enable_game_tables | boolean | DEFAULT true |
| created_at / updated_at | timestamp | NULLABLE |

### cookie-consent

#### cookie_consent_categories

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| slug | string | UNIQUE |
| label | string | NOT NULL |
| description | text | NULLABLE |
| order | integer | DEFAULT 0 |
| created_at / updated_at | timestamp | NULLABLE |

#### cookie_consent_cookies

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| category_id | uuid | FK → cookie_consent_categories CASCADE |
| name / slug | string | NOT NULL / UNIQUE |
| type | string | NOT NULL |
| description | text | NULLABLE |
| provider | string | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: CookieType (analytics, marketing, functional, preferences)

#### cookie_consent_scripts

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| cookie_id | uuid | NULLABLE, FK → cookies CASCADE |
| script_code | text | NOT NULL |
| type | string | NOT NULL |
| position | string | NOT NULL |
| enabled | boolean | DEFAULT true |
| created_at / updated_at | timestamp | NULLABLE |

#### cookie_consent_consents

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| visitor_id | uuid | NOT NULL, INDEX |
| user_id | uuid | NULLABLE, INDEX |
| ip_hash | string(64) | NOT NULL |
| user_agent | text | NOT NULL |
| preferences | json | NOT NULL |
| config_version | unsigned int | NOT NULL |
| consent_method | string | NOT NULL |
| consented_at / expires_at | timestamp | NOT NULL |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: ConsentMethod (explicit, implicit, banner_click)

### tournaments

#### tournaments_game_profiles

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | NOT NULL |
| game_system | string | NOT NULL |
| stat_definitions | json | NULLABLE |
| scoring_rules | json | NULLABLE |
| tiebreaker_config | json | NULLABLE |
| pairing_config | json | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### tournaments_tournaments

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| event_id | uuid | UNIQUE, FK → events CASCADE |
| name | string | NOT NULL |
| slug | string | UNIQUE |
| description | text | NULLABLE |
| image_public_id | string | NULLABLE |
| status | string | DEFAULT 'draft', INDEX |
| max_rounds | integer | NULLABLE |
| current_round | integer | DEFAULT 0 |
| max_participants | integer | NULLABLE |
| min_participants | integer | DEFAULT 2 |
| allow_guests | boolean | DEFAULT false |
| requires_manual_confirmation | boolean | DEFAULT false |
| game_profile_id | uuid | NULLABLE, FK → game_profiles SET NULL |
| stat_definitions / scoring_rules / tiebreaker_config / pairing_config | json | NULLABLE |
| show_participants | boolean | DEFAULT true |
| notification_email | string | DEFAULT '' |
| self_check_in_allowed / requires_check_in | boolean | DEFAULT false |
| check_in_starts_before | integer | NULLABLE |
| allowed_roles | json | NULLABLE |
| result_reporting | string | DEFAULT 'admin_only' |
| registration_opens_at / registration_closes_at / started_at / completed_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enums**: TournamentStatus (draft, registration_open, registration_closed, in_progress, completed, cancelled), ResultReporting (admin_only, participant, both)

#### tournaments_participants

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| tournament_id | uuid | FK → tournaments CASCADE |
| user_id | uuid | NULLABLE, FK → users SET NULL |
| guest_name / guest_email | string | NULLABLE |
| status | string | NOT NULL |
| stats | json | NULLABLE |
| checked_in_at | timestamp | NULLABLE |
| cancellation_token | string | UNIQUE, NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### tournaments_rounds

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| tournament_id | uuid | FK → tournaments CASCADE |
| round_number | integer | NOT NULL |
| status | string | NOT NULL |
| started_at / completed_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: RoundStatus (pending, in_progress, completed)

#### tournaments_matches

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| round_id | uuid | FK → rounds CASCADE |
| participant1_id / participant2_id | uuid | NULLABLE, FK → participants SET NULL |
| result | string | NULLABLE |
| stats | json | NULLABLE |
| is_bye | boolean | DEFAULT false |
| table_number | integer | NULLABLE |
| completed_at | timestamp | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: MatchResult (participant1_wins, participant2_wins, draw)

#### tournaments_match_history

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| match_id | uuid | FK → matches CASCADE |
| changed_by | uuid | FK → users SET NULL |
| old_result / new_result | string | NULLABLE |
| old_stats / new_stats | json | NULLABLE |
| reason | text | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

#### tournaments_standings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| tournament_id | uuid | FK → tournaments CASCADE |
| participant_id | uuid | FK → participants CASCADE |
| position | integer | NOT NULL |
| wins / draws / losses | integer | DEFAULT 0 |
| tournament_points | decimal(10,4) | DEFAULT 0 |
| tiebreaker_scores | json | NULLABLE |
| opponents_faced | json | NULLABLE |
| stats | json | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |
| **Unique** | (tournament_id, participant_id) | |

### announcements

#### announcements_announcements

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| title | string | NOT NULL |
| content | text | NOT NULL |
| author_id | uuid | NULLABLE, FK → users SET NULL |
| visibility | string | DEFAULT 'everyone' |
| position | string | DEFAULT 'top' |
| published_at / expires_at | timestamp | NULLABLE |
| is_featured | boolean | DEFAULT false |
| created_at / updated_at | timestamp | NULLABLE |

- **Enums**: AnnouncementVisibility (everyone, members, admins), AnnouncementPosition (top, bottom, modal)

### venue-bookings

#### venuebookings_resources

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| name | string | NOT NULL |
| slug | string | UNIQUE |
| description | text | NULLABLE |
| status | string | DEFAULT 'active' |
| slot_duration_minutes | integer | DEFAULT 60 |
| max_advance_days / min_advance_hours | integer | NULLABLE |
| max_daily_bookings_per_user | integer | NULLABLE |
| booking_fields | json | NULLABLE |
| approval_mode | string | DEFAULT 'auto_approve' |
| min_slots | integer | DEFAULT 1 |
| sort_order | integer | DEFAULT 0 |
| created_at / updated_at | timestamp | NULLABLE |

- **Enums**: BookableResourceStatus (active, inactive, maintenance), ApprovalMode (auto_approve, manual), SchedulingMode

#### venuebookings_operating_schedules

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| resource_id | uuid | FK → resources CASCADE |
| day_of_week | integer | NOT NULL (0-6) |
| open_time / close_time | time | NOT NULL |
| is_active | boolean | DEFAULT true |
| created_at / updated_at | timestamp | NULLABLE |
| **Unique** | (resource_id, day_of_week) | |

#### venuebookings_bookings

| Column | Type | Constraints |
|--------|------|-------------|
| id | uuid | PRIMARY KEY |
| resource_id | uuid | FK → resources CASCADE |
| user_id | uuid | FK → users CASCADE, INDEX |
| date | date | NOT NULL |
| start_time / end_time | time | NOT NULL |
| status | string | DEFAULT 'pending', INDEX |
| event_id | uuid | NULLABLE, FK → events SET NULL |
| game_table_id / tournament_id / campaign_id | string | NULLABLE |
| field_values | json | NULLABLE |
| cancellation_reason / admin_notes | text | NULLABLE |
| confirmed_at / cancelled_at | dateTime | NULLABLE |
| created_at / updated_at | timestamp | NULLABLE |

- **Enum**: BookingStatus (pending, confirmed, cancelled, completed, no_show)
- **Indexes**: (resource_id, date), (date, start_time, end_time)

---

## 3.1. Composite indexes

In addition to the simple indexes listed in each table, the migrations define the following composite indexes:

| Table | Columns | Type |
|-------|---------|------|
| email_logs | (status, created_at) | INDEX |
| module_update_logs | (update_history_id, created_at) | INDEX |
| module_update_history | (module_name, started_at) | INDEX |
| module_seeder_history | (module_name, seeder_class) | UNIQUE |
| slug_redirects | (old_slug, entity_type) | UNIQUE |
| menu_items | (location, is_active, sort_order) | INDEX |
| photos | (gallery_id, sort_order) | INDEX |

> **Note:** modules do not define additional composite indexes in their migrations.

---

## 4. Value objects

### Core

| Value object | Properties | Location |
|--------------|------------|----------|
| EntityId | value (UUID string) | Domain/ValueObjects |
| UserId | value (UUID) | Domain/ValueObjects |
| ArticleId | value (UUID) | Domain/ValueObjects |
| EventId | value (UUID) | Domain/ValueObjects |
| GalleryId | value (UUID) | Domain/ValueObjects |
| PhotoId | value (UUID) | Domain/ValueObjects |
| Slug | value (string) | Domain/ValueObjects |
| Price | amount (decimal) | Domain/ValueObjects |
| DownloadLink | label, url, description | Domain/ValueObjects |
| HexColor | value (string #RRGGBB) | Domain/ValueObjects |
| ColorPalette | shades (array of HexColor) | Domain/ValueObjects |

### Authorization

| Value object | Properties |
|--------------|------------|
| RoleId | value (UUID) |
| RoleName | value (string) |
| PermissionId | value (UUID) |
| PermissionKey | value (string, format: resource.action) |

### Navigation

| Value object | Properties |
|--------------|------------|
| MenuItemId | value (UUID) |

### Modules

| Value object | Properties |
|--------------|------------|
| ModuleId | value (UUID) |
| ModuleName | value (string) |
| ModuleVersion | value (string, semver) |
| ModuleRequirements | core (version constraint), modules (array) |
| CoreTableRegistry | tables (array of core table names) |

### Mail

| Value object | Properties |
|--------------|------------|
| EmailLogId | value (UUID) |
| SmtpPort | value (int: 25, 465, 587, 2525) |
| EmailQuota | daily, sent, remaining |

### Updates

| Value object | Properties |
|--------------|------------|
| GitHubReleaseInfo | tagName, body, publishedAt, htmlUrl |

---

## 5. Domain enums

### Core

| Enum | Values |
|------|--------|
| UserRole | admin, editor, member |
| PublicationStatus | draft, published |
| ModuleStatus | disabled, enabled, installed, uninstalled |
| MenuLocation | header, footer |
| MenuVisibility | public, authenticated, guests, permission |
| LinkTarget | _self, _blank |
| UpdateStatus | pending, in_progress, completed, failed, rolled_back |
| MailDriver | smtp, ses, resend, postmark, log |
| SmtpEncryption | tls, ssl, none |
| EmailStatus | sent, delivered, bounced, complained, failed |

### event-registrations

| Enum | Values |
|------|--------|
| RegistrationState | pending, confirmed, waiting_list, cancelled, rejected |

### memberships

| Enum | Values |
|------|--------|
| MemberType | regular, student, senior, honorary, founder |
| MemberStatus | active, inactive, suspended, expelled |
| MembershipStatus | active, expired, cancelled |
| MembershipPeriodType | monthly, quarterly, annual |
| PaymentMethod | cash, bank_transfer, online, in_kind |

### game-tables

| Enum | Values |
|------|--------|
| TableType | one_shot, campaign, side_quest |
| TableFormat | in_person, online, hybrid |
| TableStatus | draft, scheduled, full, in_progress, completed, cancelled |
| ParticipantStatus | registered, waiting_list, promoted, rejected, cancelled |
| ParticipantRole | participant, spectator |
| GameMasterRole | primary, assistant, host |
| WarningSeverity | low, medium, high |
| Genre | fantasy, sci_fi, horror, mystery, historical, modern, post_apocalyptic, superhero, comedy, western, cyberpunk, steampunk, other |
| Tone | serious, casual, dramatic, comedic, dark, lighthearted |
| ExperienceLevel | beginner, intermediate, advanced, any |
| CharacterCreation | pre_generated, session_zero, bring_your_own, hybrid |
| SafetyTool | lines_veils, x_card, open_door, stars_wishes, other |
| RegistrationType | everyone, members_only, invite_only |
| FrontendCreationStatus | draft, pending_review, approved, rejected |
| CreationAccessLevel | disabled, members_only, everyone |
| Language | es, en, fr, de, it, pt, ca, eu, gl |
| SchedulingMode | manual, event_linked |
| LocationMode | manual, event_linked |

### tournaments

| Enum | Values |
|------|--------|
| TournamentStatus | draft, registration_open, registration_closed, in_progress, completed, cancelled |
| ParticipantStatus | registered, confirmed, checked_in, dropped, disqualified |
| RoundStatus | pending, in_progress, completed |
| MatchResult | participant1_wins, participant2_wins, draw |
| PairingMethod | swiss, random |
| PairingSortCriteria | points, record, random, seed |
| SortDirection | asc, desc |
| TiebreakerType | buchholz, sos, extended_sos, median_buchholz, progressive, custom |
| Tiebreaker | buchholz, sos, extended_sos, median_buchholz, progressive, cumulative, direct_encounter, wins, secondary_points |
| ResultReporting | admin_only, participant, both |
| StatType | integer, decimal, boolean, percentage |
| ConditionType | greater_than, less_than, equals, not_equals, between |
| ByeAssignment | lowest_ranked, random, never_had_bye |

### cookie-consent

| Enum | Values |
|------|--------|
| CookieType | analytics, marketing, functional, preferences |
| ConsentMethod | explicit, implicit, banner_click |
| BannerPosition | bottom, top, center |
| BannerLayout | bar, modal, floating |
| BannerTheme | light, dark, auto |
| ConsentModeKey | ad_storage, analytics_storage, functionality_storage, personalization_storage, security_storage |

### announcements

| Enum | Values |
|------|--------|
| AnnouncementVisibility | everyone, members, admins |
| AnnouncementPosition | top, bottom, modal |

### channel-notifications

| Enum | Values |
|------|--------|
| NotificationChannel | discord, telegram, slack |
| ContentType | event_created, article_published, gallery_published, game_table_published, tournament_created |

### venue-bookings

| Enum | Values |
|------|--------|
| BookableResourceStatus | active, inactive, maintenance |
| BookingStatus | pending, confirmed, cancelled, completed, no_show |
| BookingFieldType | text, number, select, checkbox, textarea |
| FieldVisibility | public, admin_only |
| ApprovalMode | auto_approve, manual |
| SchedulingMode | fixed, flexible |

---

## 6. Main DTOs

### Input DTOs (Application/DTOs)

| DTO | Properties |
|-----|------------|
| ContactMessageDTO | name, email, message |
| CreateUserDTO | name, email, password |
| UpdateProfileDTO | name, displayName, email, avatar |
| ChangePasswordDTO | currentPassword, newPassword |

### Response DTOs (Application/DTOs/Response)

| DTO | Key properties |
|-----|----------------|
| EventResponseDTO | id, title, slug, description, startDate, endDate, location, memberPrice, nonMemberPrice, imagePublicId, isPublished, downloadLinks, tags |
| ArticleResponseDTO | id, title, slug, content, excerpt, featuredImagePublicId, author, isPublished, publishedAt, tags |
| GalleryResponseDTO | id, title, slug, description, coverImagePublicId, isPublished, isFeatured, photos, photoCount, tags |
| PhotoResponseDTO | id, imagePublicId, caption, sortOrder |
| HeroSlideResponseDTO | id, title, subtitle, buttonText, buttonUrl, imagePublicId, isActive, sortOrder |
| TagResponseDTO | id, name, slug, color, description, parentId, appliesTo |
| UserResponseDTO | id, name, displayName, email, avatarPublicId, isEmailVerified, roles |
| LegalPageResponseDTO | title, content, lastUpdated |
| CalendarEventResponseDTO | id, title, start, end, url, color |

---

## 7. Model traits

| Trait | Purpose | Used by |
|-------|---------|---------|
| DeletesCloudinaryImages | Auto-deletes Cloudinary images when updating/deleting fields in `$cloudinaryImageFields` | UserModel, EventModel, ArticleModel, GalleryModel, PhotoModel, HeroSlideModel, GameTableModel |
| HasSlug | Generates automatic slugs, manages redirections via SlugRedirect | EventModel, ArticleModel, GalleryModel |
| AuthorizesWithPermissions | Centralizes permission verification in policies via `$this->authorize($user, 'permission.key')` | All policies |
| SanitizesHtml | Sanitizes HTML with HTMLPurifier for rich-text content | LegalPageService, AboutPageService |
| BuildsPaginatedResponse | Builds standardized paginated responses | Controllers with listings |
| HasFactory | Factory pattern for testing | All Eloquent models |
| SoftDeletes | Soft delete with `deleted_at` | UserModel, MemberModel |

---

## Summary

| Category | Count |
|----------|-------|
| Core tables | 17 (including pivot and system) |
| Module tables | ~30 |
| Total tables | ~47 |
| Domain entities | 11 (core) + 15 (modules) |
| Value objects | 25 |
| Enums | 50+ |
| DTOs | 13+ |
| Model traits | 7 |
