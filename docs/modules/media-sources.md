# Media sources

The panel media library (**Contenido → Biblioteca de medios**) lists the images stored in the shared media storage and shows which records use them, so that files nobody uses can be found and deleted. This page explains how a module tells the library which of its records use stored files. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- Modules only register **media usage sources**. The core copies every use a source reports into an index of usages and works out the status of each file from it: in use, recent, unused or unknown origin.
- A file is **unused** only when no source uses it and it was uploaded more than 7 days ago (`MediaFileStatusRule::GRACE_DAYS`). Inside that margin it is **recent**.
- A file whose folder no active source claims has an **unknown origin** («Origen desconocido») and is never deleted.
- Images are **hidden by default** (core ≥ the release with #837): the library shows a file's thumbnail only when every source that claims its folder implements `ShowsMediaPreviews` and the index is up to date with every source that claims or uses it ([Showing previews](#showing-previews)). Files of unknown origin and of sources without it are listed with a placeholder.
- Deletion is off until the installation sets `MEDIA_LIBRARY_DELETE_ENABLED=true`, and it needs the permission `media.delete`, which is not granted to any role by default (the `media.view_any` permission, suggested for editors, only shows the library). Before each deletion the service checks the file again against the live sources, not the index, and keeps the file when any check fails.
- The core sources (`EventMediaSource`, `ArticleMediaSource`, `GalleryPhotoMediaSource`, `HeroSlideMediaSource`, `SiteSettingsMediaSource` in `app/Filament/Media/Sources/`), `AvatarMediaSource` and `ProfileBannerMediaSource` follow the rules below and are the best examples.
- `ProfileBannerMediaSource` (type `core.profile-banner`, «Banners de perfil», role «Banner», core ≥ the release with #743) claims `profiles/banners/`, where the owners of public profiles upload their banners (`profiles/banners/<Y>/<m>/<uuid>.<ext>`), and counts each profile's banner as used. Usages are titled with the public name (never the account name) or the profile's address, and have no edit link: only the owner changes a banner, from the profile.
- `RichTextMediaSource` (type `rich_text`, «Textos», role «En el texto») reads the core rich texts: event descriptions, article contents, the body of the site's own guides, the legal texts and the «Nosotros» history. Every delivery URL of this site in them (in `src`, `href` or `srcset`, or as bare text, with or without transformation and version) counts as a use, whatever folder the file lives in. It claims no folder.

Requirement: set `requires.core` in `module.json` to the core release that introduces the media library (3.53.0), for instance `^3.53`; otherwise an older core does not know `registerMediaSources()` and the module's files would show as unknown origin (and stay undeletable).

## The contract

A source implements `App\Application\Media\Contracts\MediaUsageSourceInterface`:

| Method | Returns |
|--------|---------|
| `type()` | Stable identifier of the source, prefixed with the module (`<module>.<things>`, such as `gametables.tables`) and unique across every source. Stored with each usage: never rename it |
| `label()` | Plural, user-facing name of the origin («Partidas»), translated |
| `folders()` | The folders the source claims, each **with a trailing slash** (`game-tables/`; a campaigns source of the same module claims `campaigns/`) |
| `excludedSubfolders()` | Subfolders of the claimed folders the source does not claim, relative to each folder and with a trailing slash; `[]` when there are none |
| `hidden()` | `true` only for sources whose files must not be listed (avatars); their files can never be deleted |
| `usages()` | Every use of a file, as an `iterable<MediaUsageDTO>` |
| `usagesFor(string $recordId)` | The uses of one record, or `[]` when it does not exist |
| `usesFile(MediaFileId $file)` | Whether any record uses the file right now |
| `editUrl(string $recordId, object $user)` | Where this person can edit the record, or `null` |

`MediaUsageDTO` carries `storedPath` (the value as stored, in any form the file id resolver understands), `recordId`, `role` (what the file is for, translated, such as «Imagen»), `title` (the record's name) and, last and optional, `hidden` (`false` by default, core ≥ the release with #830).

### Showing previews

The library hides every image unless whoever knows where it comes from says it may be seen (core ≥ the release with #837). A file shows its thumbnail, its preview, «Abrir original» and «Copiar URL», and can be chosen in «Elegir de la biblioteca», only when all of these hold:

1. a visible, active source wins its folder (the longest matching one, as for the status);
2. **every** visible, active source that claims the file implements the marker interface `App\Application\Media\Contracts\ShowsMediaPreviews` (no methods), not only the one that wins: two sources claiming the same folder, or a parent folder and a nested one that does not exclude it, must both declare it;
3. none of its usages is hidden ([Hiding one usage](#hiding-one-usage));
4. the index is up to date with every source that claims the file **and** every source that has a usage of it (one without folders, such as `RichTextMediaSource`, included): its last sync did not fail, and it ran with the source as it is now. «As it is now» is a fingerprint of the class, the marker and the version of its code: the module's installed version, or the core's (`VERSION`) for core sources. A usage whose type has no active source now (a disabled module) is never up to date.

Anything else is **concealed**, with the same placeholder as a hidden usage (below): files of unknown origin, files a source without the marker claims, files of a source whose `hidden()` throws, and files with a claiming source or a usage whose source is not up to date. Whether a source shows its previews is decided per claiming source, not per usage, so the unused files of a shown folder keep their thumbnail: they are what gets reviewed before deleting (unless a claiming source conceals them: [Concealing unused files](#concealing-unused-files)). It is worked out when the page is read, from the sources active now and the outcome of each one's last sync (`MediaSourceSyncState`, setting `media_library_source_syncs`): a new release of a source, with the same class or another, conceals its files until its rows have been written by that release.

**A new release takes effect only after the next sync.** Rows indexed by an older release carry its `hidden` flags, so until the source is synced again its images stay concealed: right after declaring the marker, or after any update. A sync during which the module's version changes does not count. Enabling or updating a module queues a sync (`SyncMediaLibraryOnModuleChange`); if one is already running the new one is dropped, so the files stay concealed until the next (by hand with «Actualizar», or the daily one). After deploying a new core version, run `php artisan media:sync` ([Deployment guide](../deployment-guide.md)). Declare the marker and hide the private usages in the same release: the first sync with the marker must already hide them.

**To show your module's thumbnails again**, once every image of its folders may be seen by whoever opens the library, declare the marker on each source that claims them:

```php
final class TableMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface, ShowsMediaPreviews
```

Do not declare it on a source that also stores private images (hidden or pending posts, private categories, hidden profiles, drafts only their author sees) unless those usages are hidden; mask their titles too when the title gives the record away. Until a module declares it, its images show the placeholder and are not offered for reuse; nothing else changes. Every core source with folders declares it, and hides what only some people may see:

- `EventMediaSource`, `ArticleMediaSource` and the event and article texts of `RichTextMediaSource` hide the usages of a proposal draft never submitted (`proposal_status` draft and no `submitted_at`, `HasProposalReview::isUnsubmittedProposal()`), titled «Propuesta sin enviar» and without an edit link: until then it is its author's alone (`HidesUnsubmittedProposals`). Submitting or withdrawing saves the model, so its usages are refreshed;
- `ProfileBannerMediaSource` hides the banner of a profile not every member may see (visibility `association` or `hidden`, no public name, deactivated account).

`RichTextMediaSource` declares the marker too, but it claims no folder, so it decides nothing but its own hidden usages.

### Concealing unused files

Hiding usages covers a file while a private record uses it. Once its last usage is gone, the file is unused, and unused files of a shown folder keep their thumbnail. For some sources an unused file may still be private (core ≥ the release with #843). The forum is the example:

- the images of a thread deleted from a members-only category, or from a campaign's cast, lose their usages as soon as it is deleted;
- so do those of a message deleted and purged after 30 days;
- images uploaded while writing a message in a private category that is never sent never had a usage;
- the replaced portrait of a non-player character with a spoiler, or that of a deleted character, is left behind.

Such a source also implements the marker interface `App\Application\Media\Contracts\ConcealsUnusedMediaFiles` (no methods), beside `ShowsMediaPreviews`:

```php
final class PostMediaSource implements MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface, ShowsMediaPreviews, ConcealsUnusedMediaFiles
```

When **any** visible, active source claiming a file implements it (winning the folder or not, as for `ShowsMediaPreviews`), a file of that folder **without any usage** in the index is concealed (the placeholder, «Imagen oculta») and never offered in «Elegir de la biblioteca». So a parent folder whose source declares it also conceals the unused files of the nested folders other modules claim inside it, unless it excludes them (`excludedSubfolders()`). Files with usages follow [Showing previews](#showing-previews) and [Hiding one usage](#hiding-one-usage) as before. Nothing else changes: an unused file is still listed, counted, selectable and deleted once the 7-day grace period is over. It is worked out when the page is read (`EloquentMediaLibraryQuery::conceals()` and `applyReusable()`), so it needs no sync of its own.

Do not declare it on sources whose unused files are reviewed before deleting and may be seen (events, galleries, hero slides): the library would stop showing what it is there to show. No core source declares it.

### Hiding one usage

`hidden: true` keeps the image of one usage out of sight while its file stays in an ordinary folder: the badge of a secret achievement, say. Hiding a whole source (`hidden()`) is not the same: its files are never listed and never deleted.

```php
yield new MediaUsageDTO($record->badge, (string) $record->id, __('achievements::media.badge'), $title, hidden: $record->is_secret);
```

A source built on `EloquentColumnMediaSource` overrides `isHidden(Model $record)` instead ([below](#eloquentcolumnmediasource)).

When any usage of a file is hidden (stored in `media_usages.is_hidden`), or the file is concealed for any other reason ([Showing previews](#showing-previews)), the media library:

- draws a placeholder instead of the thumbnail in the grid, the list, «Ver detalles» and the details panel, does not offer «Abrir original» or «Copiar URL», and titles the details «Imagen oculta» instead of the file name;
- keeps listing the file, its size, its folder and every usage with the title the source gives: mask the title yourself when it gives the secret away;
- never names it by its id: the details heading, the delete dialog and the deletion notice call it «Imagen oculta»;
- never offers the file in «Elegir de la biblioteca»; a file chosen there and concealed before the form is saved is refused with «Esta imagen ya no se puede elegir».

A file shared by a shown usage and a hidden one is hidden for both. If a source gives the same usage (file, record and role) twice, it is hidden when any copy is.

This keeps the team that manages the library from seeing a secret by accident; it is not access control. The library is only for `media.view_any`, the file is still public in Cloudinary, and its id can still be read from the page (the delete dialog, for instance), so anyone determined could build its address. Never rely on it for an image that must not be public.

Deletion does not change: the file is in use while any usage exists (hidden or not), and once the last one is gone it is deleted like any other. The flag only follows the source on the next sync or refresh of the record, so publish the change (`MediaIndexerInterface::refresh()`, or let the observer do it) when a record stops being secret.

### Rules

1. **Prefix the type with the module.** The registry keeps the first source registered for a type and skips (and logs) any later one; whichever registers first, a duplicate type is a broken source, and while one exists the library refuses to delete any file. A module type called `events` would collide with the core events.
2. **Claim your own folders, with a trailing slash.** `game-tables/` matches `game-tables/2026/11/a.jpg` but not `game-tables-old/a.jpg`. A file belongs to the source with the longest matching folder. A source with an empty folder claims nothing.
3. **Leave `content/` to the sources that read rich text.** Images uploaded from the editor into a record's text go to a `content/` subfolder of the claimed folder; `EloquentColumnMediaSource` excludes it (`['content/']`), so the source that reads the text can claim it without fighting the column source. Until one claims it, those files have an unknown origin and are never deleted (the editor and the folder: [Editor images](./editor-images.md)).
4. **Read your rich text too.** A URL of this site pasted into a module's text protects the file only when a source of the module reads that text. Ask `App\Application\Media\Services\MediaUrlExtractorInterface::fileIdsIn()` for the files a text names (it finds every delivery URL of this site and throws when the text cannot be read; [Editor images](./editor-images.md#4-reading-the-urls-in-a-text)); in `usesFile()`, prefilter with `App\Infrastructure\Media\Sources\StoredMediaIdPrefilter::likeClause()` and confirm in PHP. `RichTextMediaSource` is the example.
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

    public function label(): string { return __('game-tables::media.sources.tables'); }

    public function folders(): array { return ['game-tables/']; }

    protected function modelClass(): string { return TableModel::class; }

    protected function column(): string { return 'image_public_id'; }

    protected function role(): string { return __('game-tables::media.roles.image'); }

    protected function titleOf(Model $record): string { return (string) $record->getAttribute('title'); }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        return Gate::forUser($user)->allows('update', $record)
            ? TableResource::getUrl('edit', ['record' => $record])
            : null;
    }
}
```

The base class implements `usages()`, `usagesFor()`, `usesFile()` (a prefiltered query confirmed with the resolver), `editUrl()` and `hidden()` (`false`), and includes soft-deleted records. To keep the image of some records out of sight ([Hiding one usage](#hiding-one-usage)), override `isHidden()` (`false` by default):

```php
protected function isHidden(Model $record): bool { return (bool) $record->getAttribute('is_secret'); }
```
 A source that keeps several files, a list of photos or paths inside rich text implements the interface directly.

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

A refresh that is lost leaves its type out of date (core#844): when the refresh cannot be queued, the source throws while reading the record, or the source cannot be built in the process that runs it, the type is recorded as failed, as a failed sync would leave it. Until then the library shows the files it claims or uses as «Imagen oculta» and never offers them to reuse, since the row may still say what the source no longer does (a usage that became hidden). A refresh that works afterwards does not bring the type back, because it knows nothing about the other records; only the next sync that reads the source completely does, and only if no refresh of the type failed while it ran. A refresh whose rows were written but whose files could not be looked up in the catalog is not a failure: the files are settled by the next sync and the type stays up to date. Other types are never affected.

## Commands

| Command | What it does |
|---------|--------------|
| `media:sync` | Reconciles the index with the stored files and with the usages of every active source. Runs daily, and is also queued whenever a module is enabled, disabled, updated or uninstalled, so a module's files are not shown as unused until the night. A source that fails is reported and its rows are left as they were; the file listing adds or removes files only when it was read completely. Only a complete run updates «Última reconciliación»; any other is stored as the last failed one, and the page warns while it is the newest |
| `media:forget-source {type} [--force]` | Deletes the usage rows of a type that no active source owns and whose module is no longer installed |

## Disabling or uninstalling a module

The usage rows a source wrote stay in the index when its module is disabled or uninstalled. They are **kept on purpose**: the module's files then count as used by an inactive source and are refused for deletion («lo usaba un módulo que ahora no está activo»), because nobody can say whether the module will come back.

- Enabling the module again makes its source active, and its rows are rewritten on the next sync.
- When the module is gone for good, run `php artisan media:forget-source <type>` for each of its types. It refuses a type that still belongs to an active source, and a type whose rows belong to a module that is still installed (disabled modules included): uninstall the module first. After that, its files count as unused (or as unknown origin if no source claims their folder).

## Failing closed

The library refuses to delete any file while a registered source cannot be built or reports a type already taken by another source (the duplicate is skipped and logged). It would not know whether that source uses the file. The problem is logged once per class; fix the source (or the duplicated type) and deletion works again. A source whose `hidden()` throws is treated as hidden.

## Choosing a file from the library in module fields

A panel image field can offer «Elegir de la biblioteca»: the person picks a file already in the library, and the record points to the same file instead of uploading it again. A chosen file is therefore **shared** between records, and no code may delete it by hand any more.

Requirement: `requires.core` must allow the core release that brings the picker (the one after 3.53.0, with core#480); with an older core the classes below do not exist.

### 1. Release files through the core, never delete them

When a record lets go of a file (it is replaced, cleared, or the record is deleted), hand it to the core instead of deleting it:

- Models that use `App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages` already do: since core#480 the trait releases the old file after the save commits.
- Anything else calls `App\Application\Media\Services\MediaFileReleaserInterface::release($storedPath)` after it has saved the new value. A queued check deletes the file only if no active source uses it right now (the same live checks as the library, without the 7-day grace period or the deletion switch) and records it in the deletions log as automatic. Any doubt keeps the file, which then shows as unused.

A release waits for the open transaction to commit; with none open it runs at once. A page whose save writes several records with image fields (a repeater of photos, for instance) must run in one transaction, or a record written later in the same save that chose the released file would find it gone. On a Filament page: `protected ?bool $hasDatabaseTransactions = true;`, as the core resource pages with image fields do.

Never call `Storage::disk('images')->delete()` on a file of a claimed folder.

### 2. Mark the source

Once no code of the module deletes files of its folders by hand, the source implements the marker interface `App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface` (no methods):

```php
final class TableMediaSource extends EloquentColumnMediaSource implements ReleasesMediaThroughCoreInterface
```

Only files whose folder is claimed by an active, visible source with the marker, and that the library shows (`ShowsMediaPreviews`, no hidden usage: [Showing previews](#showing-previews)), are offered by the picker or accepted when a form is saved. The action itself only needs the marker on the field's own folder. Files of an unmarked source, of a disabled module or of unknown origin are never offered, so a record of another module cannot end up sharing a file this module would delete. Do not add the marker before step 1 is done everywhere in the module.

### 3. Attach the picker to the field

```php
use App\Filament\Forms\Components\MediaLibraryPicker;

MediaLibraryPicker::attachTo(
    FileUpload::make('image_public_id')
        ->image()
        ->disk('images')
        ->directory(fn (): string => 'game-tables/'.now()->format('Y/m'))
        ->maxSize(2048),
)
```

`attachTo()` adds the hint action `chooseFromLibrary` (`MediaLibraryPicker::ACTION`) and Filament's `preventFilePathTampering()`. The action shows only to people with `media.view_any`, and only when a marked source claims the field's `directory()`. Saving accepts the record's own path, or a reusable file of the library that the field accepts. Any other path, a forged one included, fails validation. A chosen file that was deleted meanwhile fails with «La imagen elegida ya no está en la biblioteca».

- The picker offers only files that match the field's `acceptedFileTypes()` (any image with just `->image()`) and `maxSize()`. A field's own upload rules never reach a chosen file, so add anything else as `constrain`, a closure over the `media_files` query used both to list and to validate:

  ```php
  MediaLibraryPicker::attachTo($field, constrain: fn (Builder $query): Builder => $query->where('media_files.width', '>=', 1200));
  ```

  A field that only accepts files the module itself produced (sanitized SVGs, for instance) can restrict the picker to its own folder this way, or simply not attach it.
- On a page without a record (a settings page), pass `storedPaths: fn (): array => [...]` with the values the fields hold now. Filament only accepts the record's original paths on its own, so without them a stored file that is not in the index yet would fail validation.
- Never attach it to fields of hidden sources (avatars).
