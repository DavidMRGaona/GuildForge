# Publication sources

A module that announces records outside the site (a forum discussion per game table, a post in a channel) has to know when a record is published, changes or is cancelled, and read what to show, without importing the classes of the module that owns it. The core offers a publication contract for that: owners register a source and dispatch change events; announcers listen and read the record again from the source. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces publications (the one with core#465). An older core does not know `registerPublicationSources()`: the records would never be offered.

## Pieces

| Piece | Where | What it does |
|---|---|---|
| `PublicationSourceInterface` | `App\Application\Publications\Contracts` | `type()` (stable kebab-case, e.g. `game-table`), `find($id)` (the record as it is now, or `null`) and `publishedIds()` (published records not over yet, for reconciliation) |
| `PublicationDTO` | `App\Application\Publications\DTOs` | `type`, `id`, `title`, `summary` (plain text), `url` (absolute http(s)), `state`, `startsAt`, `endsAt`, `topics`, `isFull`, `registrationOpen`, `registrationClosesAt` |
| `PublicationTopicDTO` | same | `key` (1 to 64 characters from `[a-z0-9._-]`, e.g. `game-system.<uuid>`) and `label` |
| `PublicationState` | `App\Application\Publications\Enums` | `Active`, `Cancelled`, `Ended` |
| `PublicationChanged`, `PublicationRemoved` | `App\Domain\Publications\Events` | `type` and `id`, nothing else |
| `PublicationSourceRegistryInterface` | `App\Application\Publications\Services` | `source($type)`, `sources()`: the first source registered for a type wins; one that cannot be built, is not a source or has an invalid type is skipped with a warning |

## Offering records

1. Implement the source. `find()` returns `null` when the record does not exist or is not published (draft, taken down); a cancelled or ended record that is still published comes back with its state, so whoever announced it can say so. Records that take no registrations leave `isFull` and `registrationOpen` false.
2. Return it from the provider:

```php
public function registerPublicationSources(): array
{
    return [GameTablePublicationSource::class];
}
```

3. Dispatch the events once the change commits (`DB::afterCommit()`, or an observer implementing `ShouldHandleEventsAfterCommit`):
   - `PublicationChanged($type, $id)` after any save that may change what `find()` returns: published, edited, rescheduled, filled up or freed a place, registrations closed, cancelled, ended, taken down. Dispatching it for a save that changed nothing is harmless.
   - `PublicationRemoved($type, $id)` when the record is deleted.

Time-based changes (registrations that close at a set time, an end that passes) dispatch nothing by themselves: `registrationClosesAt` and `endsAt` let a listener schedule its own check.

The core offers its events (type `event`): published or not, start and end, public page and the description as plain text. They are never cancelled and take no registrations here.

## Listening

Listen to `PublicationChanged` and `PublicationRemoved` with an ordinary listener and queue the work. Read the record again with `PublicationSourceRegistryInterface::source($type)?->find($id)` when the job runs, never from the event: events carry no data, so late or out-of-order deliveries never bring back an old state. An unknown type (its module disabled) means there is nothing to read; keep what you announced.

Nothing in the core listens to them: without an announcer they do nothing.

## Rosters

Some records have people in them: the players of a campaign and its directors. A module that gives them something elsewhere (a private group in a forum) needs to know who they are and when that changes. Requirement: the core release with core#467.

| Piece | Where | What it does |
|---|---|---|
| `PublicationRosterInterface` | `App\Application\Publications\Contracts` | Optional, implemented by a source next to `PublicationSourceInterface`: `roster($id)` and `rostersOf($userId)` |
| `PublicationRosterDTO` | `App\Application\Publications\DTOs` | `memberIds` (who takes part) and `leaderIds` (who leads, whether they take part or not): account UUIDs, lowercase, each once |
| `PublicationMembershipDTO` | same | `type`, `id` and `isLeader`: one record a user is in |
| `PublicationRosterChanged` | `App\Domain\Publications\Events` | `type`, `id` and `userIds` (the accounts whose place changed, never empty) |

Offering it:

- `roster($id)` answers for the same records as `find()`: `null` when the record does not exist or is not published.
- `rostersOf($userId)` lists the published records not over yet that the user takes part in or leads, so a listener can send a person's complete list (a shorter list reads as removals).
- Dispatch `PublicationRosterChanged($type, $id, $userIds)` once the change commits, when someone joins, leaves or is removed, or starts or stops leading. Ending, cancelling or taking down the record is a `PublicationChanged`: the roster of a record `find()` no longer returns is `null`.

Listening: check the capability with `instanceof` on the source from `PublicationSourceRegistryInterface::source($type)`. Read `roster()` and `rostersOf()` again when the queued job runs, never from the event. The registry does not change: a source without the capability simply has no roster.

## Discussion activity on pages

A page that shows a publication (an event, a game table, a campaign) may show the activity of its discussion elsewhere: messages, the last one, a button to comment, and topics such as its game system. Requirement: the core release with core#470.

- Ask `App\Application\Discussions\Contracts\SubjectDiscussionInterface::forSubject($type, $id, $userId)` (the publication's type and id, and the signed-in account or null) in the page's controller and pass `->toArray($timezone)` as the `discussion` prop. Catch any `Throwable`, report it and pass null: the page always shows, without the block when something fails.
- Render it with the shared component `@/components/discussions/SubjectDiscussion.vue` (`<SubjectDiscussion :discussion="discussion" />`, types `SubjectDiscussion`, `DiscussionActivity` and `TopicActivity` in `@/types/models`). With null it renders nothing.
- `SubjectDiscussionDTO` holds `discussion` (`DiscussionActivityDTO`: `url`, `actionUrl`, `commentCount`, `lastPostedAt`) and `topics` (`TopicActivityDTO`: `label`, `url`, `discussionCount`, `lastPostedAt`); links are absolute http(s) URLs. With nothing to show the answer is null, never an empty DTO.
- The core binds `NullSubjectDiscussion` (always null); a forum module replaces it and decides what each person may see. Every page view asks it, so it must be cheap: it reads what it keeps and never calls the forum while answering a page.

The core's event page does it already.
