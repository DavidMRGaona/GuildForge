# Media sources

The panel media library (**Contenido → Biblioteca de medios**) lists the images stored in the shared media storage and shows which records use them, so that files nobody uses can be found and deleted. This page explains how a module tells the library which of its records use stored files. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- Modules only register **media usage sources**. The core copies every use a source reports into an index of usages and works out the status of each file from it: in use, recent, unused or unknown origin.
- A file is **unused** only when no source uses it and it was uploaded more than 7 days ago (`MediaFileStatusRule::GRACE_DAYS`). Inside that margin it is **recent**.
- A file whose folder no active source claims has an **unknown origin** («Origen desconocido») and is never deleted.
- Deletion is off until the installation sets `MEDIA_LIBRARY_DELETE_ENABLED=true`, and it needs the permission `media.delete`, which is not granted to any role by default (the `media.view_any` permission, suggested for editors, only shows the library). Before each deletion the service checks the file again against the live sources, not the index, and keeps the file when any check fails.
- The core sources (`EventMediaSource`, `ArticleMediaSource`, `GalleryPhotoMediaSource`, `HeroSlideMediaSource`, `SiteSettingsMediaSource` in `app/Filament/Media/Sources/`) and `AvatarMediaSource` follow the rules below and are the best examples.
- `RichTextMediaSource` (type `rich_text`, «Textos», role «En el texto») reads the core rich texts: event descriptions, article contents, the body of the site's own guides, the legal texts and the «Nosotros» history. Every delivery URL of this site in them (in `src`, `href` or `srcset`, or as bare text, with or without transformation and version) counts as a use, whatever folder the file lives in. It claims no folder.

Requirement: set `requires.core` in `module.json` to the core release that introduces the media library, otherwise an older core does not know `registerMediaSources()` and the module's files would show as unknown origin (and stay undeletable).

## The contract

A source implements `App\Application\Media\Contracts\MediaUsageSourceInterface`:

| Method | Returns |
|--------|---------|
| `type()` | Stable identifier of the source, prefixed with the module (`<module>.<things>`, such as `gametables.tables`) and unique across every source. Stored with each usage: never rename it |
| `label()` | Plural, user-facing name of the origin («Partidas»), translated |
| `folders()` | The folders the source claims, each **with a trailing slash** (`gametables/tables/`) |
| `excludedSubfolders()` | Subfolders of the claimed folders the source does not claim, relative to each folder and with a trailing slash; `[]` when there are none |
| `hidden()` | `true` only for sources whose files must not be listed (avatars); their files can never be deleted |
| `usages()` | Every use of a file, as an `iterable<MediaUsageDTO>` |
| `usagesFor(string $recordId)` | The uses of one record, or `[]` when it does not exist |
| `usesFile(MediaFileId $file)` | Whether any record uses the file right now |
| `editUrl(string $recordId, object $user)` | Where this person can edit the record, or `null` |

`MediaUsageDTO` carries `storedPath` (the value as stored, in any form the file id resolver understands), `recordId`, `role` (what the file is for, translated, such as «Imagen») and `title` (the record's name).

### Rules

1. **Prefix the type with the module.** The registry keeps the first source registered for a type and skips (and logs) any later one; whichever registers first, a duplicate type is a broken source, and while one exists the library refuses to delete any file. A module type called `events` would collide with the core events.
2. **Claim your own folders, with a trailing slash.** `gametables/` matches `gametables/a.jpg` but not `gametables-old/a.jpg`. A file belongs to the source with the longest matching folder. A source with an empty folder claims nothing.
3. **Leave `content/` to the sources that read rich text.** Images uploaded from the editor into a record's text go to a `content/` subfolder of the claimed folder; `EloquentColumnMediaSource` excludes it (`['content/']`), so the source that reads the text can claim it without fighting the column source. Until one claims it, those files have an unknown origin and are never deleted.
4. **Read your rich text too.** A URL of this site pasted into a module's text protects the file only when a source of the module reads that text. Extract every delivery URL from the HTML and resolve it with `MediaFileIdResolverInterface::fromStoredPath()` (it returns `null` for other hosts and prefixes); in `usesFile()`, prefilter with `App\Infrastructure\Media\Sources\StoredMediaIdPrefilter::likeClause()` and confirm in PHP. `RichTextMediaSource` is the example.
5. **Count soft-deleted records.** A trashed record can be restored, and its image must still count as used. `EloquentColumnMediaSource` already queries with trashed records.
6. **`usesFile()` must be exact and live.** It answers from the module's own tables, not from the index. The deletion service asks every active source, even one that does not claim the file's folder, because a record may point at a file outside its own folders.
7. **`usages()` must chunk** (`lazyById()` or `chunkById()`, yielding one usage at a time): it runs on every sync.
8. **`editUrl()` is decided for the given user.** Return `null` when the person cannot edit the record (check the policy or the permission with `Gate::forUser($user)`), never a link they would be refused. The library shows the link only when it is not `null`.
9. **Ids that are not valid for the source return empty results.** With PostgreSQL a malformed uuid in a query throws, so check the shape first (`Str::isUuid()`).

## `EloquentColumnMediaSource`

For a model that keeps one stored image path in one column, extend `App\Infrastructure\Media\Sources\EloquentColumnMediaSource` and implement only what is specific:

```php
final class TableMediaSource extends EloquentColumnMediaSource
{
    public function type(): string { return 'gametables.tables'; }

    public function label(): string { return __('gametables::media.sources.tables'); }

    public function folders(): array { return ['gametables/tables/']; }

    protected function modelClass(): string { return TableModel::class; }

    protected function column(): string { return 'image_public_id'; }

    protected function role(): string { return __('gametables::media.roles.image'); }

    protected function titleOf(Model $record): string { return (string) $record->getAttribute('title'); }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return Gate::forUser($user)->allows('update', $record)
            ? TableResource::getUrl('edit', ['record' => $record])
            : null;
    }
}
```

The base class implements `usages()`, `usagesFor()`, `usesFile()` (a prefiltered query confirmed with the resolver), `editUrl()` and `hidden()` (`false`), and includes soft-deleted records. A source that keeps several files, a list of photos or paths inside rich text implements the interface directly.

## Registering

Return the classes from `registerMediaSources()` in the module's service provider:

```php
public function registerMediaSources(): array
{
    return [TableMediaSource::class];
}
```

The core registers them when the module loads, with the module's name; a disabled module does not register its sources.

## Keeping the index up to date

The index is rewritten only when something asks for it. Attach `App\Infrastructure\Media\Observers\RefreshMediaUsagesObserver` to the module's models as model event listeners, from the provider's `boot()`:

```php
$observer = new RefreshMediaUsagesObserver(
    'gametables.tables',
    static fn (Model $model): array => [(string) $model->getKey()],
);

TableModel::saved($observer->saved(...));
TableModel::deleted($observer->deleted(...));
```

The closure returns the ids, as the source knows them, of the records the saved model feeds (return `[]` to do nothing: models saved very often should refresh only when a relevant attribute changed). A refresh is queued after the transaction commits, reads the record again (a deleted record has no usages) and never fails the save: anything that goes wrong is logged. The nightly `media:sync` settles whatever the observers missed.

You can also call `MediaIndexerInterface::refresh($type, $recordId)` directly.

## Commands

| Command | What it does |
|---------|--------------|
| `media:sync` | Reconciles the index with the stored files and with the usages of every active source. Runs daily. A source that fails is reported and its rows are left as they were; the file listing adds or removes files only when it was read completely |
| `media:forget-source {type} [--force]` | Deletes the usage rows of a type that no active source owns |

## Disabling or uninstalling a module

The usage rows a source wrote stay in the index when its module is disabled or uninstalled. They are **kept on purpose**: the module's files then count as used by an inactive source and are refused for deletion («lo usaba un módulo que ahora no está activo»), because nobody can say whether the module will come back.

- Enabling the module again makes its source active, and its rows are rewritten on the next sync.
- When the module is gone for good, run `php artisan media:forget-source <type>` for each of its types. It refuses a type that still belongs to an active source. After that, its files count as unused (or as unknown origin if no source claims their folder).

## Failing closed

The library refuses to delete any file while a registered source cannot be built or reports a type already taken by another source (the duplicate is skipped and logged). It would not know whether that source uses the file. The problem is logged once per class; fix the source (or the duplicated type) and deletion works again. A source whose `hidden()` throws is treated as hidden.
