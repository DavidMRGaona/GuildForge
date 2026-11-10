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

    public function test_any_image_type_accepts_every_format(): void
    {
        $this->assertNull(MediaPickerCriteria::fromField(FileUpload::make('image')->image(), null)->formats);
        $this->assertNull(MediaPickerCriteria::fromField(FileUpload::make('image'), null)->formats);
        $this->assertNull(MediaPickerCriteria::fromField(FileUpload::make('image'), null)->maxBytes);
    }

    public function test_an_unknown_mime_type_accepts_nothing_of_it(): void
    {
        $this->assertSame([], MediaPickerCriteria::fromField(FileUpload::make('doc')->acceptedFileTypes(['application/pdf']), null)->formats);
    }
}
