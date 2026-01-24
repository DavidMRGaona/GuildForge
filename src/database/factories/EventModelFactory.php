<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventModel>
 */
final class EventModelFactory extends Factory
{
    protected $model = EventModel::class;

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
            'description' => fake()->paragraphs(3, true),
            'start_date' => $startDate = fake()->dateTimeBetween('+1 week', '+3 months'),
            'end_date' => fake()->boolean(30)
                ? (clone $startDate)->modify('+'.rand(1, 3).' days')
                : null,
            'location' => fake()->optional()->address(),
            'image_public_id' => null,
            'member_price' => fake()->optional(0.7)->randomFloat(2, 5, 50),
            'non_member_price' => fn (array $attr) => $attr['member_price'] !== null
                ? $attr['member_price'] + fake()->randomFloat(2, 2, 10)
                : null,
            'is_published' => false,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the event is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }

    /**
     * Indicate that the event is upcoming.
     */
    public function upcoming(): static
    {
        return $this->state(function (array $attributes): array {
            $startDate = fake()->dateTimeBetween('+1 day', '+3 months');

            // Recalculate end_date if it was set
            $endDate = isset($attributes['end_date']) && $attributes['end_date'] !== null
                ? (clone $startDate)->modify('+'.rand(1, 3).' days')
                : null;

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }

    /**
     * Indicate that the event is in the past.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes): array {
            $startDate = fake()->dateTimeBetween('-3 months', '-1 day');

            // Recalculate end_date if it was set, ensuring it's after start_date
            $endDate = isset($attributes['end_date']) && $attributes['end_date'] !== null
                ? (clone $startDate)->modify('+'.rand(1, 3).' days')
                : null;

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }

    /**
     * Indicate that the event is multi-day.
     *
     * Uses afterMaking to ensure end_date is calculated from the actual
     * start_date after all states have been applied (e.g., past()->multiDay()).
     */
    public function multiDay(): static
    {
        return $this->afterMaking(function (EventModel $event): void {
            $event->end_date = (clone $event->start_date)->modify('+2 days');
        });
    }
}
