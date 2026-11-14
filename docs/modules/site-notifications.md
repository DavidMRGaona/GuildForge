# Site notifications

Besides mail, the core has a notification centre: a bell in the header, for signed-in accounts, with the unread count and the latest notices, and an «Avisos» page with all of them (core#491). A module puts a notice there with an ordinary Laravel notification sent through the core's `site` channel. The person turns its notices on and off with the same mail categories as the mail, and muting a thing from a mail footer mutes its notices too. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release with core#491. An older core has no `HasSiteNotification` and no `site` parameter on `MailCategoryDTO`, so the notification would not load.

## Declaring that a category has site notices

Site notices belong to a [mail category](./mail-categories.md). Say which categories also appear in the notification centre with `site: true`. A category that should never be mailed, only shown on the site, also says `mail: false`:

```php
use App\Application\Mail\DTOs\MailCategoryDTO;

public function registerMailCategories(): array
{
    return [
        // Mailed and shown on the site: two switches in the profile, «En la web» and «Por correo»
        MailCategoryDTO::optional(
            key: 'my-module.replies',
            section: 'my-module::mail.categories.section',
            label: 'my-module::mail.categories.replies.label',
            description: 'my-module::mail.categories.replies.description',
            digestible: true,
            site: true,
        ),
        // Only on the site («me gusta»): no mail, no unsubscribe link, out of the opt-out statistics
        MailCategoryDTO::optional(
            key: 'my-module.likes',
            section: 'my-module::mail.categories.section',
            label: 'my-module::mail.categories.likes.label',
            description: 'my-module::mail.categories.likes.description',
            site: true,
            mail: false,
        ),
    ];
}
```

- Every category is mail only unless it says `site: true` (`mail` defaults to `true`, `site` to `false`), so nothing changes for categories declared before core#491.
- A category needs at least one channel, and the daily digest (`digestible: true`) needs mail: otherwise the registry throws `InvalidMailCategoryException` (in local and testing; elsewhere the category is skipped with a warning).
- A **mandatory** category with `site: true` is always shown on the site too, with the same padlock and reason in the profile.
- `audience` works as for mail: it only decides who sees the category in their profile.

## Marking the notification

The notification implements `HasSiteNotification` (and `HasMailCategory`, which it already needs for mail) and adds the channel to `via()`:

```php
use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class ReplyPostedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function mailCategory(): string
    {
        return 'my-module.replies';
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return new MailSubjectDTO('my-module.topic', $this->topicId, $this->topicTitle, 'my-module::mail.subjects.topic');
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        // Checked when it is delivered, not when it was queued
        if (! $this->topics->canRead($notifiable, $this->topicId)) {
            return null;
        }

        return new SiteNotificationDTO(
            title: __('my-module::notifications.reply', ['author' => $this->authorName, 'topic' => $this->topicTitle]),
            url: route('my-module.topics.show', ['topic' => $this->topicSlug, 'mensaje' => $this->postId], absolute: false).'#m'.$this->postId,
            body: $this->excerpt,
            icon: 'message-square',
            groupKey: 'my-module.topic:'.$this->topicId,
            actorId: $this->authorId,
            groupedTitle: fn (SiteNotificationGroupDTO $group): string => trans_choice(
                'my-module::notifications.replies_grouped',
                $group->otherActors,
                ['author' => $this->authorName, 'count' => $group->otherActors, 'topic' => $this->topicTitle],
            ),
        );
    }
}
```

`SiteNotificationDTO`:

| Field | What it is |
|---|---|
| `title` | What happened, in one line («Ana respondió en «La tumba»»). Required. |
| `url` | A path of this site (`/foro/temas/la-tumba#m42`). Absolute addresses, `//host`, backslashes and whitespace throw `InvalidArgumentException`. Use `route(..., absolute: false)`. |
| `body` | A short excerpt, optional. Plain text: never HTML. |
| `icon` | Optional Lucide name in kebab case. The bell draws a closed list, `App\Application\SiteNotifications\SiteNotificationIcons::NAMES` (the [mail section icons](./mail-categories.md#declaring-them) plus `at-sign`, `ban`, `chart-bar`, `circle-check`, `clipboard-check`, `file-text`, `flag`, `images`, `message-circle`, `message-square`, `quote`, `thumbs-up`, `user-plus` and `vote`; the same names as `siteNotificationIconMap` in `resources/js/utils/icons.ts`, kept equal by a core test). Any other name is stored and shown with the bell's default icon, and the channel logs a warning once per class and icon (core#667). Needs core ≥ the release with #667 for the six icons after `at-sign`; on an older core they show a bell. |
| `groupKey` | Optional. Unread notices of the person with the same key become one row (see below). `[A-Za-z0-9._:-]{1,191}`; start it with the module name. |
| `actorId` | Optional. The account that caused the notice. A notice to that same account is never stored, and groups count distinct actors with it. |
| `groupedTitle` | Optional closure `fn (SiteNotificationGroupDTO $group): string`, the title of a group row. Called only while the group holds more than one notice. |

Every text is put on one line, cut (title 160 characters, body 300, ending in «…») and escaped by Vue wherever it is shown, so pass names written by people as they are. Write the texts when the notice is built, in the locale of that moment, as for the mail.

## Groups

A topic with ten replies in an afternoon should not fill the list. When a notice has a `groupKey` and the person has an **unread** row with the same key, that row takes it instead of a new one:

- its count goes up by one and the row moves to the top, with the newest notice's title, body, link and icon;
- the title is `groupedTitle($group)` when given: `$group->count` is the number of notices in the row, `$group->otherActors` the distinct actors other than the newest one (without actor ids, `count - 1`). «Ana y 3 más respondieron en «La tumba»» is `otherActors` = 3. Use `trans_choice()` for the plural;
- without `groupedTitle`, the row keeps the newest title and the bell shows «y N avisos más» under it.

Once the person opens the row (or marks everything read), the next notice starts a new one. Use one key per thing the notices are about (`my-module.topic:<id>`), never one per person or per kind.

## What the core does

When a notification goes through the `site` channel to an account, the core, at delivery time (inside the queue worker for a queued notification):

1. Stores nothing for anything that is not an account, nor for deactivated or anonymized accounts.
2. Stores nothing, with one warning per class, when the notification does not implement `HasSiteNotification` or its category is not registered or has no `site: true`.
3. Shows mandatory categories always. Holds back an optional one the person turned off **on the site**, or one about a thing they muted (`HasMailSubject`, the same mutes as the mail). Turning the mail off, or pausing it, does not touch the site notices. If the choices cannot be read, the notice is shown.
4. Calls `toSiteNotification($notifiable)`. `null` stores nothing; an exception stores nothing and logs a warning.
5. Stores nothing when `actorId` is the recipient.
6. Logs a warning, once per class and icon, when `icon` is not one the bell draws (`SiteNotificationIcons::isKnown()`); the notice is still stored and shows the default icon.
7. Stores the notice, or adds it to the unread row of its group.

For a category with `mail: false`, the mail channel sends nothing even if `via()` lists `mail`, and the profile has no mail control for it.

**Never notify about what the person cannot see.** Check it in `toSiteNotification()` (and in `toMail()` or `shouldSend()` for the mail), not when you queue the notification: access may change while it waits.

## The bell and the «Avisos» page

- The shared Inertia prop `siteNotifications` is `{ unread: number }` for a signed-in account and `null` for guests. The bell is hidden for accounts that must still verify their email (its routes are closed to them, like every signed-in route).
- The counter refreshes with a light JSON request (`GET /avisos/contador`, never a page visit) every minute while the tab is visible, and on return to the tab. Opening the bell loads the latest 8 (`GET /avisos/recientes`). Both answer `{ unread, items? }` and are limited to 120 requests per minute.
- Every row links to `/avisos/{id}`, which marks it read and redirects to its `url`, so a link to a message (`#m42`) lands on it. Someone else's notice is a 404.
- `/avisos` lists every row, 20 per page, newest first. `POST /avisos/leidos` marks everything read (JSON for the bell, a redirect back for the page).
- Nothing is real time: core#493 covers that.

## When the module deletes the thing

Forget its notices together with its mutes, with the call the mail already asks for:

```php
app(MailSubjectMuteServiceInterface::class)->forgetSubject('my-module.topic', $topic->id);
```

It deletes every notice whose `HasMailSubject` was that thing, for everybody, so the bell never links to a page that is gone. `SiteNotificationServiceInterface::forgetSubject()` does the same for site notices alone.

## Retention and privacy

- Read notices are deleted 90 days after they were read, unread ones 180 days after their latest notice: `site-notifications:prune`, scheduled daily.
- Anonymizing an account deletes its notices and its site choices; deleting it cascades.
- **Notices about an anonymized account (core ≥ the release with #537).** The title and body are stored already written, with the name in them, and the core cannot tell where. So anonymizing also *forgets* every row, of anybody, that has the account among its `actorId`s (`SiteNotificationServiceInterface::forgetActor()`, in the same transaction as the anonymization): read or unread, single or grouped. A group goes whole even when it has other actors, because its title («Ana y 2 más») may name the account; the bell count is recalculated from the remaining rows, so it drops with them. Pass `actorId` in every notice that names a person, or it cannot be forgotten. The call is idempotent. Groups keep at most 100 actors: an account trimmed off a very big group is not found.
- The notices live in `site_notifications` (one row per notice or group, `user_id` with cascade) and the choices in `user_site_notification_preferences` (only what the person changed; no row shows the notices). Never read or write them directly: use `SiteNotificationServiceInterface` (counts, lists, opening, marking read, forgetting) and `SiteNotificationPreferenceServiceInterface` (`receives()`, `update()`). The state of each category for the profile comes from `MailPreferenceServiceInterface::sectionsFor()`: `MailPreferenceDTO::$mail`, `$site` and `$siteEnabled`.

## The core's site news

The core uses the bell itself for its own content (core#613): the first publication of an event, an article or a gallery reaches every active account as a notice of the optional category `core.site-news` («Novedades», site only, on by default).

- `SiteNewsObserver` watches the three models. A save that leaves the content published for the first time (created published, or `is_published` turned on) writes one row in `site_news_announcements` in the same transaction, and queues `AnnounceSiteNewsJob` once it commits. Taking the content down and publishing it again, or editing it, says nothing; an event that has already ended is no news.
- `AnnounceSiteNewsJob` splits the active accounts in batches of 100, one `DeliverSiteNewsJob` each, leaving out who published it and who proposed it. Each batch goes straight to the site channel (the news is never mailed), so `Notification::fake()` in a module's tests does not see it. `SiteNewsNotification` reads the content again when it is delivered (`SiteNewsReaderInterface`) and shows nothing once it is no longer published.
- Unread news of a person share the group key `core.news` («3 novedades en el sitio»). Deleting the content forgets its notices (subject types `core.event`, `core.article`, `core.gallery`).

A module that wants the same for its own content (a new announcement, a general survey opened) declares its own site-only category and sends its own notification: never `core.site-news`.

## Testing

Send the notification to an account and read what the core stored:

```php
$user = UserModel::factory()->create();

$user->notify(new ReplyPostedNotification(/* … */));

$items = app(SiteNotificationServiceInterface::class)->latest($user, 10);
$this->assertSame('Ana respondió en «La tumba»', $items[0]->title);
```

Register the module's categories first in tests that build their own registry. To check that a person who lost access gets nothing, make `toSiteNotification()` return `null` and assert `unreadCount()` is 0.

Check that every notice asks for an icon the bell draws, so a typo fails in the module's CI instead of showing a bell:

```php
$notice = (new ReplyPostedNotification(/* … */))->toSiteNotification($user);

$this->assertTrue(SiteNotificationIcons::isKnown((string) $notice?->icon));
```
