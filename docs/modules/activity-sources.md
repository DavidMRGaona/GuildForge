# Activity sources

A module that runs things people take part in (tables, tournaments, events, forum posts) can tell other modules who took part and how, without either side importing the other: who played or ran a table, who organized or won a tournament, who attended an event. The achievements module keeps a record of it to grant badges; any other consumer may read it the same way. For the rest of the module SDK, see the [module development guide](./README.md).

`requires.core` is mandatory: set it to the core release with #774 or later. On an older core the module breaks: the container cannot build anything that takes `ActivityPublisherInterface`, and nothing reads its sources.

## Contents

1. [Pieces](#pieces)
2. [What a source may report](#what-a-source-may-report)
3. [Offering a source](#offering-a-source)
4. [Facts](#facts)
5. [History](#history)
6. [Describing subjects](#describing-subjects)
7. [Attributes](#attributes)
8. [Publishing changes](#publishing-changes)
9. [Reading sources](#reading-sources)
10. [Failing closed](#failing-closed)
11. [Testing](#testing)
12. [The core's own sources](#the-cores-own-sources)

## Pieces

| Piece | Where | What it does |
|---|---|---|
| `ActivitySourceInterface` | `App\Application\Activity\Contracts` | `type()`, `kind()`, `label()`, `facts($subjectId)`, `history($cursor, $limit)`, `describe($subjectIds, $viewer)` |
| `OffersActivityAttributes` | same | Optional: `attributes()` and `attributeValues($key)`, the attributes its facts carry and the values each can take |
| `ActivityKind`, `ActivityRole` | `App\Domain\Activity` | What a subject is (`game_session`, `campaign`, `tournament`, `event`, `forum_post`, `forum_discussion`, `membership_year`, `survey_response`, `venue_booking`, `account`, `profile_completed`, `proposal`) and what a person did in it (`player`, `director`, `organizer`, `creator`, `attendee`, `author`, `respondent`, `booker`, `member`, `proposer`, `owner`). A new value is added in the core when a module needs one |
| `ActivityFactDTO` | `App\Application\Activity\DTOs` | `userId` (account UUID, kept in lower case), `role`, `occurredAt` (an instant, kept in the storage zone), `attributes` (key → `ActivityAttributeValueDTO`), `placement` (`null`, or 1 = the winner) |
| `ActivityAttributeValueDTO`, `ActivityAttributeDTO` | same | A value (`id`, `label`) and an attribute (`key`, `label`) |
| `ActivitySubjectFactsDTO` | same | `subjectId` and its `facts`: one per person and role |
| `ActivityHistoryPageDTO` | same | `subjects` (`ActivitySubjectFactsDTO`, each once) and `nextCursor` (`null` on the last page) |
| `ActivitySubjectDTO` | same | `subjectId`, `title`, optional `url`: how a subject is shown |
| `ActivityRecorded` | `App\Domain\Activity\Events` | `sourceType` and `subjectId`, nothing else: «the facts of this subject may have changed» |
| `ActivityPublisherInterface` | `App\Application\Activity\Services` | `recorded($type, $subjectId)`: dispatches `ActivityRecorded` once the change commits, once per subject and outermost transaction |
| `ActivitySourceRegistryInterface` | same | The registered sources (`all()`, `find($type)`, `isUnavailable($type)`), as classes |
| `ActivityReaderInterface` | same | How consumers read sources: never throws, and every result says whether it was read |

## What a source may report

**Only what any signed-in member can know exists.** A badge, a counter or a ranking built on a fact is shown to other people, so a fact must never reveal anything they could not see on the module's own pages:

- published tables and campaigns, never private or draft ones;
- posts and discussions in categories every member sees, never hidden, deleted or moderated ones;
- answers to surveys that are not anonymous, never to anonymous ones.

When in doubt, leave it out. Something restricted is never reported, not even «just for the owner»: the consumer does not know who will see what it builds.

**One accepted exception: who played and who attended.** Who played a table, a campaign session, a campaign or a tournament is not public: the pages show counts only. The same goes for who attended an event (a confirmed registration of a published event). Sources still report the `player` and `attendee` roles, so a consumer can count games played and events attended. In exchange, a consumer may only ever show others **totals** built from those facts («played 12 tables», «attended 12 events»), never a specific subject to somebody its `describe()` would not show it to, and people can hide their achievements. Every other role and kind follows the rule above strictly: a hidden director, a membership that is not shown, a proposer who is not shown and a hidden profile are never reported.

**The same answer for everybody.** `facts()` and `history()` never read the signed-in user, the session or the request: they run in queued jobs and commands, where nobody is signed in. Never reuse a query scoped to «what the current user may see»: in a job it answers nothing, and the consumer would delete real facts. Decide with the any-signed-in-member rule above, written as a query of its own.

A subject that **stops counting** (a post hidden or deleted, a table made private, a player removed from it) is reported again: `facts()` returns what is true now (maybe nothing) and the module publishes the change (see [Publishing changes](#publishing-changes)), so whoever keeps a record corrects it.

## Offering a source

Implement `ActivitySourceInterface` and return the class from the provider:

```php
public function registerActivitySources(): array
{
    return [SessionActivitySource::class];
}
```

- `type()` is `<module>.<thing>` (`game-tables.session`): the module name, a dot and a name in snake_case or kebab-case, 100 characters at most. It must start with the module that registers it (`core.` for the core). Consumers store it with every fact, so it never changes.
- `kind()` is what its subjects are, the same for all of them. `label()` is translated («Partidas»).
- Sources are built from the container in each request or job that reads them, so they may take dependencies in their constructor. The registry keeps only the class and what it read when checking it (type, kind, owner), never an instance: keep no state across requests (no static properties or caches).

A class that is not a source, has an invalid type or one that does not start with its module is left out for good, with a warning (once a day per class and reason). Two classes with one type are **both** left out: a record kept from one would otherwise be read from the other. A class that throws while it is built is left out only until it builds again: the registry tries it again on the next lookup, so a passing failure in a long-running worker does not last until it restarts.

## Facts

`facts($subjectId)` returns the current truth of one subject: one `ActivityFactDTO` per person and role. A person may hold several roles in the same subject (a director who also played), each a fact of its own; the same person twice in the same role is a broken source. All the facts of one subject happened together: its players played together.

```php
public function facts(string $subjectId): array
{
    $table = $this->tables->findCounted($subjectId); // published, not private, completed

    if ($table === null) {
        return [];
    }

    $system = new ActivityAttributeValueDTO($table->gameSystemId, $table->gameSystemName);

    return [
        ...array_map(fn (string $id) => new ActivityFactDTO($id, ActivityRole::Director, $table->endedAt, ['game_system' => $system]), $table->directorIds),
        ...array_map(fn (string $id) => new ActivityFactDTO($id, ActivityRole::Player, $table->endedAt, ['game_system' => $system]), $table->playerIds),
    ];
}
```

- Empty when the subject does not exist, no longer counts or has not happened yet. Never throw for an unknown id.
- `userId` is an account UUID. Guests, NPCs and anything without an account are never reported.
- `occurredAt` is an instant (when the subject took place or ended, as the source decides). The DTO keeps it in the storage zone (PHP's default zone, which the application sets), so one built from `fromDisplayWallTime()` is the same instant and a consumer that stores it through query bindings never shifts it. The consumer shows it with `TimezoneServiceInterface`.
- `placement` is for what has a ranking (a tournament's standings): 1 is the winner; `null` otherwise.
- Ids are stable: a subject id and an attribute value id are a record's own id (a UUID, a number, a slug), never a title. They are letters, digits, `.`, `_`, `:` and `-`, 191 characters at most, starting with a letter or a digit. A UUID is kept in lower case everywhere (DTOs, publisher, reader: `ActivityIdentifierRule::stableId()`), so one from a route parameter and one from a table are the same subject.
- The DTOs check all of this when they are built and throw `InvalidArgumentException`; the core reads a source that throws as failed.

## History

`history($cursor, $limit)` returns every subject that counts, with its facts, for recalculating from scratch (a site that installs the achievements module years after its first table):

- In a stable order, `$limit` subjects at most per page (the core never asks for more than `ActivityHistoryPageDTO::MAX_LIMIT`, 500), each subject once.
- `$cursor` is `null` for the first page; afterwards it is the previous page's `nextCursor`. The cursor is yours and opaque to the caller (an encoded last id, say), never an offset that a new row would shift. `nextCursor` is `null` on the last page, and never the cursor it was given.
- A cursor you cannot read throws (`InvalidArgumentException`); never start again from the first page, or a walk would go round forever. The reader turns the throw into `Failed`.
- Every subject's facts are what `facts()` would return for it. Build them in batch (one query per page) rather than calling `facts()` once per subject.

## Describing subjects

`describe($subjectIds, $viewer)` gives title and link of the subjects asked for that `$viewer` may see (`null` is a guest), keyed by subject id, with the module's own access service; leave out the others. The consumer uses it to show a person their own progress («7 de 10: …») and in the panel. A link is a path of the site (`/mesas/…`) or an https address; the core drops any other link and keeps the title.

## Attributes

A fact may carry attributes, up to 20: a snake_case key (`game_system`, `tournament_profile`) and a value with a stable id and a label. Consumers compare ids («3 different systems», «played Pathfinder») and only show labels.

A source whose facts carry attributes also implements `OffersActivityAttributes`, so a rule builder can offer them without depending on the module:

```php
public function attributes(): array
{
    return [new ActivityAttributeDTO('game_system', __('game-tables::messages.activity.game_system'))];
}

public function attributeValues(string $key): array
{
    return $key === 'game_system'
        ? array_map(fn ($system) => new ActivityAttributeValueDTO($system->id, $system->name), $this->systems->all())
        : [];
}
```

Every attribute key its facts carry is listed by `attributes()`; an unknown key has no values.

## Publishing changes

Call `ActivityPublisherInterface::recorded($type, $subjectId)` wherever a subject's facts may have changed: it ended (through every path that ends it: `complete()` **and** `changeStatus()`), somebody joined or left, it was hidden, deleted, made private or stopped counting. Publishing for a save that changed nothing is harmless.

```php
public function __construct(private ActivityPublisherInterface $activity) {}

public function complete(GameTable $table): void
{
    DB::transaction(function () use ($table): void {
        // …
        $this->activity->recorded('game-tables.session', $table->id);
    });
}
```

- Call it inside the change's transaction or after it: `ActivityRecorded` is dispatched once the transaction commits (at once outside one), never when it rolls back. A subject published several times in one transaction is announced once.
- Nothing is dispatched while an impersonation lasts: the change is rolled back anyway.
- It never throws. An invalid type or id is dropped with a warning; a listener's failure (a queue that cannot take the job) is logged and never reaches the module.
- The event carries ids only. Never dispatch `ActivityRecorded` yourself.

Time alone changes nothing: a source whose subjects count once a date passes publishes from its own scheduled task.

## Reading sources

A consumer listens to `ActivityRecorded` (queued, after commit) and reads through `ActivityReaderInterface`, never by calling sources or the registry's classes:

```php
$result = $this->activity->facts($event->sourceType, $event->subjectId);

if (! $result->isRead()) {
    return; // Missing or failed: keep what you had
}

$this->ledger->replace($event->sourceType, $event->subjectId, $result->facts);
```

| Method | Returns |
|---|---|
| `sources()` | `list<ActivitySourceSummaryDTO>` (`type`, `kind`, `label`, `module`) of the sources offered now; one that fails is left out |
| `facts($type, $subjectId)` | `ActivityFactsResultDTO`: `outcome`, `facts`, `isRead()` |
| `history($type, $cursor, $limit)` | `ActivityHistoryResultDTO`: `outcome`, `page` (`ActivityHistoryPageDTO`, `null` unless read), `isRead()` |
| `describe($type, $subjectIds, $viewer)` | `ActivityDescriptionsResultDTO`: `outcome`, `subjects` (a list: numeric ids would turn into int keys), `find($id)`, `isRead()` |
| `attributes($type)`, `attributeValues($type, $key)` | Lists, empty when the source offers none, is missing or fails |

`outcome` is an `ActivityReadOutcome`:

- `Read`: the answer. For `facts()`, the whole truth: empty means the subject no longer counts, so its facts go.
- `Missing`: no module registers the type (it is disabled or gone).
- `Failed`: a source for it is registered but could not be read: two classes share the type, it could not be built (while a class fails before its type can be read, every type nobody offers reads as `Failed`, since it may be that one's), it threw, or it returned something the contract refuses (a fact twice, a page longer than asked, a cursor pointing back to itself, a `describe()` with nothing well formed).

`Missing` and `Failed` both mean «unknown»: **never delete anything kept from a source because of them**. A history walk that meets a page it could not read stops there and tries again later.

The reader only catches a page that points back to itself. A walk keeps the cursors it has seen (or a cap on pages) and stops when one comes back: a broken source could otherwise alternate between two pages forever.

Facts of **anonymized** accounts are left out of `facts()` and `history()` (one query per call), so a recalculation never brings back what was erased; a subject may then come back with no facts. Deactivated accounts keep theirs: whether they count is the consumer's choice.

## Failing closed

| What happens | Result |
|---|---|
| The class is not a source, has an invalid or foreign type | Left out of the registry for good (`Missing`), warned once a day per class and reason |
| The class throws while it is built | Left out until it builds again, tried on the next lookup (`Failed`) |
| Two classes with one type | Both left out (`Failed`), warned |
| `facts()`, `history()` or `describe()` throws | `Failed`, warned once a day per type and reason |
| Facts with the same person twice in one role, or not `ActivityFactDTO` | `Failed` |
| A history page longer than asked, or whose `nextCursor` is the cursor given | `Failed` |
| `describe()` returns subjects not asked for | Dropped; malformed entries dropped and warned; nothing well formed at all is `Failed` |
| A fact of an anonymized account | Left out |
| A link that is neither a site path nor https | Dropped, the title kept |
| An id that cannot be a subject's | `facts()` is read with no facts, without asking the source |

Warnings name the type, the module, the class and the reason, and an exception only by its class: a message may carry what a person can see. One source failing never affects another.

## Testing

Extend `Tests\Support\Activity\ActivitySourceTestCase`, set `$moduleName` and say how to build the source and a subject that counts. The test case enables the module before each test (`$autoEnableModule = true`), so its provider registers the source as on a site, and takes the owner from `$moduleName`:

```php
final class SessionActivitySourceTest extends ActivitySourceTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(SessionActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        return $this->completedTableWithPlayers()->id;
    }
}
```

A test with another base class can use the trait `ActivitySourceContractTests` directly: it implements `activitySourceOwner()` and boots the module first (a `ModuleTestCase` with `$moduleName` and `$autoEnableModule = true`).

It checks that the type starts with the module, the source is registered and read by the core, `facts()` of an unknown id is empty, the facts of a counted subject pass the core's rules, the history walks to the end without repeats and agrees with `facts()`, `describe()` answers only for what was asked, and the attributes are valid. Override `unknownActivitySubjectId()` when the source's ids are not UUIDs.

Test the privacy rule yourself, beyond the contract: a private table, a hidden category, an anonymous survey or a guest never appear in `facts()` or `history()`, the answer is the same with nobody signed in, an unreadable cursor throws, and each change that makes something stop counting publishes it (`Event::fake([ActivityRecorded::class])`).

## The core's own sources

The core reports three things only it knows (core#777), registered under `core.` in `ActivityServiceProvider::CORE_SOURCES`, before any module's. A consumer reads them like any other source, through `ActivityReaderInterface`. None of them depends on who is signed in.

| Type | Kind | Role | Attributes | Subject id | Date (`occurredAt`) |
|---|---|---|---|---|---|
| `core.account` | `account` | `owner` | none | the account's UUID | when the account was created |
| `core.profile-completed` | `profile_completed` | `owner` | none | the account's UUID | when the profile became complete |
| `core.proposal` | `proposal` | `proposer` | `content_type`: `event` or `article` | `events:<uuid>` or `articles:<uuid>` | when a reviewer approved it |

**What counts is only what any signed-in member can know exists** (see [What a source may report](#what-a-source-may-report)):

- **Accounts and profiles** count only while the account is active (neither deactivated nor anonymized), has a public name and its profile is visible to every signed-in member (`public` or `members`). A profile only the association (`association`) or its owner (`hidden`) may see is left out, as is an account without a public name (one of only spaces, tabs or line breaks is none: PHP decides, as for the profile page). A deactivated account counts again once restored. What publishes them is in the table below.
- **`core.account`**: one subject per such account, the account age. The consumer decides what seniority means from `occurredAt`.
- **`core.profile-completed`**: the account has a public name, an avatar and a «Sobre mí» (`ProfileCompletenessRule`). It counts by `user_profiles.completed_at`, a column written only through that PHP rule (`ProfileCompletion`), so SQL never disagrees with it. The date is set the first time all three are there and cleared when one goes, so changing something unrelated (visibility, banner, the text of «Sobre mí») never moves it. If the profile stops being complete, `facts()` is empty; if it is completed again it counts again with the new date. What a consumer already granted is the consumer's business. **A site that had complete profiles before the column existed runs `php artisan profiles:stamp-completed [--dry-run] [--chunk=500]` once after deploying**: it stamps them with their last change as the date and publishes them. It is idempotent and never runs on a request; until it runs those profiles do not count.
- **`core.proposal`**: an event or article proposed from the web (core#570) that was approved, is still published and whose proposer's account is active. Content created in the panel is not a proposal. Unpublishing it in the panel, deleting it or deactivating or anonymizing the proposer stops it counting. **Event proposals count only while the site shows who proposed them** (the events proposals setting «Mostrar quién propone», off by default): a member could not know who proposed one otherwise. **An article counts only while its byline (`author_id`) is its proposer**: the panel may sign it as somebody else, and then the proposer is no longer shown (changing the byline publishes it again). Changing that setting queues `PublishApprovedEventProposalsJob`, which publishes every approved event proposal in batches. The history goes through events first (when they count), then articles, each by id.

**When they are published.** The core publishes through model observers, so any path counts: registration, the panel, a command, a seeder.

| Change | Published |
|---|---|
| An account is created | `core.account` |
| The public name is set or cleared | `core.account`, and `core.profile-completed` only when that completes or breaks the profile |
| The avatar or «Sobre mí» changes | `core.profile-completed`, only when that completes the profile or breaks it |
| The profile's visibility changes between one every member sees (`public`, `members`) and one they do not | `core.account`, `core.profile-completed` |
| An account is anonymized, deactivated, restored or deleted | `core.account`, `core.profile-completed` and each of its approved proposals |
| A proposal is approved (reviewer or publishing directly), or an approved one is unpublished, published again, reassigned, re-signed (an article's byline) or deleted | `core.proposal` |
| The events «Mostrar quién propone» setting changes | every approved event proposal, queued |

Submitting, returning or rejecting a proposal publishes nothing: only an approved one counts. Changes made with a query builder or a mass `update()` raise no model event and are not published; the consumer's recalculation (`history()`) corrects them.

**Describing them.** `describe()` of an account or a profile names the person and links `/miembros/<handle>` only when the viewer may see the profile (`ProfileAccessRule`); for anybody else (a guest, for instance) the title names nobody («Cuenta de una persona») and there is no link. An account that does not count is not described at all, not even to its owner. A proposal gives the title of the published event or article and its public link (`/eventos/<slug>`, `/articulos/<slug>`): counting already means published, which any guest can open.

**Testing a core source** uses the trait directly (it is not a module): `use ActivitySourceContractTests;`, `activitySourceOwner()` returns `core`, and the test class has the database (`LazilyRefreshDatabase`). `Tests\Support\Activity\FakesActivityEvents` drops the scoped publisher when it fakes `ActivityRecorded`, so a publisher built before the fake does not dispatch for real. Laravel builds an observer for every event it handles, so an observer keeps nothing between events except what it ties to the model itself: a permanent deletion empties the proposals' proposer, so `forceDeleting` reads them while the account still has them and `forceDeleted` announces them once it is gone. The publisher announces a subject once per outermost transaction (savepoints included), so a change that touches the same subject through several observers is announced once; the observers open a savepoint only around the queries that can fail, never around a publication. If an approved proposal has no review date, its creation date is used, never its last edit.
