<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

/**
 * A save that writes several records releases what one of them let go of only once every record is written:
 * a later record of the same save may have picked that very file.
 */
final class SharedImageWithinOneSaveTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_photo_removed_and_picked_again_in_the_same_save_keeps_its_file(): void
    {
        Storage::fake('images');
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $catalog);
        $catalog->add('galleries/2026/11/x');
        MediaFileModel::query()->create([
            'file_id' => 'galleries/2026/11/x',
            'resource_type' => 'image',
            'folder' => 'galleries/2026/11/',
            'format' => 'jpg',
            'bytes' => 1000,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
        $gallery = GalleryModel::factory()->create();
        // The form asks for a category
        $gallery->tags()->attach(TagModel::factory()->forGalleries()->create());
        PhotoModel::factory()->create(['gallery_id' => $gallery->id, 'image_public_id' => 'galleries/2026/11/x.jpg']);
        $this->actingAs(UserModel::factory()->admin()->create());

        // The old photo is removed and a new one, written after it, picks its file from the library
        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->set('data.photos', ['new-1' => ['image_public_id' => ['a' => 'galleries/2026/11/x.jpg'], 'caption' => null]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['galleries/2026/11/x.jpg'], PhotoModel::query()->where('gallery_id', $gallery->id)->pluck('image_public_id')->all());
        $this->assertArrayHasKey('galleries/2026/11/x', $catalog->assets);
    }
}
