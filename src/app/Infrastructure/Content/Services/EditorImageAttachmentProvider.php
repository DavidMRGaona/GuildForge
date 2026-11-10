<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Services\EditorImageUrlsInterface;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;

/**
 * Image URLs for RichContentRenderer, which rewrites every image "src" from its "data-id". Editor
 * images get their Cloudinary URL, built locally; an image the move command has not reached yet
 * keeps its URL on the local "public" disk, as the renderer gave it before core#481. Rendering only.
 */
final readonly class EditorImageAttachmentProvider implements FileAttachmentProvider
{
    private const string LEGACY_DISK = 'public';

    public function __construct(
        private EditorImageUrlsInterface $urls,
    ) {}

    public function attribute(RichContentAttribute $attribute): static
    {
        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if (! is_string($file) || $file === '') {
            return null;
        }

        $url = $this->urls->urlFor($file);

        if ($url !== null) {
            return $url;
        }

        $legacy = Storage::disk(self::LEGACY_DISK);

        try {
            return $legacy->exists($file) ? $legacy->url($file) : null;
        } catch (FilesystemException) {
            return null;
        }
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        throw new LogicException('Editor images are uploaded by the rich editor, not by the renderer.');
    }

    public function getDefaultFileAttachmentVisibility(): string
    {
        return 'public';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    public function cleanUpFileAttachments(array $exceptIds): void {}
}
