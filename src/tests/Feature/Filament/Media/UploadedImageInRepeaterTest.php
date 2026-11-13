<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Media;

use App\Filament\Resources\GalleryResource\Pages\CreateGallery;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * core#608: a repeater saves its relationship by reading each item's state again, after the upload field has
 * already stored the new file. That stored path must be accepted, while a path the form did not upload,
 * open with or pick from the library is still refused.
 */
final class UploadedImageInRepeaterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->actingAs(UserModel::factory()->admin()->create());
    }

    public function test_a_gallery_is_created_with_photos_uploaded_in_the_same_form(): void
    {
        $category = TagModel::factory()->forGalleries()->create();

        Livewire::test(CreateGallery::class)
            ->fillForm([
                'title' => 'Jornadas de otoño',
                'slug' => 'jornadas-de-otono',
                'category_id' => $category->id,
                'photos' => [
                    ['image_public_id' => [UploadedFile::fake()->image('mesa.jpg')], 'caption' => 'Mesa'],
                    ['image_public_id' => [UploadedFile::fake()->image('dados.png')], 'caption' => 'Dados'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $gallery = GalleryModel::query()->where('title', 'Jornadas de otoño')->firstOrFail();
        $paths = PhotoModel::query()->where('gallery_id', $gallery->id)->orderBy('sort_order')->pluck('image_public_id')->all();

        $this->assertCount(2, $paths);

        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression('#^galleries/\d{4}/\d{2}/[0-9a-f-]{36}\.(jpg|png)$#', $path);
            Storage::disk('images')->assertExists($path);
        }
    }

    public function test_a_photo_uploaded_while_editing_a_gallery_is_saved(): void
    {
        $gallery = GalleryModel::factory()->create();
        $gallery->tags()->attach(TagModel::factory()->forGalleries()->create());

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->fillForm(['photos' => [['image_public_id' => [UploadedFile::fake()->image('nueva.jpg')], 'caption' => null]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(1, PhotoModel::query()->where('gallery_id', $gallery->id)->get());
    }

    public function test_a_path_the_form_did_not_upload_is_still_refused(): void
    {
        $gallery = GalleryModel::factory()->create();
        $gallery->tags()->attach(TagModel::factory()->forGalleries()->create());
        Storage::disk('images')->put('galleries/2026/11/ajena.jpg', 'x');

        Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()])
            ->set('data.photos', ['new-1' => ['image_public_id' => ['a' => 'galleries/2026/11/ajena.jpg'], 'caption' => null]])
            ->call('save')
            ->assertHasFormErrors(['photos.new-1.image_public_id']);

        $this->assertCount(0, PhotoModel::query()->where('gallery_id', $gallery->id)->get());
    }
}
