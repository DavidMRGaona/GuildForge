# Activity sources

A module that runs things people take part in (tables, tournaments, events, forum posts) can tell other modules who took part and how, without either side importing the other: who played or ran a table, who organized or won a tournament, who attended an event. The achievements module keeps a record of it to grant badges; any other consumer may read it the same way. For the rest of the module SDK, see the [module development guide](./README.md).

Requires core ≥ the release with #774; set `requires.core` to it. An older core does not know `registerActivitySources()`: the module's activity would simply never be read.

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

## Pieces

| Piece | Where | What it does |
|---|---|---|
| `ActivitySourceInterface` | `App\Application\Activity\Contracts` | `type()`, `kind()`, `label()`, `facts($subjectId)`, `history($cursor, $limit)`, `describe($subjectIds, $viewer)` |
| `OffersActivityAttributes` | same | Optional: `attributes()` and `attributeValues($key)`, the attributes its facts carry and the values each can take |
| `ActivityKind`, `ActivityRole` | `App\Domain\Activity` | What a subject is (`game_session`, `campaign`, `tournament`, `event`, `forum_post`, `forum_discussion`, `membership_year`, `survey_response`, `venue_booking`, `account`, `profile_completed`, `proposal`) and what a person did in it (`player`, `director`, `organizer`, `creator`, `attendee`, `author`, `respondent`, `booker`, `member`, `proposer`, `owner`). A new value is added in the core when a module needs one |
| `ActivityFactDTO` | `App\Application\Activity\DTOs` | `userId` (account UUID, kept in lower case), `role`, `occurredAt` (an instant), `attributes` (key → `ActivityAttributeValueDTO`), `placement` (`null`, or 1 = the winner) |
| `ActivityAttributeValueDTO`, `ActivityAttributeDTO` | same | A value (`id`, `label`) and an attribute (`key`, `label`) |
| `ActivitySubjectFactsDTO` | same | `subjectId` and its `facts`: one per person and role |
| `ActivityHistoryPageDTO` | same | `subjects` (`ActivitySubjectFactsDTO`, each once) and `nextCursor` (`null` on the last page) |
| `ActivitySubjectDTO` | same | `subjectId`, `title`, optional `url`: how a subject is shown |
| `ActivityRecorded` | `App\Domain\Activity\Events` | `sourceType` and `subjectId`, nothing else: «the facts of this subject may have changed» |
| `ActivityPublisherInterface` | `App\Application\Activity\Services` | `recorded($type, $subjectId)`: dispatches `ActivityRecorded` once the change commits |
| `ActivitySourceRegistryInterface` | same | The registered sources (`all()`, `find($type)`), as classes |
| `ActivityReaderInterface` | same | How consumers read sources: never throws, and every result says whether it was read |

## What a source may report

**Only what any signed-in member can know exists.** A badge, a counter or a ranking built on a fact is shown to other people, so a fact must never reveal anything they could not see on the module's own pages:

- published tables and campaigns, never private or draft ones;
- posts and discussions in categories every member sees, never hidden, deleted or moderated ones;
- answers to surveys that are not anonymous, never to anonymous ones;
- confirmed registrations of published events, never guests or pending sign-ups.

When in doubt, leave it out. Something restricted is never reported, not even «just for the owner»: the consumer does not know who will see what it builds.

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

A class that cannot be built, is not a source, has an invalid type or one that does not start with its module, is left out with a warning (once a day per class and reason). Two classes with one type are **both** left out: a record kept from one would otherwise be read from the other.

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
- `occurredAt` is an instant (when the subject took place or ended, as the source decides); the consumer converts it with `TimezoneServiceInterface`.
- `placement` is for what has a ranking (a tournament's standings): 1 is the winner; `null` otherwise.
- Ids are stable: a subject id and an attribute value id are a record's own id (a UUID, a number, a slug), never a title. They are letters, digits, `.`, `_`, `:` and `-`, 191 characters at most, starting with a letter or a digit.
- The DTOs check all of this when they are built and throw `InvalidArgumentException`; the core reads a source that throws as failed.

## History

`history($cursor, $limit)` returns every subject that counts, with its facts, for recalculating from scratch (a site that installs the achievements module years after its first table):

- In a stable order, `$limit` subjects at most per page (the core never asks for more than `ActivityHistoryPageDTO::MAX_LIMIT`, 500), each subject once.
- `$cursor` is `null` for the first page; afterwards it is the previous page's `nextCursor`. The cursor is yours and opaque to the caller (an encoded last id, say), never an offset that a new row would shift. `nextCursor` is `null` on the last page, and never the cursor it was given.
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

- Call it inside the change's transaction or after it: `ActivityRecorded` is dispatched once the transaction commits (at once outside one), never when it rolls back.
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
- `Missing`: nobody offers the type now (its module is disabled or gone).
- `Failed`: the source could not be built, threw, or returned something the contract refuses (a fact twice, a page longer than asked, a cursor pointing back to itself).

`Missing` and `Failed` both mean «unknown»: **never delete anything kept from a source because of them**. A history walk that meets a page it could not read stops there and tries again later.

## Failing closed

| What happens | Result |
|---|---|
| The class cannot be built, is not a source, has an invalid or foreign type | Left out of the registry, warned once a day per class and reason |
| Two classes with one type | Both left out, warned |
| `facts()`, `history()` or `describe()` throws | `Failed`, warned once a day per type and reason |
| Facts with the same person twice in one role, or not `ActivityFactDTO` | `Failed` |
| A history page longer than asked, or whose `nextCursor` is the cursor given | `Failed` |
| `describe()` returns subjects not asked for | Dropped; malformed entries dropped and warned |
| A link that is neither a site path nor https | Dropped, the title kept |
| An id that cannot be a subject's | `facts()` is read with no facts, without asking the source |

Warnings name the type, the module, the class and the reason, and an exception only by its class: a message may carry what a person can see. One source failing never affects another.

## Testing

Extend `Tests\Support\Activity\ActivitySourceTestCase` (or use the trait `ActivitySourceContractTests` with another base) and say how to build the source and a subject that counts:

```php
final class SessionActivitySourceTest extends ActivitySourceTestCase
{
    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(SessionActivitySource::class);
    }

    protected function activitySourceOwner(): string
    {
        return 'game-tables';
    }

    protected function createCountedActivitySubject(): string
    {
        return $this->completedTableWithPlayers()->id;
    }
}
```

It checks that the type starts with the module, the source is registered and read by the core, `facts()` of an unknown id is empty, the facts of a counted subject pass the core's rules, the history walks to the end without repeats and agrees with `facts()`, `describe()` answers only for what was asked, and the attributes are valid. Override `unknownActivitySubjectId()` when the source's ids are not UUIDs.

Test the privacy rule yourself, beyond the contract: a private table, a hidden category, an anonymous survey or a guest never appear in `facts()` or `history()`, and each change that makes something stop counting publishes it (`Event::fake([ActivityRecorded::class])`).
