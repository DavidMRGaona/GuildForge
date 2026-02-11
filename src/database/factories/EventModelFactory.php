<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
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
        $title = $this->faker->sentence(3);

        return [
            'id' => $this->faker->uuid(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->paragraphs(3, true),
            'start_date' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'end_date' => fn (array $attr) => Carbon::parse($attr['start_date'])->addDays(rand(0, 3)),
            'location' => $this->faker->optional()->address(),
            'image_public_id' => null,
            'member_price' => $this->faker->optional(0.7)->randomFloat(2, 5, 50),
            'non_member_price' => fn (array $attr) => $attr['member_price'] !== null
                ? $attr['member_price'] + $this->faker->randomFloat(2, 2, 10)
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
            $startDate = $this->faker->dateTimeBetween('+1 day', '+3 months');

            return [
                'start_date' => $startDate,
                'end_date' => (clone $startDate)->modify('+'.rand(0, 3).' days'),
            ];
        });
    }

    /**
     * Indicate that the event is in the past.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes): array {
            $startDate = $this->faker->dateTimeBetween('-3 months', '-1 day');

            return [
                'start_date' => $startDate,
                'end_date' => (clone $startDate)->modify('+'.rand(0, 3).' days'),
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
