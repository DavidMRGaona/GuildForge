<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TagModel>
 */
final class TagModelFactory extends Factory
{
    protected $model = TagModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'id' => fake()->uuid(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'parent_id' => null,
            'applies_to' => ['events', 'articles', 'galleries'],
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }

    /**
     * Tag applies to events only.
     */
    public function forEvents(): static
    {
        return $this->state(fn (array $attributes): array => [
            'applies_to' => ['events'],
        ]);
    }

    /**
     * Tag applies to articles only.
     */
    public function forArticles(): static
    {
        return $this->state(fn (array $attributes): array => [
            'applies_to' => ['articles'],
        ]);
    }

    /**
     * Tag applies to galleries only.
     */
    public function forGalleries(): static
    {
        return $this->state(fn (array $attributes): array => [
            'applies_to' => ['galleries'],
        ]);
    }

    /**
     * Tag applies to events and articles.
     */
    public function forEventsAndArticles(): static
    {
        return $this->state(fn (array $attributes): array => [
            'applies_to' => ['events', 'articles'],
        ]);
    }

    /**
     * Set a parent tag.
     */
    public function withParent(TagModel $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Set a specific color.
     */
    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes): array => [
            'color' => $color,
        ]);
    }

    /**
     * Set the sort order.
     */
    public function withSortOrder(int $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $order,
        ]);
    }
}
