<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Gallery;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentGalleryRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class EloquentGalleryRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EloquentGalleryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentGalleryRepository;
    }

    public function test_it_finds_gallery_by_id(): void
    {
        $model = GalleryModel::factory()->create([
            'title' => 'Test Gallery',
            'slug' => 'test-gallery',
        ]);

        $gallery = $this->repository->findById(new GalleryId($model->id));

        $this->assertNotNull($gallery);
        $this->assertEquals($model->id, $gallery->id()->value);
        $this->assertEquals('Test Gallery', $gallery->title());
        $this->assertEquals('test-gallery', $gallery->slug()->value);
    }

    public function test_it_returns_null_when_not_found(): void
    {
        $gallery = $this->repository->findById(GalleryId::generate());

        $this->assertNull($gallery);
    }

    public function test_it_finds_gallery_by_slug(): void
    {
        $model = GalleryModel::factory()->create([
            'title' => 'Warhammer Tournament',
            'slug' => 'warhammer-tournament',
        ]);

        $gallery = $this->repository->findBySlug('warhammer-tournament');

        $this->assertNotNull($gallery);
        $this->assertEquals($model->id, $gallery->id()->value);
        $this->assertEquals('Warhammer Tournament', $gallery->title());
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $gallery = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($gallery);
    }

    public function test_it_finds_published_galleries(): void
    {
        GalleryModel::factory()->published()->count(3)->create();
        GalleryModel::factory()->draft()->count(2)->create();

        $galleries = $this->repository->findPublished();

        $this->assertCount(3, $galleries);
        $galleries->each(function (Gallery $gallery) {
            $this->assertTrue($gallery->isPublished());
        });
    }

    /**
     * Inserted in the reverse of the expected order, so ties only come out right when the query breaks them.
     */
    public function test_published_galleries_break_ties_by_creation_then_id(): void
    {
        $createdAt = '2026-02-02 09:00:00';

        GalleryModel::factory()->published()->create(['title' => 'Tie oldest', 'created_at' => '2026-02-01 09:00:00']);
        GalleryModel::factory()->published()->create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Tie low id',
            'created_at' => $createdAt,
        ]);
        GalleryModel::factory()->published()->create([
            'id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff',
            'title' => 'Tie high id',
            'created_at' => $createdAt,
        ]);

        $titles = $this->repository->findPublished()->map(fn (Gallery $gallery): string => $gallery->title())->all();

        $this->assertSame(['Tie high id', 'Tie low id', 'Tie oldest'], $titles);
    }

    public function test_it_finds_all_galleries(): void
    {
        GalleryModel::factory()->published()->count(3)->create();
        GalleryModel::factory()->draft()->count(2)->create();

        $galleries = $this->repository->findAll();

        $this->assertCount(5, $galleries);
    }

    public function test_it_saves_new_gallery(): void
    {
        $id = GalleryId::generate();
        $gallery = new Gallery(
            id: $id,
            title: 'New Gallery',
            slug: new Slug('new-gallery'),
            description: 'A brand new gallery.',
            isPublished: true,
        );

        $this->repository->save($gallery);

        $this->assertDatabaseHas('galleries', [
            'id' => $id->value,
            'title' => 'New Gallery',
            'slug' => 'new-gallery',
            'description' => 'A brand new gallery.',
            'is_published' => true,
        ]);
    }

    public function test_it_updates_existing_gallery(): void
    {
        $model = GalleryModel::factory()->create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'is_published' => false,
        ]);

        $gallery = new Gallery(
            id: new GalleryId($model->id),
            title: 'Updated Title',
            slug: new Slug('updated-slug'),
            description: 'Updated description.',
            isPublished: true,
        );

        $this->repository->save($gallery);

        $this->assertDatabaseHas('galleries', [
            'id' => $model->id,
            'title' => 'Updated Title',
            'slug' => 'updated-slug',
            'description' => 'Updated description.',
            'is_published' => true,
        ]);

        $this->assertDatabaseMissing('galleries', [
            'title' => 'Original Title',
        ]);
    }

    public function test_it_deletes_gallery(): void
    {
        $model = GalleryModel::factory()->create();
        $gallery = $this->repository->findById(new GalleryId($model->id));

        $this->assertNotNull($gallery);

        $this->repository->delete($gallery);

        $this->assertDatabaseMissing('galleries', [
            'id' => $model->id,
        ]);
    }
}
