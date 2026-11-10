<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\DTOs\EditorImageMoveReportDTO;
use App\Application\Content\Services\EditorImageMoverInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Knowledge\Enums\GuideSource;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Before core#481 the rich editors stored their images on the local "public" disk (Filament's
 * default). Each image found in the stored HTML is uploaded to the "images" disk, in the content
 * folder of its origin, and the HTML is pointed at it there.
 *
 * - Safe to run again: images already on Cloudinary are skipped, and a moved file is named after
 *   its content, so an upload repeated after an interrupted run overwrites the same asset.
 * - A local copy is deleted only once every value using it is saved; a failed upload or save
 *   leaves the value and its local copy as they were.
 * - Values are written straight to the table, as the Trix conversion does: moving an image is not
 *   an edit (no updated_at, no model events), and only if nobody saved the value meanwhile.
 */
final readonly class EditorImageMover implements EditorImageMoverInterface
{
    private const string LOCAL_DISK = 'public';

    private const string CLOUD_DISK = 'images';

    /** Name space of the moved files' UUIDs (version 5): same origin and content, same name */
    private const string NAMESPACE = '6f1d9a4e-3c2b-5e8f-9a7d-0b4c1e2f3a5d';

    /** Columns edited with the panel's rich editors */
    private const array COLUMNS = [
        ['table' => 'events', 'column' => 'description', 'origin' => EditorImageOrigin::Events],
        ['table' => 'articles', 'column' => 'content', 'origin' => EditorImageOrigin::Articles],
        ['table' => 'knowledge_guides', 'column' => 'body', 'origin' => EditorImageOrigin::Knowledge],
    ];

    /** Settings edited with the panel's rich editors */
    private const array SETTINGS = [
        'about_history' => EditorImageOrigin::About,
        'legal_privacy_content' => EditorImageOrigin::Legal,
        'legal_notice_content' => EditorImageOrigin::Legal,
        'legal_cookies_content' => EditorImageOrigin::Legal,
        'legal_terms_content' => EditorImageOrigin::Legal,
    ];

    public function __construct(
        private SettingsServiceInterface $settings,
        private EditorImageTags $tags,
        private MimeTypeDetector $mimeTypes = new FinfoMimeTypeDetector,
    ) {}

    public function move(bool $dryRun = false): EditorImageMoveReportDTO
    {
        $values = $this->values();
        $moved = $broken = $failed = [];

        /** @var list<array{value: array{label: string, table: string, column: string, id: string, html: string, origin: EditorImageOrigin}, images: list<array{tag: string, shown: string, path: string}>}> $plans */
        $plans = [];
        /** @var array<string, int> $references values using each local file */
        $references = [];

        foreach ($values as $value) {
            $images = [];

            foreach ($this->tags->read($value['html']) as $tag) {
                $path = $this->localPath($tag['src'], $tag['id']);

                if ($path === null) {
                    continue;
                }

                $shown = $tag['src'] ?? $tag['id'] ?? '';

                if (! $this->isMovable($path)) {
                    $broken[] = $value['label'].': '.$shown;

                    continue;
                }

                $images[] = ['tag' => $tag['tag'], 'shown' => $shown, 'path' => $path];
            }

            if ($images === []) {
                continue;
            }

            $plans[] = ['value' => $value, 'images' => $images];

            foreach (array_unique(array_column($images, 'path')) as $path) {
                $references[$path] = ($references[$path] ?? 0) + 1;
            }
        }

        /** @var array<string, string> $uploaded Cloudinary path of each origin and local file already uploaded */
        $uploaded = [];
        /** @var array<string, int> $saved values saved with each local file moved */
        $saved = [];

        foreach ($plans as ['value' => $value, 'images' => $images]) {
            $rewrites = $lines = [];
            $current = null;

            try {
                foreach ($images as $image) {
                    $current = $image['shown'];
                    $key = $value['origin']->value.'|'.$image['path'];
                    $target = $uploaded[$key] ?? $this->upload($image['path'], $value['origin'], $dryRun);
                    $uploaded[$key] = $target;
                    $rewrites[$image['tag']] = ['src' => $this->cloud()->url($target), 'id' => $target];
                    $lines[] = $value['label'].': '.$image['shown'].' → '.$target;
                }

                $current = null;

                if (! $dryRun) {
                    $this->write($value, $this->tags->rewrite($value['html'], $rewrites));
                }
            } catch (Throwable $e) {
                $failed[] = $value['label'].($current !== null ? ': '.$current : '').' ('.$e->getMessage().')';

                continue;
            }

            array_push($moved, ...$lines);

            foreach (array_unique(array_column($images, 'path')) as $path) {
                $saved[$path] = ($saved[$path] ?? 0) + 1;
            }
        }

        if (! $dryRun) {
            foreach ($saved as $path => $count) {
                if ($count === $references[$path] && ! $this->local()->delete($path)) {
                    $failed[] = $path.' (moved, but the local copy could not be deleted)';
                }
            }
        }

        return new EditorImageMoveReportDTO(count($values), $moved, $broken, $failed);
    }

    /**
     * @return list<array{label: string, table: string, column: string, id: string, html: string, origin: EditorImageOrigin}>
     */
    private function values(): array
    {
        $values = [];

        foreach (self::COLUMNS as ['table' => $table, 'column' => $column, 'origin' => $origin]) {
            $query = DB::table($table)->whereNotNull($column)->orderBy('id');

            // Official guides come from Markdown files, not from the editor
            if ($table === 'knowledge_guides') {
                $query->where('source', GuideSource::Site->value);
            }

            foreach ($query->get(['id', $column]) as $row) {
                $values[] = [
                    'label' => $table.'.'.$column.'#'.$row->id,
                    'table' => $table,
                    'column' => $column,
                    'id' => (string) $row->id,
                    'html' => (string) $row->{$column},
                    'origin' => $origin,
                ];
            }
        }

        foreach (self::SETTINGS as $key => $origin) {
            // From the table, not the settings cache, which may be stale
            $html = $this->setting($key);

            if ($html !== null) {
                $values[] = ['label' => 'settings#'.$key, 'table' => 'settings', 'column' => 'value', 'id' => $key, 'html' => $html, 'origin' => $origin];
            }
        }

        return $values;
    }

    /**
     * The path on the local disk an image points at, or null when it is not a local image. Images
     * already in a content folder are on Cloudinary; the src wins over data-id, which Trix never wrote.
     */
    private function localPath(?string $src, ?string $id): ?string
    {
        if ($id !== null && EditorImageOrigin::ofPath($id) instanceof EditorImageOrigin) {
            return null;
        }

        $fromSrc = $src !== null ? $this->pathOfLocalUrl($src) : null;

        if ($fromSrc !== null) {
            return $fromSrc;
        }

        if ($id === null || $id === '' || str_contains($id, '://')) {
            return null;
        }

        return $this->isMovable($id) ? $id : null;
    }

    /**
     * "/storage/<path>", relative or on the site's own host, as the local disk serves it.
     */
    private function pathOfLocalUrl(string $src): ?string
    {
        $base = (string) config('filesystems.disks.'.self::LOCAL_DISK.'.url');
        $prefix = rtrim((string) (parse_url($base, PHP_URL_PATH) ?? ''), '/').'/';
        $url = parse_url($src);

        if ($url === false || $prefix === '/' || isset($url['scheme']) && ! in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $hosts = array_filter([parse_url($base, PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        $host = $url['host'] ?? null;

        if ($host !== null && ! in_array(strtolower($host), array_map(strtolower(...), $hosts), true)) {
            return null;
        }

        $path = $url['path'] ?? '';

        return str_starts_with($path, $prefix) ? rawurldecode(substr($path, strlen($prefix))) : null;
    }

    /**
     * An image file inside the local disk: no parent segments, nothing absolute.
     */
    private function isMovable(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || in_array('..', explode('/', $path), true)) {
            return false;
        }

        try {
            // By extension, as Cloudinary picks the resource type
            return str_starts_with((string) $this->mimeTypes->detectMimeTypeFromPath($path), 'image/') && $this->local()->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function upload(string $path, EditorImageOrigin $origin, bool $dryRun): string
    {
        $contents = $this->local()->get($path) ?? throw new RuntimeException('Could not read the local copy');
        $month = CarbonImmutable::createFromTimestamp($this->local()->lastModified($path), (string) config('app.timezone'));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = Uuid::uuid5(self::NAMESPACE, $origin->value.':'.hash('sha256', $contents))->toString();
        $target = $origin->directory($month).'/'.$name.($extension !== '' ? '.'.$extension : '');

        if (! $dryRun && $this->cloud()->put($target, $contents) !== true) {
            throw new RuntimeException('Upload refused');
        }

        return $target;
    }

    /**
     * @param  array{table: string, column: string, id: string, html: string}  $value
     */
    private function write(array $value, string $html): void
    {
        if ($value['table'] === 'settings') {
            if ($this->setting($value['id']) !== $value['html']) {
                throw new RuntimeException('Changed while moving');
            }

            $this->settings->set($value['id'], $html);

            return;
        }

        $updated = DB::table($value['table'])
            ->where('id', $value['id'])
            ->where($value['column'], $value['html'])
            ->update([$value['column'] => $html]);

        if ($updated !== 1) {
            throw new RuntimeException('Changed while moving');
        }
    }

    private function setting(string $key): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return is_string($value) ? $value : null;
    }

    private function local(): Filesystem
    {
        return Storage::disk(self::LOCAL_DISK);
    }

    /**
     * @return Filesystem&Cloud
     */
    private function cloud(): mixed
    {
        return Storage::disk(self::CLOUD_DISK);
    }
}
