# Linkable records

A module that links its own data to records of other parts of the site (an organization that sponsored a tournament, a venue lent for an event) has to find those records and show them again later, without importing the classes of whoever owns them. The site search does not do: it only holds public, indexed records, and links must also reach drafts and records that only the panel shows. The core offers a linkable record contract for that: owners register a source, consumers ask the core's lookup. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces linkable records (the one with core#594). An older core does not know `registerLinkableRecordSources()`: the records would never be offered, and a consumer would find no lookup to ask.

## Pieces

| Piece | Where | What it does |
|---|---|---|
| `LinkableRecordSourceInterface` | `App\Application\Linking\Contracts` | `type()` (stable kebab-case, e.g. `tournament`), `label()` (translated, e.g. «Torneo»), `search($query, $viewerId, $limit)` and `find($id, $viewerId)` |
| `LinkableRecordDTO` | `App\Application\Linking\DTOs` | `type`, `id` (1 to 100 characters), `title`, `date` (optional), `dateIsDay` (false unless `date` is a calendar day) and `panelUrl` (optional, absolute http(s)) |
| `LinkableRecordLookupInterface` | `App\Application\Linking\Services` | What consumers use: `find($type, $id, $viewerId)`, `search($type, $query, $viewerId, $limit = 20)` (at most 50) and `types()` (label per type) |
| `LinkableRecordSourceRegistryInterface` | same | `register`, `registerMany`, `all`, `sources()` keyed by type, `source($type)`, `unregisterModule()`, `clear()`: the first source registered for a type wins; one that cannot be built, is not a source or has an invalid type is skipped with a warning |

Viewer ids are account UUIDs. An id with no account sees what a visitor would.

## Offering records

1. Implement the source:
   - `type()` never changes once released: consumers store it next to the id.
   - `search()` is for a picker in the panel: return only what the viewer may see, at most `$limit`, the most relevant first. An empty query lists the latest records. Every DTO carries your `type()`.
   - `find()` returns `null` both when the record does not exist and when the viewer may not see it, so nobody learns that a record exists without being allowed to see it. The DTO's `id` is the one asked for.
   - Decide visibility with the same rule as your panel and pages (your policy, the access service you already use), never a looser one. Drafts and panel-only records are fine as long as the viewer could open them.
   - `panelUrl` only when the viewer may open that page in the panel (panel access and your policy's `update` or `view`); otherwise `null`.
   - `date` is what consumers sort by and show: an instant (shown in the display zone) or, with `dateIsDay: true`, a calendar day that is never converted between zones.
   - A source that builds panel URLs from a Filament resource lives next to the panel code (the core's is `App\Filament\Linking\Sources\EventLinkableRecordSource`).
2. Return it from the provider:

```php
public function registerLinkableRecordSources(): array
{
    return [TournamentLinkableRecordSource::class];
}
```

The registry only knows sources of enabled modules: a disabled or uninstalled module registers nothing, so its types have no source.

The core offers its events (type `event`, label «Evento»): published events for every viewer; drafts (unpublished events, proposals in review or rejected) for whoever holds `events.view_any`; proposals never submitted from the web stay hidden, as in the panel. `date` is the start, and `panelUrl` the edit page for whoever has panel access and may edit the event.

## Consuming records

Store only the type and the id of what you link, never the title or the date: they are read again, for each viewer, every time they are shown. Ask through `LinkableRecordLookupInterface`, never through the sources directly:

```php
$record = $this->linkableRecords->find($link->type, $link->recordId, $viewer->id);

if ($record === null) {
    // Not shown: missing, not visible to this viewer, or its source is gone or failed
}
```

**It fails closed.** A type without a source (its module disabled or uninstalled, a source that cannot be built) and a source that throws, or answers with a record of another type or id, are treated alike: `find()` returns `null`, `search()` returns `[]` and nothing throws. Only the second is an error, logged by the lookup; the first is ordinary and logs nothing. Show such links the same way, whatever the reason (for example as a neutral count of links not available), and never with a title kept from earlier: that title would outlive the viewer's right to see the record.

`types()` lists the types that can be linked right now with their labels, for a type picker; a source whose label cannot be read is left out (and logged).

A lookup per link runs one query per record: fine for a record's page, not for a list of hundreds of rows. Nothing in the core consumes linkable records: without a consumer they do nothing.
