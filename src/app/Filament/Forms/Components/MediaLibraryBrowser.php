<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\Exceptions\InvalidMediaFileIdException;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Picker\MediaPickerCriteria;
use App\Filament\Pages\MediaLibraryPage;
use App\Filament\Support\FileSize;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Filament\Forms\Components\Field;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * The grid of the media library picker (MediaLibraryPicker) with the preview of the chosen file. Its state is
 * the id of the chosen file; the search, origin, status and page fields next to it narrow the grid.
 */
final class MediaLibraryBrowser extends Field
{
    public const int PER_PAGE = 24;

    private const int THUMBNAIL_SIZE = 300;

    private const int PREVIEW_WIDTH = 800;

    protected string $view = 'filament.forms.components.media-library-browser';

    private ?MediaPickerCriteria $criteria = null;

    public function criteria(MediaPickerCriteria $criteria): static
    {
        $this->criteria = $criteria;

        return $this;
    }

    /**
     * Files of the current page.
     *
     * @return array{items: list<array{fileId: string, thumbnailUrl: ?string, title: ?string, origin: string, statusLabel: string, statusColor: string}>, page: int, pages: int}
     */
    public function getPage(): array
    {
        $library = $this->library();
        $get = $this->makeGetUtility();
        $query = $this->requireCriteria()->apply($library->query());

        $search = $get('search');
        if (is_string($search) && trim($search) !== '') {
            $query = $library->applySearch($query, trim($search));
        }

        $origin = $get('origin');
        if (is_string($origin) && $origin !== '') {
            $query = $library->applyOrigin($query, $origin);
        }

        $status = is_string($get('status')) ? MediaFileStatus::tryFrom($get('status')) : null;
        if ($status instanceof MediaFileStatus) {
            $query = $library->applyStatus($query, $status);
        }

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) $get('page')));

        $items = array_values($query->with('usages')
            ->orderByDesc('media_files.uploaded_at')
            ->orderBy('media_files.id')
            ->forPage($page, self::PER_PAGE)
            ->get()
            ->map(fn (MediaFileModel $file): array => [
                'fileId' => $file->file_id,
                'thumbnailUrl' => $this->urls()->mediaThumbnail($file->file_id, self::THUMBNAIL_SIZE),
                'title' => $this->titleOf($file),
                'origin' => $this->originOf($file),
                'statusLabel' => MediaLibraryPage::statusLabel($library->statusOf($file)),
                'statusColor' => MediaLibraryPage::statusColor($library->statusOf($file)),
            ])
            ->all());

        return ['items' => $items, 'page' => $page, 'pages' => $pages];
    }

    /**
     * The chosen file, as the preview shows it; null when nothing (or nothing this field accepts) is chosen.
     *
     * @return array{fileId: string, imageUrl: ?string, dimensions: ?string, format: ?string, size: string, uploadedAt: string, usages: list<array{origin: string, title: string, role: string}>}|null
     */
    public function getPreview(): ?array
    {
        $fileId = $this->getState();

        if (! is_string($fileId) || $fileId === '') {
            return null;
        }

        $file = $this->requireCriteria()->apply($this->library()->query())
            ->with('usages')
            ->where('media_files.file_id', $fileId)
            ->first();

        if (! $file instanceof MediaFileModel) {
            return null;
        }

        $registry = resolve(MediaSourceRegistryInterface::class);

        return [
            'fileId' => $file->file_id,
            'imageUrl' => $this->urls()->mediaPreview($file->file_id, self::PREVIEW_WIDTH),
            'dimensions' => $file->width !== null && $file->height !== null ? $file->width.' × '.$file->height : null,
            'format' => $file->format !== null ? mb_strtoupper($file->format) : null,
            'size' => FileSize::format($file->bytes),
            'uploadedAt' => resolve(TimezoneServiceInterface::class)->format($file->uploaded_at, 'd/m/Y'),
            'usages' => array_values($file->usages
                ->sortBy([['source_type', 'asc'], ['title', 'asc'], ['id', 'asc']])
                ->map(fn (MediaUsageModel $usage): array => [
                    'origin' => $this->labelOf($registry->source($usage->source_type)) ?? $usage->source_type,
                    'title' => $usage->title,
                    'role' => $usage->role,
                ])
                ->all()),
        ];
    }

    /**
     * State path of the page field beside this one, which the grid's arrows change.
     */
    public function getPageStatePath(): string
    {
        return Str::beforeLast((string) $this->getStatePath(), '.').'.page';
    }

    private function requireCriteria(): MediaPickerCriteria
    {
        return $this->criteria ?? throw new LogicException('The media library browser needs the criteria of its field.');
    }

    private function titleOf(MediaFileModel $file): ?string
    {
        $usages = $file->usages->sortBy([['title', 'asc'], ['id', 'asc']])->values();
        $first = $usages->first();

        if (! $first instanceof MediaUsageModel) {
            return null;
        }

        return $usages->count() > 1
            ? __('filament.media_library.title_more', ['title' => $first->title, 'count' => $usages->count() - 1])
            : $first->title;
    }

    private function originOf(MediaFileModel $file): string
    {
        try {
            $source = resolve(MediaSourceRegistryInterface::class)->claimingSource(new MediaFileId($file->file_id));
        } catch (InvalidMediaFileIdException) {
            $source = null;
        }

        return $this->labelOf($source) ?? __('filament.media_library.unknown_origin');
    }

    private function labelOf(?MediaUsageSourceInterface $source): ?string
    {
        try {
            return $source?->label();
        } catch (Throwable) {
            return null;
        }
    }

    private function library(): MediaLibraryQueryInterface
    {
        return resolve(MediaLibraryQueryInterface::class);
    }

    /**
     * Ids in the index are relative to the storage prefix, so URLs use the same one.
     */
    private function urls(): CloudinaryImageUrlBuilder
    {
        $cloudName = config('cloudinary.cloud_name');

        return new CloudinaryImageUrlBuilder(
            is_string($cloudName) ? $cloudName : '',
            resolve(MediaFileIdResolverInterface::class)->prefix(),
        );
    }
}
