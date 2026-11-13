# Editor images

The panel's rich editors upload the images inserted in a text to the shared media storage (Cloudinary), in a `content/` folder of their own, so the media library can tell which text uses each one. This page explains how a module gives its rich editors the same behaviour, how it lets the core move the images its editors stored on the local disk before, and how its media source reads the URLs in its texts. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: `requires.core` must allow the core release that brings this API (the one after 3.54.0, with core#496); with an older core the classes below do not exist.

## 1. The content folder

A module's editor images live in `<scope>/content/<year>/<month>/<uuid>.<ext>`, where the scope is the folder the module claims for its records (`announcements`, `game-tables`). `App\Domain\Content\ValueObjects\EditorImageFolder` holds it:

```php
use App\Domain\Content\ValueObjects\EditorImageFolder;

$folder = EditorImageFolder::of('announcements');

$folder->directory(now()); // "announcements/content/2026/11"
$folder->claim();          // "announcements/content/", for the media source's folders()
EditorImageFolder::ofPath('announcements/content/2026/11/a.png'); // the same folder; null for any other path
```

The scope is one lowercase segment (letters, digits and single hyphens); anything else throws `InvalidArgumentException`. The core's own folders are the cases of `EditorImageOrigin` (`EditorImageOrigin::Articles->folder()`).

## 2. The editor

Configure every `RichEditor` of the module with `App\Filament\Forms\RichEditor\EditorImages`:

```php
use App\Filament\Forms\RichEditor\EditorImages;

EditorImages::configure(
    RichEditor::make('content')->label(__('announcements::announcements.fields.content')),
    EditorImageFolder::of('announcements'),
)
```

It stores each image on the `images` disk in the folder of the current month with a UUID name, accepts JPEG, PNG, WebP and GIF (no SVG, which can carry scripts) up to 2048 KB, and builds the delivery URL locally, without asking Cloudinary whether the file exists. An image still on the local disk keeps the URL saved in the HTML. `App\Application\Content\Services\EditorImageUrlsInterface::urlFor()` gives the delivery URL of an image of any content folder (and `null` for any other path).

## 3. Moving the old images: editor text sources

Before core#481 every editor, of the core and of the modules, stored its images at the root of the local `public` disk. The same local file may be named by texts of different scopes: an image copied from an article to an announcement keeps its `/storage/…` URL. `php artisan content:move-editor-images-to-cloudinary` moves the images of every **editor text source** registered by the core and by the loaded modules, and deletes a local copy only once every text naming it, in every source, has been saved. A module therefore needs no move command of its own: it registers its texts.

For a text kept in one column of one table, extend `App\Infrastructure\Content\Sources\TableEditorTextSource`:

```php
use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Infrastructure\Content\Sources\TableEditorTextSource;

final class AnnouncementContentTextSource extends TableEditorTextSource
{
    public function type(): string { return 'announcements.content'; }

    public function folder(): EditorImageFolder { return EditorImageFolder::of('announcements'); }

    protected function table(): string { return 'announcements_announcements'; }

    protected function column(): string { return 'content'; }
}
```

The base class reads and writes with the query builder: soft-deleted rows count (they can be restored), and moving an image fires no model events and leaves `updated_at` alone. A row is written only if its text did not change meanwhile. Override `keyColumn()` when the key is not `id`, and `scope()` to leave rows out. Anything else implements `App\Application\Content\Contracts\EditorTextSourceInterface` directly (`type()`, `folder()`, `texts()`, `replace()`).

Return the classes from the provider:

```php
public function registerEditorTextSources(): array
{
    return [AnnouncementContentTextSource::class];
}
```

### Rules

1. **Prefix the type with the module** and never rename it: `--source=<type>` selects it. A duplicate type leaves the second source out.
2. **Register every text written with a rich editor**, even one whose images were never local: a text the command cannot read may name a local copy, and the command keeps every copy while one cannot be read.
3. **`texts()` throws when it cannot read.** It never returns fewer texts than there are.

### What the command does

- `--dry-run` lists what would move and what would be kept, without uploading, writing or deleting anything.
- `--source=<type>` moves only that source's texts; a local copy that a text of another source names is kept and listed («Kept on the local disk»).
- `--host=<host>` (repeatable) adds previous hosts of the site whose `/storage/` URLs are local images too.
- **It fails closed.** No local copy is deleted in a run while a source cannot be built or read, or while a module is installed but not loaded (disabled, or enabled but incompatible), because their texts cannot be read. The images still move and the texts are still rewritten; the report says why the copies stayed. Enable the module (or fix the source) and run the command again to delete them.
- It writes the texts straight to the table, so the media library learns about the new URLs on its next sync (`media:sync`, daily), unless the module refreshes them itself.

## 4. Reading the URLs in a text

A module's media source that reads rich text (rule 4 of [Media sources](./media-sources.md#rules)) asks `App\Application\Media\Services\MediaUrlExtractorInterface::fileIdsIn($text)` for the files of this site the text names, by file id. It finds every delivery URL in an attribute (`src`, `href`, `srcset`) or as bare text, with or without transformation and version, in HTML or JSON-escaped HTML. It throws a `RuntimeException` when the text cannot be read, so a deletion is refused instead of the file looking unused.

```php
public function usesFile(MediaFileId $file): bool
{
    $query = AnnouncementModel::withTrashed()->select(['content'])->whereNotNull('content');
    $like = StoredMediaIdPrefilter::likeClause($query->getQuery()->getGrammar(), 'content', $file);

    if ($like !== null) {
        $query->whereRaw($like[0], $like[1]);
    }

    foreach ($query->lazy(200) as $record) {
        if (isset($this->urls->fileIdsIn($record->getAttribute('content'))[$file->value])) {
            return true;
        }
    }

    return false;
}
```

The source claims the content folder (`folders()` returns `[$folder->claim()]`), as described in [Media sources](./media-sources.md).

## 5. The editor on the public site (core#573)

The panel's `RichEditor` is not available on the public site. The core has its own Vue component, `RichTextEditor` (`resources/js/components/form/RichTextEditor.vue`, TipTap), with the toolset of the panel's article editor in the same groups (core#627): bold, italic, underline, strike, subscript, superscript, link · headings 2 and 3 · align to start, centre, end · quote, code block, bulleted and numbered lists · table, image · undo, redo. Inside a table a second bar adds and removes rows and columns, toggles the header row, merges and splits cells and deletes the table; Tab moves between cells and leaves the editor after the last one. It is usable with keyboard and screen reader, and writes the HTML the panel editor stores, so a text written there opens and saves in the panel without loss: alignment as `style="text-align: center|end"` on paragraphs and headings (the start writes nothing), `<u>`, `<s>`, `<sub>`, `<sup>`, `<pre><code>` and `<table><tbody>` with `<th>` cells for the header row.

```vue
<RichTextEditor v-model="form.content" label="Contenido" upload-url="/editor/imagenes/articulos" />
```

- **Images**: `POST /editor/imagenes/articulos` (route `editor-images.articles.store`, `auth`, its own limiter `editor-image-upload`: 20 a minute and 200 an hour per account) stores the file in the articles' editor folder (`articles/content/<Y>/<m>/`, so the editor text and media sources claim it) and answers `{ id, url }`. The path goes in the image's `data-id` next to `src`, like the panel. Types (JPG, PNG, WebP, GIF; no SVG) and size (2 MB) are checked by the server on the real content type. Without `upload-url` the editor offers no images.
- **Who may upload**: `EditorImageUploadAuthorizerInterface`. The default (`PanelEditorImageUploadAuthorizer`) allows whoever may create articles in the panel; the feature that opens the editor to more people binds its own rule. It fails closed.
- **Sanitising**: never store the HTML as it arrives. `RichTextSanitizerInterface::sanitize($html, EditorImageOrigin::Articles)` runs the core's `richtext` HTMLPurifier profile (through `SanitizesHtml`, which also accepts the logical alignments `start` and `end` the panel writes, as an exact list), removes the empty paragraphs the editor leaves at the end, and keeps only the origin's own editor images (their delivery URL together with a `data-id` inside the origin's folder); any other image is removed.
