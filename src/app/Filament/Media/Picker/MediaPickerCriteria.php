<?php

declare(strict_types=1);

namespace App\Filament\Media\Picker;

use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a file upload field accepts from the media library: the field's rules only reach uploaded files,
 * so a chosen file is held to them here, both when listing and when the form is saved.
 */
final readonly class MediaPickerCriteria
{
    /** Cloudinary formats of each MIME type a field may accept */
    private const array FORMATS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/jpg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/svg+xml' => ['svg'],
        'image/x-icon' => ['ico'],
        'image/vnd.microsoft.icon' => ['ico'],
        'image/avif' => ['avif'],
    ];

    /**
     * @param  list<string>|null  $formats  null for any image
     * @param  (Closure(Builder<MediaFileModel>): Builder<MediaFileModel>)|null  $constrain  the field's own rule, if any
     */
    public function __construct(
        public ?array $formats,
        public ?int $maxBytes,
        public ?Closure $constrain = null,
    ) {}

    /**
     * @param  (Closure(Builder<MediaFileModel>): Builder<MediaFileModel>)|null  $constrain
     */
    public static function fromField(FileUpload $field, ?Closure $constrain): self
    {
        $types = $field->getAcceptedFileTypes();
        $formats = null;

        if ($types !== null && ! in_array('image/*', $types, true)) {
            $formats = [];

            foreach ($types as $type) {
                foreach (self::FORMATS[strtolower($type)] ?? [] as $format) {
                    $formats[] = $format;
                }
            }

            $formats = array_values(array_unique($formats));
        }

        $maxSize = $field->getMaxSize();

        return new self($formats, $maxSize !== null ? $maxSize * 1024 : null, $constrain);
    }

    /**
     * The reusable files of the library this field accepts.
     *
     * @param  Builder<MediaFileModel>  $query
     * @return Builder<MediaFileModel>
     */
    public function apply(Builder $query): Builder
    {
        $query = resolve(MediaLibraryQueryInterface::class)->applyReusable($query);

        if ($this->formats !== null) {
            $query->whereIn('media_files.format', $this->formats);
        }

        if ($this->maxBytes !== null) {
            $query->where('media_files.bytes', '<=', $this->maxBytes);
        }

        return $this->constrain instanceof Closure ? ($this->constrain)($query) : $query;
    }
}
