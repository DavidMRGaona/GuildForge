<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoModel>
 */
final class PhotoModelFactory extends Factory
{
    protected $model = PhotoModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'gallery_id' => GalleryModel::factory(),
            'image_public_id' => 'test/placeholder/' . fake()->uuid(),
            'caption' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Set the gallery for this photo.
     */
    public function forGallery(GalleryModel $gallery): static
    {
        return $this->state(fn (array $attributes): array => [
            'gallery_id' => $gallery->id,
        ]);
    }

    /**
     * Set a specific sort order.
     */
    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }
}
