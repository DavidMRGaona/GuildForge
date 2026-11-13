<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
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
            'end_date' => fn (array $attr): Carbon => $this->endAfter($attr['start_date']),
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
                'end_date' => $this->endAfter($startDate),
            ];
        });
    }

    /**
     * Indicate that the event is in the past: it has always ended.
     *
     * The start is drawn at least four days back, so even the longest end
     * (start + 72 hours, or start + 2 days with multiDay()) lies behind now.
     */
    public function past(): static
    {
        return $this->state(function (array $attributes): array {
            $startDate = $this->faker->dateTimeBetween('-3 months', '-4 days');

            return [
                'start_date' => $startDate,
                'end_date' => $this->endAfter($startDate),
            ];
        });
    }

    /**
     * Indicate that the event has download links.
     */
    public function withDownloadLinks(int $count = 2): static
    {
        return $this->state(fn (array $attributes): array => [
            'download_links' => collect(range(1, $count))->map(fn (int $i): array => [
                'label' => $this->faker->sentence(3),
                'url' => $this->faker->url(),
                'description' => $this->faker->optional(0.7)->sentence(),
            ])->toArray(),
        ]);
    }

    /**
     * An end between two hours and three days after the start, never equal to it:
     * the panel form rejects an event that ends when it starts.
     */
    private function endAfter(DateTimeInterface|string $start): Carbon
    {
        return Carbon::parse($start)->addHours($this->faker->numberBetween(2, 72));
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

    /**
     * A proposal from the web in the given status. Drafts are unsubmitted unless $submitted (a
     * returned draft); any other status was submitted. Approved ones are published.
     */
    public function proposal(ProposalStatus $status = ProposalStatus::Draft, ?UserModel $by = null, bool $submitted = false): static
    {
        return $this->state(function (array $attributes) use ($status, $by, $submitted): array {
            $proposer = $by instanceof UserModel ? $by->id : UserModel::factory();
            $wasSubmitted = $status !== ProposalStatus::Draft || $submitted;
            $wasReviewed = in_array($status, [ProposalStatus::Approved, ProposalStatus::Rejected], true) || ($status === ProposalStatus::Draft && $submitted);

            return [
                'proposal_status' => $status,
                'proposed_by' => $proposer,
                'submitted_at' => $wasSubmitted ? now()->subDay() : null,
                'reviewed_at' => $wasReviewed ? now() : null,
                'is_published' => $status === ProposalStatus::Approved,
            ];
        });
    }
}
