# Mail categories

Every mail the site sends to an account belongs to a category. Each person sees the categories in their profile and turns off the optional ones they don't want. Mandatory ones are shown locked, with the reason they are always sent. A module declares the categories of its mail and says, in each notification, which one it belongs to; the core does the filtering. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces mail categories (the one with core#328). An older core has no `HasMailCategory`, so a notification implementing it would not load.

## Choosing categories

Group the module's mail into a few categories under one section, the module's visible name («Partidas», «Torneos»…). Never one category per mail: people should understand the whole list at a glance.

- **Mandatory**: the mail confirms something the person did, changes their plans or asks them to act (a sign-up confirmation, a cancellation, a request to approve). It is always sent.
- **Optional**: everything else (reminders, news, notices about other people). It is on until the person turns it off.

The module's own global switches (settings the administrators set for the whole site) still decide first: a personal preference only ever means receiving less, never more.

## Declaring them

Return the categories from the service provider. Every key starts with the module name followed by a dot, and stays the same forever, because it is stored with each person's choice:

```php
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;

public function registerMailCategories(): array
{
    return [
        MailCategoryDTO::mandatory(
            key: 'my-module.sign-ups',
            section: 'my-module::mail.categories.section',
            label: 'my-module::mail.categories.sign_ups.label',
            description: 'my-module::mail.categories.sign_ups.description',
            reason: 'my-module::mail.categories.sign_ups.reason',
        ),
        MailCategoryDTO::optional(
            key: 'my-module.reminders',
            section: 'my-module::mail.categories.section',
            label: 'my-module::mail.categories.reminders.label',
            description: 'my-module::mail.categories.reminders.description',
            // Optional: the icon of the section's card in the profile (Lucide name in kebab case)
            icon: 'dices',
        ),
        MailCategoryDTO::optional(
            key: 'my-module.moderation',
            section: 'my-module::mail.categories.section',
            label: 'my-module::mail.categories.moderation.label',
            description: 'my-module::mail.categories.moderation.description',
            // Only people who can moderate see it in their profile
            audience: static fn (object $user): bool => app(AuthorizationServiceInterface::class)
                ->can($user, 'my-module:items.moderate'),
        ),
    ];
}
```

- `section`, `label`, `description` and `reason` are translation keys: the core translates them when it lists the categories. Write them in Spanish, with the project's capitalization («Recordatorios de partidas»).
- `icon` (optional, `optional()` and `mandatory()`) is the Lucide icon name, in kebab case, of the section's card in the profile; the first category of a section that declares one decides. The profile knows a closed list (`mailSectionIconMap` in `resources/js/utils/icons.ts`: `bell`, `book-open`, `building-2`, `calendar-check`, `calendar-days`, `circle-user-round`, `clipboard-list`, `credit-card`, `dices`, `handshake`, `heart`, `id-card`, `mail`, `megaphone`, `map-pin`, `newspaper`, `settings-2`, `shield-check`, `swords`, `ticket`, `trophy`, `users`) and uses a generic mail icon for a missing or unknown name; a name that is not kebab case throws `InvalidMailCategoryException` like the other invalid values. Needs a core with the field: declare the minimum version in the module.
- `audience` only decides who sees the category in their profile. It never decides whether a mail goes out, and if it throws, the category is hidden. Without it, everyone sees the category.
- The core loads the categories of enabled modules only. A key without the module's prefix, a malformed key or a mandatory category without a reason throws `InvalidMailCategoryException` in local and testing (module CI included); in production the category is skipped with a warning.

## Marking the notifications

Each mail notification implements `HasMailCategory` and returns one of the keys:

```php
use App\Application\Mail\Contracts\HasMailCategory;
use Illuminate\Notifications\Notification;

final class TableReminderNotification extends Notification implements HasMailCategory
{
    public function mailCategory(): string
    {
        return 'my-module.reminders';
    }
}
```

Keep the keys in constants of the module (for example, on the provider) so the declaration and the notifications cannot drift apart.

A notification with no category, or with a key that is not registered, is still sent and leaves one warning per class in the log: that is how a module that was not adapted shows up.

## Saying what a mail is about

Sometimes what a person wants to stop is not a whole category but the notices about one game, campaign or tournament. A notification that is about one such thing also implements `HasMailSubject` and says which:

```php
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use Illuminate\Notifications\Notification;

final class TableReminderNotification extends Notification implements HasMailCategory, HasMailSubject
{
    public function mailCategory(): string
    {
        return 'my-module.reminders';
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return new MailSubjectDTO(
            type: 'my-module.table',
            id: $this->table->id,
            label: $this->table->title,
            typeLabel: 'my-module::mail.subjects.table', // «Mesa», shown before the name in the profile
        );
    }
}
```

- `type` is `<module>.<kind>`, with the same grammar as category keys; `id` matches `[A-Za-z0-9_-]{1,64}` (a UUID, a number or a slug). Both are stored with the person's choice and signed into a link, so they never change while the thing exists. Use one `type` per kind of thing, shared by every notification about it: the mute covers them all.
- `label` is the name people see. It is put on one line, cut to 160 characters and escaped wherever it is shown, so pass it as it is. `typeLabel` is an optional translation key, never free text.
- The constructor throws `InvalidArgumentException` for values it cannot store; return `null` when a mail is about nothing in particular. If `mailSubject()` throws, the mail is sent as if it had no subject (one warning in the log).
- Implementing it needs the core release with core#334: set `requires.core` accordingly.

What the core does with it:

- **Delivery**: muting a thing holds back every **optional** mail about it, whatever its category. Mandatory mail about it is always sent, and a mail without a subject is never held by a mute. If the mutes cannot be read, the mail goes out.
- **Footer**: an optional mail with a subject, to an account, adds «No recibir más avisos de «<label>»» above the category line, in HTML and text. The `List-Unsubscribe` header still unsubscribes from the category.
- **Link**: `/correos/silenciar/{user}/{type}/{id}?nombre=…&tipo=…`, signed for the account, the thing and its names, with no expiry. Opening it (`GET`) only asks to confirm, `POST` mutes and `DELETE` undoes; changing any part gives 403. It signs nobody in, works in maintenance and for accounts that still have to verify their email, and has the same rate limit as the unsubscribe links. Build it with `MailUnsubscribeLinksInterface::muteSubjectUrl($userId, $subject)`.
- **Profile**: the «Correos» tab lists what the person muted under «Avisos silenciados» (newest first, with the date), each with «Volver a recibir» (`DELETE /perfil/correos/silenciados` with `type` and `id`). The names are the ones stored when the person muted it (refreshed if they mute it again), so the row stays readable once the thing ended or its module is disabled; a `typeLabel` nobody translates any more is left out.
- **Anonymizing** an account deletes what it muted.

**When the module deletes the thing**, it forgets the mutes so the profile stops listing them:

```php
app(MailSubjectMuteServiceInterface::class)->forgetSubject('my-module.table', $table->id);
```

`MailSubjectMuteServiceInterface` also answers `isMuted($user, $subject)` and `mutedFor($user)`; never read `user_mail_subject_mutes` directly. The table holds one row per account and thing (`unique(user_id, subject_type, subject_id)`, plus an index on `(subject_type, subject_id)` for `forgetSubject()`); the account's deletion cascades.

## Notify the account, not its address

The core recognises the person only when the notification goes to the account:

```php
$user->notify(new TableReminderNotification($table));
```

`Notification::route('mail', $user->email)` sends to a bare address: the core cannot tell whose it is, so the person's choices are ignored. Use `route()` only for addresses that are not accounts (the organization's mailbox, a venue, a guest without an account). To send to a different address of the account (as the core does with the email change), implement `NamesMailRecipient` on the notification and still notify the account.

## What the core does

When a notification is about to go out by mail to an account, the core:

1. Sends nothing to an anonymized account.
2. Sends mail of a mandatory category always.
3. Holds back mail of an optional category the person turned off, while their optional mail is paused, or about a thing they muted.
4. Sends the mail if the preferences cannot be read: missing a cancellation is worse than one reminder too many.

Each reason to hold optional mail back is a `MailDeliveryRuleInterface` tagged `mail.delivery_rules`: `allows($user, $category, $subject)`, where `$subject` is the `MailSubjectDTO` of the notification or `null`. Mandatory categories never reach the rules, and a rule that throws lets the mail through.

The check runs when the mail is delivered, inside the queue worker for queued notifications, so a choice made while the mail was waiting counts.

## What every mail to an account carries

A module gets this for free, with nothing in its notifications, as long as `toMail()` returns a `MailMessage` (the core template or a Markdown view built on `<x-mail::message>`) and the notification goes to the account:

- **A footer** in the core template, HTML and text:
  - optional category: «¿No quieres recibir más correos como este? Darte de baja de «<category>» · Elegir qué correos recibes»;
  - mandatory category: «Este correo siempre se envía. Puedes elegir qué otros recibes en tu perfil»;
  - no registered category: only «Puedes elegir qué correos recibes en tu perfil».
- **The `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` headers** (RFC 8058) on optional categories only, so mail clients show their own unsubscribe button.

The unsubscribe link is signed for that account and that category, never expires and opens nothing else: no sign-in, no other category. Opening it only shows a confirmation page (spam filters open the links of a mail), where the person confirms with a button and can undo afterwards. A mandatory category can never be turned off by a link. It also works while the site is in maintenance and for accounts that still have to verify their email.

What does **not** get them:

- mail to a plain address (`Notification::route('mail', …)`: guests, the organization's mailbox), since there is no account to choose for;
- a `Mailable` returned from `toMail()`, since it may be addressed to anyone;
- a custom HTML view (`MailMessage::view()`) that does not use `<x-mail::message>`: it gets the headers, but not the footer.

To build these addresses yourself (for example in a page of the module), use `MailUnsubscribeLinksInterface`: `unsubscribeUrl($userId, $categoryKey)`, `oneClickUrl($userId, $categoryKey)` and `preferencesUrl()` (the «Correos» tab of the profile).

## Reading the categories

- `MailCategoryRegistryInterface` lists every category (`all()`, `find()`) and tells which one a notification belongs to (`forNotification()`).
- `MailPreferenceServiceInterface` lists the categories a person sees, grouped by section and with their state (`sectionsFor()`), saves their choices (`update()`, which refuses mandatory, unknown or hidden categories) and says whether a mail of a category reaches them (`receives()`).

## Where people choose

The profile has a **Correos** tab (`/perfil?tab=mail`, id `mail`, exported as `MAIL_TAB` in `useProfileTabs.ts`) that draws `sectionsFor()` as one card per section (core#453): icon (`MailPreferenceSectionDTO::$icon`), name and «2 de 3 activados» on top, one row with a switch per optional category (`MailPreferenceRow`, whose `control` slot takes any other control and receives the ids it must be labelled and described by), and the mandatory ones as a list with a padlock, their reason and no control at the end of the card (`MailMandatoryList`; a section with only mandatory ones shows only that), and a «Guardar cambios» button that sends only what changed to `PUT /perfil/correos` (`profile.mail-preferences.update`). The server validates every choice through `update()`; a refused one comes back as an error under `preferences.<category key>`. The tab is where mail footers and unsubscribe links point to. Above the categories sits the pause block (core#331): a week, a month or until the person resumes (`PUT /perfil/correos/pausa` with `duration` = `week|month|indefinite`; `DELETE` resumes; both behind the same middleware and throttle as the choices). The pause is one row in `user_mail_pauses` (`user_id` primary key, `paused_until` nullable: null = until the person resumes; resuming deletes the row). It is a delivery rule like any other (`PausedMailRule`, tagged in `mail.delivery_rules`): it holds back every optional mail, leaves mandatory mail alone, never reads or changes the per-category choices, and ends by itself because the end is compared with the current instant when a mail is sent (nothing is scheduled). A week or a month lasts through that display day, so «until 12 December» includes the 12th. If the pause cannot be read, the mail goes out. Anonymizing an account deletes it. Ask `MailPauseServiceInterface::current($user)` (null when there is none or it is over) instead of reading the table. Accounts paused right now: `SELECT COUNT(*) FROM user_mail_pauses WHERE paused_until IS NULL OR paused_until > <now in the storage zone>`.

## Opt-outs in the panel statistics

*Sistema → Configuración → Estadísticas de correo* (`mail.view_stats`) lists, for each optional category, how many accounts turned it off, out of how many can receive it, and the percentage (core#332), plus how many accounts have their optional mail paused right now. Only counts: never names or addresses. Ask `MailCategoryOptOutStatisticsInterface::report()` instead of reading the tables.

- «Can receive it» is the active accounts: neither anonymized nor deactivated, which count nowhere. A category without `audience` costs two grouped counts.
- With an `audience`, the closure runs on the active accounts in chunks of 500 and the result is cached for 10 minutes; someone who turned the category off and no longer sees it is not counted. A closure that throws counts nobody for that account.
- Mandatory categories are not listed, and neither are choices stored for categories that are no longer registered (a disabled module).

