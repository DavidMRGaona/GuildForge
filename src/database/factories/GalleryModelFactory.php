<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryModel>
 */
final class GalleryModelFactory extends Factory
{
    protected $model = GalleryModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'id' => fake()->uuid(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->optional()->paragraphs(2, true),
            'is_published' => false,
        ];
    }

    /**
     * Indicate that the gallery is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the gallery is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }
}
