# Search sources

The site search (`/buscar` and the suggestions panel in the header) finds events, articles, galleries, help guides and whatever the enabled modules offer. This page explains how a module makes its records searchable. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- Modules only register **indexed sources**. The core copies each record a source offers into a search document (table of stored documents, mirrored to Meilisearch when it is configured) and searches those documents. There is no way for a module to answer a query live.
- The help guides are the only live source: the core itself filters them by what the signed-in user may see. A module never needs to care about them.
- The core sources (`EventSearchSource`, `ArticleSearchSource`, `GallerySearchSource` in `app/Infrastructure/Search/Sources/`) follow exactly the rules below and are the best examples.
- A search never answers 500 because of the engine or of one source: a failing source is logged and skipped, and Meilisearch falls back to the database engine.

Requirement: set `requires.core` in `module.json` to the core release that introduces search sources (3.36.0), for instance `^3.36`. An older core does not know `registerSearchSources()` and the module would silently not be searchable.

## The contract

A source implements `App\Application\Search\Contracts\IndexedSearchSourceInterface`:

| Method | Returns |
|--------|---------|
| `type()` | Stable identifier of the kind of record (`tables`), unique across every source. Stored with each document: never rename it |
| `label()` | Plural, user-facing name of the group («Partidas»), translated |
| `itemLabel()` | Singular name of one record («Partida»), translated |
| `pastLabel()` | Tag for records that are over («Pasada»), or `null` when the type has no such notion |
| `document(string $id)` | The `SearchDocumentDTO` of one record, or `null` when it must not be searchable |
| `documents()` | Every searchable record, as an `iterable<SearchDocumentDTO>` |

`SearchDocumentDTO` carries `id` (the record's key, a string), `title` and `url` (both required: an empty one throws), `body` (plain text), and optionally `keywords`, `meta` (a short line such as the venue), `imagePublicId`, `startsAt`, `endsAt`, `publishedAt`, `finished`, `audience` and `locale`.

- Dates are `DateTimeImmutable` instants. Results that have not ended yet rank ahead of finished ones (with Meilisearch, past results weigh half), so set `finished` for records that are over.
- Turn rich text into plain text before it goes into `body` (`App\Infrastructure\Search\Support\PlainText::from()` is available to code inside the core; a module strips tags itself).
- `audience` is `SearchAudience::Public` (default), `Authenticated` (any signed-in user) or `Members` (association members). The core filters by the visitor's audiences; a visitor never receives a document above their level.

## Visibility rules

1. **Copy the visibility rule of the public listing, not of the detail page.** A document exists only if the record shows up in the public listing for the audience you give it. A detail page that opens by direct link (an unlisted or finished record) is not a reason to index it.
2. **Cancelled and draft content returns `null` from `document()`** and is left out of `documents()`. A cancelled record must never be searchable, even for its owner.
3. **Ids that are not valid for the source return `null`.** `document()` receives whatever the indexer queued; with PostgreSQL a malformed uuid in a query throws, so check the shape first (the core sources use `Str::isUuid()`).
4. **`documents()` must chunk.** It runs on every full rebuild: use `lazyById()` or `chunkById()` and `yield` one document at a time, and eager-load what `toDocument()` reads to avoid N+1 queries.
5. Build the URL with `route()` so it is the one a visitor would use.

## Keeping the index up to date

A document is rewritten only when someone asks for it. Inject `App\Application\Search\Services\SearchIndexerInterface` (or `resolve()` it in an observer) and call:

- `refresh($type, $id)` after a record is created or updated;
- `forget($type, $id)` after it is deleted.

Both queue the work for when the current transaction commits and never throw, so they are safe inside a save. `refresh()` is also the right call when the record becomes hidden (unpublished, cancelled): the document is removed if `document()` returns `null`.

Use an Eloquent observer or call the indexer explicitly from your service. Also refresh after changes that do not touch the record's own row but feed its document: tag syncs, pivot changes and child rows (the core refreshes a gallery when one of its photos changes).

To rebuild by hand:

```bash
php artisan search:sync               # every type
php artisan search:sync --type=tables # one type
```

`search:sync` also runs when a module is enabled, disabled, updated or uninstalled, so a module's documents appear and disappear with it. The stored query counters are pruned daily by `search:prune-metrics`.

## Example

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\MyModule\Models\TableModel;

final class TableSearchSource implements IndexedSearchSourceInterface
{
    public function type(): string
    {
        return 'tables';
    }

    public function label(): string
    {
        return __('my-module::search.label');
    }

    public function itemLabel(): string
    {
        return __('my-module::search.item');
    }

    public function pastLabel(): ?string
    {
        return __('my-module::search.past');
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $table = $this->listed()->whereKey($id)->first();

        return $table instanceof TableModel ? $this->toDocument($table) : null;
    }

    public function documents(): iterable
    {
        foreach ($this->listed()->lazyById(200) as $table) {
            yield $this->toDocument($table);
        }
    }

    // The same conditions as the public listing; cancelled and draft tables are not in it
    private function listed(): Builder
    {
        return TableModel::query()->where('status', 'published');
    }

    private function toDocument(TableModel $table): SearchDocumentDTO
    {
        return new SearchDocumentDTO(
            id: (string) $table->getKey(),
            title: $table->title,
            body: strip_tags($table->description),
            url: route('my-module.tables.show', $table->slug),
            startsAt: $table->starts_at->toImmutable(),
            finished: $table->starts_at->isPast(),
        );
    }
}
```

Register it in the module's service provider; the core registers it when the module loads:

```php
public function registerSearchSources(): array
{
    return [TableSearchSource::class];
}
```

Keep it current from the provider's `boot()`:

```php
use App\Application\Search\Services\SearchIndexerInterface;

TableModel::saved(fn (TableModel $table) => resolve(SearchIndexerInterface::class)->refresh('tables', (string) $table->getKey()));
TableModel::deleted(fn (TableModel $table) => resolve(SearchIndexerInterface::class)->forget('tables', (string) $table->getKey()));
```
