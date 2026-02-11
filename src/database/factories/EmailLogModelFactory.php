<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Mail\Enums\EmailStatus;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLogModel>
 */
final class EmailLogModelFactory extends Factory
{
    protected $model = EmailLogModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'recipient' => $this->faker->safeEmail(),
            'sender' => $this->faker->safeEmail(),
            'subject' => $this->faker->sentence(4),
            'mailer' => 'smtp',
            'status' => EmailStatus::Sent,
            'error_message' => null,
            'message_id' => $this->faker->uuid(),
            'metadata' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailStatus::Failed,
            'error_message' => $this->faker->sentence(),
            'sent_at' => null,
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailStatus::Bounced,
        ]);
    }

    public function complained(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EmailStatus::Complained,
        ]);
    }
}
