<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Filament\Media\Sources\GalleryPhotoMediaSource;
use App\Filament\Resources\GalleryResource;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\TestCase;

final class GalleryPhotoMediaSourceTest extends TestCase
{
    use ColumnSourceContractTests;
    use LazilyRefreshDatabase;

    protected function source(): MediaUsageSourceInterface
    {
        return app(GalleryPhotoMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return PhotoModel::factory()->create(['image_public_id' => $path ?? '']);
    }

    protected function updatePermission(): string
    {
        return 'galleries.update';
    }

    public function test_declares_its_type_and_folders(): void
    {
        $this->assertSame('galleries', $this->source()->type());
        $this->assertSame(['galleries/'], $this->source()->folders());
    }

    public function test_title_and_edit_link_are_the_galleries(): void
    {
        $gallery = GalleryModel::factory()->create(['title' => 'Torneo de primavera']);
        $photo = PhotoModel::factory()->forGallery($gallery)->create(['image_public_id' => 'galleries/a.jpg']);
        $user = $this->editorWithPermissions(['galleries.update']);

        $usage = $this->source()->usagesFor((string) $photo->getKey())[0];

        $this->assertSame('Torneo de primavera', $usage->title);
        $this->assertSame((string) $photo->getKey(), $usage->recordId);
        $this->assertSame(GalleryResource::getUrl('edit', ['record' => $gallery]), $this->source()->editUrl($usage->recordId, $user));
    }
}
