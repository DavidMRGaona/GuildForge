<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class GalleryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_displays_published_galleries(): void
    {
        GalleryModel::factory()->published()->count(3)->create();
        GalleryModel::factory()->draft()->count(2)->create();

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 3)
        );
    }

    public function test_index_paginates_galleries(): void
    {
        GalleryModel::factory()->published()->count(15)->create();

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 12)
                ->has('galleries.meta.currentPage')
                ->has('galleries.meta.lastPage')
                ->has('galleries.meta.perPage')
                ->has('galleries.meta.total')
        );
    }

    public function test_index_orders_galleries_by_created_at_descending(): void
    {
        $oldGallery = GalleryModel::factory()->published()->create([
            'title' => 'Old Gallery',
            'created_at' => now()->subDays(10),
        ]);
        $newGallery = GalleryModel::factory()->published()->create([
            'title' => 'New Gallery',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 2)
                ->where('galleries.data.0.title', 'New Gallery')
                ->where('galleries.data.1.title', 'Old Gallery')
        );
    }

    public function test_index_includes_photo_count(): void
    {
        $gallery = GalleryModel::factory()->published()->create();
        PhotoModel::factory()->forGallery($gallery)->count(5)->create();

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data.0.photoCount')
                ->where('galleries.data.0.photoCount', 5)
        );
    }

    public function test_show_displays_single_published_gallery(): void
    {
        $gallery = GalleryModel::factory()->published()->create([
            'title' => 'Test Gallery',
            'slug' => 'test-gallery',
            'description' => 'Test description',
        ]);

        $response = $this->get('/galeria/test-gallery');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Show')
                ->has('gallery')
                ->where('gallery.title', 'Test Gallery')
                ->where('gallery.slug', 'test-gallery')
                ->where('gallery.description', 'Test description')
        );
    }

    public function test_show_includes_photos_ordered_by_sort_order(): void
    {
        $gallery = GalleryModel::factory()->published()->create([
            'slug' => 'test-gallery',
        ]);

        PhotoModel::factory()->forGallery($gallery)->withSortOrder(3)->create([
            'caption' => 'Third Photo',
        ]);
        PhotoModel::factory()->forGallery($gallery)->withSortOrder(1)->create([
            'caption' => 'First Photo',
        ]);
        PhotoModel::factory()->forGallery($gallery)->withSortOrder(2)->create([
            'caption' => 'Second Photo',
        ]);

        $response = $this->get('/galeria/test-gallery');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Show')
                ->has('gallery.photos', 3)
                ->where('gallery.photos.0.caption', 'First Photo')
                ->where('gallery.photos.1.caption', 'Second Photo')
                ->where('gallery.photos.2.caption', 'Third Photo')
        );
    }

    public function test_show_returns_404_for_unpublished_gallery(): void
    {
        GalleryModel::factory()->draft()->create([
            'slug' => 'unpublished-gallery',
        ]);

        $response = $this->get('/galeria/unpublished-gallery');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_nonexistent_gallery(): void
    {
        $response = $this->get('/galeria/nonexistent-gallery');

        $response->assertStatus(404);
    }

    public function test_index_includes_tags_in_response(): void
    {
        $tag1 = TagModel::factory()->forGalleries()->create([
            'name' => 'Event Photos',
            'slug' => 'event-photos',
        ]);
        $tag2 = TagModel::factory()->forGalleries()->create([
            'name' => 'Miniatures',
            'slug' => 'miniatures',
        ]);

        TagModel::factory()->forEvents()->create();

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('tags', 2)
                ->where('tags.0.name', fn ($val) => in_array($val, ['Event Photos', 'Miniatures']))
                ->where('tags.1.name', fn ($val) => in_array($val, ['Event Photos', 'Miniatures']))
        );
    }

    public function test_index_includes_current_tag_filter(): void
    {
        TagModel::factory()->forGalleries()->create([
            'slug' => 'event-photos',
        ]);

        $response = $this->get('/galeria?tags=event-photos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->where('currentTags', ['event-photos'])
        );
    }

    public function test_index_filters_galleries_by_tag(): void
    {
        $tag = TagModel::factory()->forGalleries()->create([
            'name' => 'Event Photos',
            'slug' => 'event-photos',
        ]);

        $taggedGallery = GalleryModel::factory()->published()->create([
            'title' => 'Tournament 2024',
        ]);
        $taggedGallery->tags()->attach($tag->id);

        $untaggedGallery = GalleryModel::factory()->published()->create([
            'title' => 'Painted Miniatures',
        ]);

        $response = $this->get('/galeria?tags=event-photos');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 1)
                ->where('galleries.data.0.title', 'Tournament 2024')
        );
    }

    public function test_index_shows_all_galleries_when_no_tag_filter(): void
    {
        $tag = TagModel::factory()->forGalleries()->create();

        $taggedGallery = GalleryModel::factory()->published()->create(['title' => 'Tagged Gallery']);
        $taggedGallery->tags()->attach($tag->id);

        $untaggedGallery = GalleryModel::factory()->published()->create(['title' => 'Untagged Gallery']);

        $response = $this->get('/galeria');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 2)
        );
    }

    public function test_index_returns_empty_when_tag_has_no_galleries(): void
    {
        $tag = TagModel::factory()->forGalleries()->create([
            'slug' => 'unused-tag',
        ]);

        GalleryModel::factory()->published()->create();

        $response = $this->get('/galeria?tags=unused-tag');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 0)
        );
    }

    public function test_show_includes_gallery_tags(): void
    {
        $tag1 = TagModel::factory()->forGalleries()->create([
            'name' => 'Event Photos',
            'slug' => 'event-photos',
        ]);
        $tag2 = TagModel::factory()->forGalleries()->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);

        $gallery = GalleryModel::factory()->published()->create([
            'slug' => 'test-gallery',
        ]);
        $gallery->tags()->attach([$tag1->id, $tag2->id]);

        $response = $this->get('/galeria/test-gallery');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Show')
                ->has('gallery.tags', 2)
                ->where('gallery.tags.0.name', fn ($val) => in_array($val, ['Event Photos', 'Warhammer 40k']))
                ->where('gallery.tags.1.name', fn ($val) => in_array($val, ['Event Photos', 'Warhammer 40k']))
        );
    }

    public function test_show_displays_gallery_without_tags(): void
    {
        $gallery = GalleryModel::factory()->published()->create([
            'slug' => 'untagged-gallery',
        ]);

        $response = $this->get('/galeria/untagged-gallery');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Show')
                ->has('gallery.tags', 0)
        );
    }

    public function test_index_filters_by_child_tag(): void
    {
        $parent = TagModel::factory()->forGalleries()->create([
            'name' => 'Miniature Games',
            'slug' => 'miniature-games',
        ]);

        $child = TagModel::factory()->forGalleries()->withParent($parent)->create([
            'name' => 'Warhammer 40k',
            'slug' => 'warhammer-40k',
        ]);

        $gallery = GalleryModel::factory()->published()->create([
            'title' => 'Warhammer Army Showcase',
        ]);
        $gallery->tags()->attach($child->id);

        $response = $this->get('/galeria?tags=warhammer-40k');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Gallery/Index')
                ->has('galleries.data', 1)
                ->where('galleries.data.0.title', 'Warhammer Army Showcase')
        );
    }
}
