<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlideModel>
 */
final class HeroSlideModelFactory extends Factory
{
    protected $model = HeroSlideModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'title' => $this->faker->sentence(3),
            'subtitle' => $this->faker->optional()->sentence(5),
            'button_text' => $this->faker->optional()->words(2, true),
            'button_url' => $this->faker->optional()->url(),
            'image_public_id' => null,
            'is_active' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the slide is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the slide is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific sort order for the slide.
     */
    public function withOrder(int $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $order,
        ]);
    }
}
