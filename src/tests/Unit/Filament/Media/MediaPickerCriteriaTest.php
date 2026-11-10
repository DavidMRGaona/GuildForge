<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Media;

use App\Filament\Media\Picker\MediaPickerCriteria;
use Filament\Forms\Components\FileUpload;
use Tests\TestCase;

final class MediaPickerCriteriaTest extends TestCase
{
    public function test_criteria_maps_mime_types_and_max_size(): void
    {
        $criteria = MediaPickerCriteria::fromField(
            FileUpload::make('favicon')->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/jpeg'])->maxSize(512),
            null,
        );

        $this->assertSame(['png', 'ico', 'svg', 'jpg', 'jpeg'], $criteria->formats);
        $this->assertSame(512 * 1024, $criteria->maxBytes);
    }

    public function test_any_image_type_accepts_web_raster_formats_only(): void
    {
        // Cloudinary keeps PDF, PSD or AI files as image resources too; an SVG is only taken where a field asks for it
        $raster = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

        $this->assertSame($raster, MediaPickerCriteria::fromField(FileUpload::make('image')->image(), null)->formats);
        $this->assertSame($raster, MediaPickerCriteria::fromField(FileUpload::make('image'), null)->formats);
        $this->assertNull(MediaPickerCriteria::fromField(FileUpload::make('image'), null)->maxBytes);
    }

    public function test_an_unknown_mime_type_accepts_nothing_of_it(): void
    {
        $this->assertSame([], MediaPickerCriteria::fromField(FileUpload::make('doc')->acceptedFileTypes(['application/pdf']), null)->formats);
    }
}
