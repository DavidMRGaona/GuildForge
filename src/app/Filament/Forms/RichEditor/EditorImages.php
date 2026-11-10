<?php

declare(strict_types=1);

namespace App\Filament\Forms\RichEditor;

use App\Application\Content\Services\EditorImageUrlsInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use Filament\Forms\Components\RichEditor;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Images inserted with a rich editor go to the "images" disk (Cloudinary), in the content folder
 * of their origin and month, with a UUID name; only images, as large as the image uploads (core#481).
 */
final class EditorImages
{
    public const string DISK = 'images';

    /** No SVG: it can carry scripts */
    public const array ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Kilobytes, as the featured images */
    public const int MAX_SIZE = 2048;

    public static function configure(RichEditor $editor, EditorImageOrigin $origin): RichEditor
    {
        return $editor
            ->fileAttachments(true)
            ->fileAttachmentsDisk(self::DISK)
            ->fileAttachmentsDirectory(static fn (): string => $origin->directory(now()))
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(self::ACCEPTED_TYPES)
            ->fileAttachmentsMaxSize(self::MAX_SIZE)
            ->saveUploadedFileAttachmentUsing(static fn (TemporaryUploadedFile $file): ?string => self::store($file, $origin))
            // Built locally: the default asks Cloudinary's Admin API whether each image exists every time
            // the form loads. Null for an image still on the local disk keeps the URL saved in the HTML.
            ->getFileAttachmentUrlUsing(static fn (mixed $file): ?string => is_string($file)
                ? app(EditorImageUrlsInterface::class)->urlFor($file)
                : null);
    }

    private static function store(TemporaryUploadedFile $file, EditorImageOrigin $origin): ?string
    {
        $extension = $file->guessExtension() ?? strtolower($file->getClientOriginalExtension());

        $path = $file->storeAs($origin->directory(now()), Str::uuid()->toString().'.'.$extension, [
            'disk' => self::DISK,
            'mimetype' => $file->getMimeType(),
        ]);

        return is_string($path) ? $path : null;
    }
}
