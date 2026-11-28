# Bots

A bot is an account that is not a person and acts for the site: the publisher of a forum, for example, whose posts must not be signed by a member. The core keeps the account, hides it from everything that deals with people (sign-in, profiles, activity, impersonation, lists of accounts) and gives modules three things: a way to offer a bot, a way to ask for its account id and a way to show it as an author.

Requires core ≥ the release with #863; set `requires.core` to it.

## Contents

1. [What a bot is](#what-a-bot-is)
2. [Registering a bot](#registering-a-bot)
3. [Asking for the account id](#asking-for-the-account-id)
4. [Showing a bot as an author](#showing-a-bot-as-an-author)
5. [Recognising a bot](#recognising-a-bot)
6. [Adopting an existing account](#adopting-an-existing-account)
7. [What bots are kept out of](#what-bots-are-kept-out-of)
8. [Rules for module code](#rules-for-module-code)
9. [Testing](#testing)

## What a bot is

A bot is a row in `users` whose `bot_key` is set (`<module>.<name>`, unique); people have `bot_key` null. Its password is random and never kept, its email is `bot-<id>@bots.invalid`, and it has no roles, no profile and no sessions. Its public name (`display_name`) is null by default, which means **the site's name** (`SiteBrandingServiceInterface::siteName()`); a bot that declares its own `name()` keeps that instead. `bot_key` is not mass assignable: only `BotsInterface` sets it.

The core has no bots of its own: only enabled modules offer them. The account is created the first time a module asks for it and stays when the module is disabled (its posts keep an author); it can be recognised and described then, but not asked for by key until the module is enabled again.

## Registering a bot

Implement `App\Application\Bots\Contracts\BotInterface` and return the class from the provider's `registerBots()`. Bots are built from the container, so they may take dependencies in their constructor.

```php
use App\Application\Bots\Contracts\BotInterface;

final class PublisherBot implements BotInterface
{
    public function key(): string
    {
        return 'forum.publisher';
    }

    public function label(): string
    {
        return __('forum::messages.bots.publisher');
    }

    public function description(): string
    {
        return __('forum::messages.bots.publisher_description');
    }

    public function name(): ?string
    {
        return null; // the site's name
    }
}
```

```php
public function registerBots(): array
{
    return [PublisherBot::class];
}
```

| Method | Meaning |
|---|---|
| `key()` | `<module>.<name>`: the module's own name, a dot, then snake or kebab-case (the rules of a site block key, `BotKey`, at most 100 characters). Never change it once an account exists: the account is found by it. |
| `label()` | What the administration sees in lists. |
| `description()` | What the bot does, for the administration. |
| `name()` | The public name of the account, or `null` for the site's name. |

The registry (`BotRegistryInterface`) fails closed and never throws. It leaves a bot out, with a warning once a day per class and reason (`bots:warned:`), when:

- the class is not a `BotInterface`, or cannot be built (the warning names only the exception's class);
- the key is invalid, or does not start with the registering module's name;
- two classes give the same key (neither is offered);
- the class is registered by two modules (the second registration is ignored).

## Asking for the account id

`App\Application\Bots\Services\BotsInterface::id($key)` returns the id (lower case UUID) of the bot's account, creating it the first time. It throws `App\Domain\Bots\Exceptions\UnknownBotException` when no enabled module registers the key.

```php
$authorId = $this->bots->id('forum.publisher');
```

Creation takes a lock per key, so two callers at once get the same account. Call `id()` **outside a database transaction when you can**: an outer transaction keeps the new row uncommitted after the lock is released, and a concurrent caller may then not see it and try to create the account again, hitting the unique index on `bot_key`. A module that calls it at the start of a job or request, before opening its own transaction, never sees this.

The service is bound `scoped`: inject it, never keep it between requests.

## Showing a bot as an author

Every Eloquent query on `UserModel` leaves bots out (global scope `WithoutBots`). A module that resolves the names of the authors of its posts must add them back **in the query that resolves the names**, or the bot's posts show no author:

```php
$authors = UserModel::query()
    ->withBots()
    ->whereIn('id', $authorIds)
    ->get();
```

`withBots()` is the only way in; `withoutGlobalScopes()` is not (see [Rules for module code](#rules-for-module-code)). Show the account's `display_name` and, when it is null, the site's name; or let the core do it with `describe()`:

```php
$bots = $this->bots->describe($authorIds); // array<id, BotDTO>
```

`describe()` returns only the ids that are bots, keyed by id in lower case (people, unknown and malformed ids are left out; upper case ids are accepted), as `BotDTO`: `id`, `key`, `module`, `name` (the account's own name or the site's) and `avatarPublicId`. It reads deactivated accounts too and writes nothing. A bot has no profile: never link to `/miembros/...` for it, and never ask the profile card for it (it answers `null`).

## Recognising a bot

`BotsInterface::isBot(?string $accountId)` tells whether an account is a bot, even one whose module is now disabled (`null` and malformed ids are false). On a model, `UserModel::isBot()` does the same from `bot_key`. Use them to decide what a bot never does: it never receives mail, never has preferences and never joins a table.

## Adopting an existing account

A site that had a system account before bots existed (the forum's old author) turns it into the bot instead of creating a second one, so its posts keep their author:

```php
$adopted = $this->bots->adopt('forum.publisher', $accountId); // bool
```

`adopt()` throws `UnknownBotException` for an unregistered key, and returns `false`, changing nothing, for a malformed or unknown id, an account taken meanwhile by the same key on another account, or any account `App\Infrastructure\Bots\BotAdoptionRule` does not allow. Adopting wipes the login, so the rule only takes an account that could never have belonged to a person:

- the email is on an `.invalid` domain (`.invalid` itself or a subdomain);
- the email was never verified, and the account is not anonymized;
- it has no two-factor authentication (a state that cannot be read refuses, and is warned once a day);
- it holds no role but the base role `member` (deactivated accounts count);
- it is not the account of another bot (the same key again is allowed).

The account is locked first and everything happens in one transaction. It removes every role (written to the role history, source `system`), replaces the password by a random one and clears the remember token and the deactivation, forgets the public profile (and the banner), and deletes the account's notices, their seen mark, push subscriptions and pending password reset. Afterwards it invalidates the account's authorization cache and publishes `core.account` and `core.profile-completed` for it, so consumers stop counting the account. Already the bot of this key: `true`, nothing done again. After `adopt()`, `id()` returns that account.

## What bots are kept out of

| Where | What happens |
|---|---|
| Account queries | Hidden by `WithoutBots`, so lookups and lists of accounts never include them. |
| Sign-in | Never, even with the right password; no session or remember cookie survives (sessions of a bot end). |
| Password reset | Never. |
| Panel | `canAccessPanel()` is false even with `admin.access` given by hand. |
| Impersonation | Never a valid target. |
| Roles | `users:assign-member-role` and every path that gives the base role skip them. |
| Profiles | No profile row is created, and an adopted account's is forgotten. |
| Activity | Creating, changing, deactivating or restoring a bot announces nothing, and `ActivityReaderInterface` drops the facts of bots, so achievements never count them. |
| Notices | The notification centre, mail and push never deliver to them; adoption deletes what was stored. |

## Rules for module code

- **Never call `withoutGlobalScopes()` without arguments on `UserModel`**: it drops `WithoutBots` and brings every bot into the query. Name the scope you want to drop (`withoutGlobalScope(SoftDeletingScope::class)`, `withTrashed()`) or use `withBots()` when you mean bots.
- **A raw query on `users`** (`DB::table('users')`, `->from('users')`, a join) skips the scope: filter `bot_key` yourself (`whereNull('users.bot_key')`, or `whereNotNull` when you want bots only). The core's architecture test (`RawUserQueriesTest`) scans only the core's `app/`; nothing checks a module, so review yours.
- Never sign a bot in, give it roles or a profile, or send it mail. It acts only when module code writes in its name, with the id from `BotsInterface::id()`.
- Show a bot's name through `display_name` or the site's name, never through `name` (the account name is the bot's label).

## Testing

Register the bot in the module's provider and ask `app(BotsInterface::class)->id('<module>.<name>')`: the account is created on the first call and reused on the second. Check the author's name with `withBots()` or `describe()`, and that a person's posts are unchanged. To test adoption, create an account with an `.invalid` email, no verification and only the `member` role, and call `adopt()`; any change (a verified email, another role, two-factor on) must make it `false` and change nothing. See `tests/Feature/Bots/` in the core for examples (`BotsServiceTest`, `BotAdoptionTest`, `BotExclusionTest`).
