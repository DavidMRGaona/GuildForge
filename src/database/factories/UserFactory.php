<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserModel>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<UserModel>
     */
    protected $model = UserModel::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'name' => $name,
            'display_name' => $name,
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar_public_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (UserModel $user): void {
            $role = RoleModel::firstOrCreate(
                ['name' => 'admin'],
                ['display_name' => 'Administrator']
            );
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    /**
     * Indicate that the user is an editor.
     */
    public function editor(): static
    {
        return $this->afterCreating(function (UserModel $user): void {
            $role = RoleModel::firstOrCreate(
                ['name' => 'editor'],
                ['display_name' => 'Editor']
            );
            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }
}
