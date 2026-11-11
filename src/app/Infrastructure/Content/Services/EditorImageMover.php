<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Contracts\EditorTextSourceInterface;
use App\Application\Content\DTOs\EditorImageMoveReportDTO;
use App\Application\Content\DTOs\EditorTextDTO;
use App\Application\Content\Services\EditorImageMoverInterface;
use App\Application\Content\Services\EditorTextSourceRegistryInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\MimeTypeDetector;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Before core#481 the rich editors, of the core and of the modules, stored their images at the root of
 * the local "public" disk (Filament's default). Each image found in the texts of the registered editor
 * text sources is uploaded to the "images" disk, in the content folder of its source, and the text is
 * pointed at it there (core#496).
 *
 * - Safe to run again: images already in a content folder are skipped, and a moved file is named after
 *   its folder and content, so an upload repeated after an interrupted run overwrites the same asset.
 * - One local copy may be named by texts of several sources (an image copied from an article to an
 *   announcement keeps its URL). It is deleted only once every text naming it, in every source, is
 *   saved. A failed upload or save leaves the text and its local copy as they were.
 * - Fails closed: while some texts cannot be read (a source that cannot be built or read, a module
 *   installed but not loaded), no local copy is deleted at all.
 * - Texts are written by their source, straight to the table: moving an image is not an edit (no
 *   updated_at, no model events), and only if nobody saved the text meanwhile.
 */
final readonly class EditorImageMover implements EditorImageMoverInterface
{
    private const string LOCAL_DISK = 'public';

    private const string CLOUD_DISK = 'images';

    /** Name space of the moved files' UUIDs (version 5): same folder and content, same name */
    private const string NAMESPACE = '6f1d9a4e-3c2b-5e8f-9a7d-0b4c1e2f3a5d';

    public function __construct(
        private EditorTextSourceRegistryInterface $sources,
        private ModuleRepositoryInterface $modules,
        private EnabledModulesResolverInterface $loadedModules,
        private EditorImageTags $tags,
        private MimeTypeDetector $mimeTypes = new FinfoMimeTypeDetector,
    ) {}

    public function move(bool $dryRun = false, array $hosts = [], ?string $source = null): EditorImageMoveReportDTO
    {
        if ($source !== null && ! $this->sources->source($source) instanceof EditorTextSourceInterface) {
            throw new InvalidArgumentException('Unknown editor text source: '.$source);
        }

        $hosts = $this->siteHosts($hosts);
        $moved = $broken = $failed = $otherHosts = $kept = [];
        $held = $this->unreadableModules();

        foreach ($this->sources->unresolved() as $class) {
            $held[] = 'The editor text source '.$class.' could not be loaded';
        }

        /** @var list<array{source: EditorTextSourceInterface, text: EditorTextDTO, images: list<array{tag: string, shown: string, path: string}>}> $plans */
        $plans = [];
        /** @var array<string, list<string>> $namedBy labels of the texts naming each local file, in every source */
        $namedBy = [];
        $checked = 0;

        foreach ($this->sources->sources() as $textSource) {
            $selected = $source === null || $textSource->type() === $source;

            try {
                $texts = $this->read($textSource);
            } catch (Throwable $e) {
                $held[] = 'The texts of '.$textSource->type().' could not be read';

                if ($selected) {
                    $failed[] = $textSource->type().' (could not be read: '.$e->getMessage().')';
                }

                continue;
            }

            foreach ($texts as $text) {
                $images = $named = [];

                foreach ($this->tags->read($text->html) as $tag) {
                    $shown = $tag['src'] ?? $tag['id'] ?? '';
                    $foreign = $tag['src'] !== null ? $this->storageUrlOnOtherHost($tag['src'], $hosts) : null;

                    if ($foreign !== null) {
                        $here = $this->isMovable($foreign['path']);

                        // Maybe a previous domain of the site: its copy is not deleted under it
                        if ($here) {
                            $named[] = $foreign['path'];
                        }

                        if ($selected) {
                            $otherHosts[] = $text->label.': '.$shown.($here
                                ? ' (file found here: pass --host='.$foreign['host'].')'
                                : ' (no such file here)');
                        }

                        continue;
                    }

                    $path = $this->localPath($tag['src'], $tag['id'], $hosts);

                    if ($path === null) {
                        continue;
                    }

                    if (! $this->isMovable($path)) {
                        if ($selected) {
                            $broken[] = $text->label.': '.$shown;
                        }

                        continue;
                    }

                    $images[] = ['tag' => $tag['tag'], 'shown' => $shown, 'path' => $path];
                }

                foreach (array_unique([...array_column($images, 'path'), ...$named]) as $path) {
                    $namedBy[$path][] = $text->label;
                }

                if ($selected) {
                    $checked++;

                    if ($images !== []) {
                        $plans[] = ['source' => $textSource, 'text' => $text, 'images' => $images];
                    }
                }
            }
        }

        /** @var array<string, string> $uploaded Cloudinary path of each folder and local file already uploaded */
        $uploaded = [];
        /** @var array<string, list<string>> $saved labels of the texts saved with each local file moved */
        $saved = [];

        foreach ($plans as ['source' => $textSource, 'text' => $text, 'images' => $images]) {
            $rewrites = $lines = [];
            $current = null;
            $folder = $textSource->folder();

            try {
                foreach ($images as $image) {
                    $current = $image['shown'];
                    $key = $folder->scope.'|'.$image['path'];
                    $target = $uploaded[$key] ?? $this->upload($image['path'], $folder, $dryRun);
                    $uploaded[$key] = $target;
                    $rewrites[$image['tag']] = ['src' => $this->cloud()->url($target), 'id' => $target];
                    $lines[] = $text->label.': '.$image['shown'].' → '.$target;
                }

                $current = null;

                if (! $dryRun && ! $textSource->replace($text, $this->tags->rewrite($text->html, $rewrites))) {
                    throw new RuntimeException('Changed while moving');
                }
            } catch (Throwable $e) {
                $failed[] = $text->label.($current !== null ? ': '.$current : '').' ('.$e->getMessage().')';

                continue;
            }

            array_push($moved, ...$lines);

            foreach (array_unique(array_column($images, 'path')) as $path) {
                $saved[$path][] = $text->label;
            }
        }

        foreach ($saved as $path => $labels) {
            $pending = array_values(array_diff($namedBy[$path] ?? [], $labels));

            if ($held !== []) {
                $kept[] = $path.' (deletion held)';
            } elseif ($pending !== []) {
                $kept[] = $path.' (still named by '.implode(', ', $pending).')';
            } elseif (! $dryRun && ! $this->local()->delete($path)) {
                $failed[] = $path.' (moved, but the local copy could not be deleted)';
            }
        }

        return new EditorImageMoveReportDTO($checked, $moved, $broken, $failed, $otherHosts, $kept, $held);
    }

    /**
     * Every text of a source, read whole before anything moves: a source that fails halfway counts as
     * unread, never as a source naming fewer files.
     *
     * @return list<EditorTextDTO>
     */
    private function read(EditorTextSourceInterface $source): array
    {
        $texts = [];

        foreach ($source->texts() as $text) {
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * Modules installed on the site whose provider did not load (disabled, or enabled but incompatible):
     * their texts may name local copies nobody can read now.
     *
     * @return list<string>
     */
    private function unreadableModules(): array
    {
        try {
            $loaded = $this->loadedModules->names();
            $installed = array_filter($this->modules->all()->all(), static fn (Module $module): bool => $module->isInstalled());
        } catch (Throwable $e) {
            return ['The installed modules could not be read ('.$e->getMessage().')'];
        }

        $held = [];

        foreach ($installed as $module) {
            if (! in_array($module->name()->value, $loaded, true)) {
                $held[] = 'The module '.$module->name()->value.' is installed but not loaded (disabled or incompatible): its texts cannot be read';
            }
        }

        return $held;
    }

    /**
     * The path on the local disk an image points at, or null when it is not a local image. Images
     * already in a content folder are on Cloudinary; the src wins over data-id, which Trix never wrote.
     */
    /**
     * @param  list<string>  $hosts
     */
    private function localPath(?string $src, ?string $id, array $hosts): ?string
    {
        if ($id !== null && EditorImageFolder::ofPath($id) instanceof EditorImageFolder) {
            return null;
        }

        $fromSrc = $src !== null ? $this->pathOfLocalUrl($src, $hosts) : null;

        if ($fromSrc !== null) {
            return $fromSrc;
        }

        if ($id === null || $id === '' || str_contains($id, '://')) {
            return null;
        }

        return $this->isMovable($id) ? $id : null;
    }

    /**
     * "/storage/<path>", relative or on a host of the site, as the local disk serves it.
     *
     * @param  list<string>  $hosts
     */
    private function pathOfLocalUrl(string $src, array $hosts): ?string
    {
        $url = $this->storageUrl($src);

        if ($url === null || ($url['host'] !== null && ! in_array($url['host'], $hosts, true))) {
            return null;
        }

        return $url['path'];
    }

    /**
     * A "/storage/<path>" URL on a host that is not one of the site's: maybe a previous domain.
     *
     * @param  list<string>  $hosts
     * @return array{host: string, path: string}|null
     */
    private function storageUrlOnOtherHost(string $src, array $hosts): ?array
    {
        $url = $this->storageUrl($src);

        if ($url === null || $url['host'] === null || in_array($url['host'], $hosts, true)) {
            return null;
        }

        return ['host' => $url['host'], 'path' => $url['path']];
    }

    /**
     * The lowercase host (null when relative) and disk path of a "/storage/<path>" http(s) URL.
     *
     * @return array{host: ?string, path: string}|null
     */
    private function storageUrl(string $src): ?array
    {
        $prefix = rtrim((string) (parse_url($this->localBaseUrl(), PHP_URL_PATH) ?? ''), '/').'/';
        $url = parse_url($src);

        if ($url === false || $prefix === '/' || isset($url['scheme']) && ! in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $path = $url['path'] ?? '';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        return [
            'host' => isset($url['host']) ? strtolower($url['host']) : null,
            'path' => rawurldecode(substr($path, strlen($prefix))),
        ];
    }

    /**
     * Hosts whose "/storage/" URLs are this site's: the local disk's, the application's and the
     * previous ones passed in.
     *
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function siteHosts(array $extra): array
    {
        $hosts = [parse_url($this->localBaseUrl(), PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST), ...$extra];

        return array_values(array_unique(array_map(strtolower(...), array_filter($hosts, is_string(...)))));
    }

    private function localBaseUrl(): string
    {
        return (string) config('filesystems.disks.'.self::LOCAL_DISK.'.url');
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

    private function upload(string $path, EditorImageFolder $folder, bool $dryRun): string
    {
        $contents = $this->local()->get($path) ?? throw new RuntimeException('Could not read the local copy');
        $month = CarbonImmutable::createFromTimestamp($this->local()->lastModified($path), (string) config('app.timezone'));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = Uuid::uuid5(self::NAMESPACE, $folder->scope.':'.hash('sha256', $contents))->toString();
        $target = $folder->directory($month).'/'.$name.($extension !== '' ? '.'.$extension : '');

        if (! $dryRun && $this->cloud()->put($target, $contents) !== true) {
            throw new RuntimeException('Upload refused');
        }

        return $target;
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
